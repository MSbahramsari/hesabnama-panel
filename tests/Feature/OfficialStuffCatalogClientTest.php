<?php

use App\Services\OfficialStuffCatalogClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('retrieves one exact identifier from the official stuff portal', function () {
    Cache::forget('stuff-catalog:official-lookup:2330002582524');
    config()->set('services.stuff_catalog.portal_url', 'https://stuffid.test/portal-gateway');

    $path = tempnam(sys_get_temp_dir(), 'stuffid-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('result.csv', <<<'CSV'
ID,DescriptionOfID,Vat,Taxable,RunDate,ExpirationDate,CreateDate,LastEditDate,Type,PricingDescription
2330002582524,خدمات مشاوره حسابداری مالی/ارایه مشاوره خدمات مالی در زمینه خزانه داری/ شرکت ره نما حساب مهر,10,مشمول,1403-02-02,,1404-06-05,1403-02-02,شناسه اختصاصی خدمت,
CSV);
    $zip->close();
    $archive = file_get_contents($path);
    @unlink($path);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($archive) {
        if (str_ends_with($request->url(), '/auth/gs/graphql')) {
            return Http::response([
                'data' => [
                    'identity_create_session' => [
                        'data' => 'guest-session',
                        'statusCode' => 200,
                    ],
                ],
            ]);
        }

        if (str_ends_with($request->url(), '/StuffRate/gs/graphql')) {
            return Http::response([
                'data' => [
                    'GetInformationWithIDFilter' => [
                        'data' => ['fileName' => 'exact-result.zip'],
                        'statusCode' => 200,
                    ],
                ],
            ]);
        }

        return Http::response($archive, 200, ['Content-Type' => 'application/zip']);
    });

    $result = app(OfficialStuffCatalogClient::class)->lookup('2330002582524');

    expect($result)->toMatchArray([
        'item_id' => '2330002582524',
        'description' => 'خدمات مشاوره حسابداری مالی/ارایه مشاوره خدمات مالی در زمینه خزانه داری/ شرکت ره نما حساب مهر',
        'type' => 'شناسه اختصاصی خدمت',
        'vat' => 10.0,
        'taxable' => 'مشمول',
        'effective_date' => '1403-02-02',
    ]);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/StuffRate/gs/graphql')
        && $request->hasHeader('SessionID', 'guest-session')
        && ($request->data()['variables']['input']['code'] ?? null) === '2330002582524');
});
