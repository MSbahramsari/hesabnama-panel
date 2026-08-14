<?php

namespace App\Services\Moadian;

use App\Models\TaxpayerProfile;
use App\Models\User;

class MoadianClientFactory
{
    public function __construct(
        private MoadianNormalizer $normalizer,
        private MoadianCrypto $crypto,
    ) {}

    public function forUser(User $user): MoadianClient
    {
        $user->loadMissing('taxpayerProfile');

        return $this->forProfile($user->taxpayerProfile);
    }

    public function forProfile(?TaxpayerProfile $taxpayerProfile): MoadianClient
    {
        return new MoadianClient(
            $this->configuration($taxpayerProfile),
            $this->normalizer,
            $this->crypto,
        );
    }

    public function publicClient(): MoadianClient
    {
        return $this->forProfile(null);
    }

    public function configuration(?TaxpayerProfile $taxpayerProfile): MoadianConfiguration
    {
        return new MoadianConfiguration($taxpayerProfile);
    }

    public function configurationForUser(User $user): MoadianConfiguration
    {
        $user->loadMissing('taxpayerProfile');

        return $this->configuration($user->taxpayerProfile);
    }
}
