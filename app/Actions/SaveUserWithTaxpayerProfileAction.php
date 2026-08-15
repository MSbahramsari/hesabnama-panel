<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Moadian\MoadianConfiguration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveUserWithTaxpayerProfileAction
{
    private const TAXPAYER_FIELDS = [
        'taxpayer_name',
        'taxpayer_type',
        'national_id',
        'economic_code',
        'fiscal_id',
        'branch_code',
        'private_key',
    ];

    /** @param array<string, mixed> $data */
    public function handle(array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($data, $user): User {
            $taxpayerData = Arr::only($data, self::TAXPAYER_FIELDS);
            $data = Arr::except($data, self::TAXPAYER_FIELDS);
            $user ??= new User;
            $user->fill($data);
            $user->save();

            $hasTaxpayerProfile = $user->taxpayerProfile()->exists();

            if ($user->role !== UserRole::Admin || $hasTaxpayerProfile || $this->hasTaxpayerCredentials($taxpayerData)) {
                $privateKeyFile = Arr::pull($taxpayerData, 'private_key');
                $profile = $user->taxpayerProfile()->firstOrNew();

                if ($privateKeyFile instanceof UploadedFile) {
                    $privateKey = file_get_contents($privateKeyFile->getRealPath());
                    $taxpayerData['private_key'] = $privateKey === false ? '' : $privateKey;
                }

                if (isset($taxpayerData['fiscal_id'])) {
                    $taxpayerData['fiscal_id'] = Str::upper(trim((string) $taxpayerData['fiscal_id']));
                }

                $profile->fill($taxpayerData);

                $credentialsChanged = $profile->isDirty(['fiscal_id', 'economic_code', 'private_key']);

                if ($credentialsChanged) {
                    $profile->connection_verified_at = null;
                }

                $profile->save();

                if ($credentialsChanged) {
                    Cache::forget((new MoadianConfiguration($profile))->tokenCacheKey());
                }
            }

            return $user->load('taxpayerProfile');
        });
    }

    /** @param array<string, mixed> $taxpayerData */
    private function hasTaxpayerCredentials(array $taxpayerData): bool
    {
        foreach (['taxpayer_name', 'national_id', 'economic_code', 'fiscal_id', 'private_key'] as $field) {
            if (filled($taxpayerData[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
