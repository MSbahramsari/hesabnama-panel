<?php

use App\Models\TaxpayerProfile;
use App\Models\User;
use App\Services\Moadian\MoadianClientFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;

beforeEach(function () {
    $this->privateKey = RSA::createKey(2048)->toString('PKCS8');
});

it('renders the taxpayer setup on a member profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertSuccessful()
        ->assertSee('اطلاعات اتصال به سامانه مودیان');
});

it('lets a member complete and safely update its taxpayer profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'taxpayer_name' => 'شرکت آزمون مودیان',
        'taxpayer_type' => 'legal',
        'national_id' => '14001234567',
        'economic_code' => '411111111111',
        'fiscal_id' => 'abc123',
        'branch_code' => '1',
        'private_key' => UploadedFile::fake()->createWithContent('private.pem', $this->privateKey),
    ])->assertSessionHas('success');

    $profile = $user->taxpayerProfile()->firstOrFail();

    expect($profile->fiscal_id)->toBe('ABC123')
        ->and($profile->private_key)->toBe($this->privateKey);

    $profile->update(['connection_verified_at' => now()]);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'taxpayer_name' => 'شرکت آزمون مودیان و شرکا',
        'taxpayer_type' => 'legal',
        'national_id' => $profile->national_id,
        'economic_code' => $profile->economic_code,
        'fiscal_id' => $profile->fiscal_id,
        'branch_code' => $profile->branch_code,
    ])->assertSessionHas('success');

    expect($profile->refresh()->private_key)->toBe($this->privateKey)
        ->and($profile->taxpayer_name)->toBe('شرکت آزمون مودیان و شرکا')
        ->and($profile->connection_verified_at)->not->toBeNull();
});

it('tests the authenticated connection with the signed-in taxpayer credentials', function () {
    config()->set('services.moadian.driver', 'real');
    config()->set('services.moadian.base_url', 'https://moadian.test/api/self-tsp');
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response([
        'result' => [
            'data' => [
                'token' => 'account-token',
                'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
            ],
        ],
    ]));

    $user = User::factory()->create();
    $profile = TaxpayerProfile::factory()->for($user)->create([
        'fiscal_id' => 'ABC123',
        'economic_code' => '411111111111',
        'private_key' => $this->privateKey,
    ]);

    $this->actingAs($user)
        ->post(route('profile.moadian.test'))
        ->assertSessionHas('success');

    expect($profile->refresh()->connection_verified_at)->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync/GET_TOKEN')
        && ($request->data()['packet']['data']['username'] ?? null) === 'ABC123');
});

it('keeps access tokens isolated between taxpayer accounts', function () {
    config()->set('services.moadian.driver', 'real');
    config()->set('services.moadian.base_url', 'https://moadian.test/api/self-tsp');
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $fiscalId = $request->data()['packet']['data']['username'];

        return Http::response([
            'result' => [
                'data' => [
                    'token' => "token-{$fiscalId}",
                    'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                ],
            ],
        ]);
    });

    $firstUser = User::factory()->create();
    TaxpayerProfile::factory()->for($firstUser)->create([
        'fiscal_id' => 'ABC123',
        'economic_code' => '411111111111',
        'private_key' => $this->privateKey,
    ]);
    $secondUser = User::factory()->create();
    TaxpayerProfile::factory()->for($secondUser)->create([
        'fiscal_id' => 'DEF456',
        'economic_code' => '422222222222',
        'private_key' => $this->privateKey,
    ]);
    $clientFactory = app(MoadianClientFactory::class);

    expect($clientFactory->forUser($firstUser)->token())->toBe('token-ABC123')
        ->and($clientFactory->forUser($secondUser)->token())->toBe('token-DEF456');

    Http::assertSentCount(2);
});
