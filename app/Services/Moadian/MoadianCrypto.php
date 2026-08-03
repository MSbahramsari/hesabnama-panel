<?php

namespace App\Services\Moadian;

use App\Exceptions\MoadianApiException;
use JsonException;
use phpseclib3\Crypt\RSA;

class MoadianCrypto
{
    public function sign(string $text, string $privateKey): string
    {
        $signed = openssl_sign($text, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new MoadianApiException('امضای دیجیتال درخواست سامانه مودیان انجام نشد.');
        }

        return base64_encode($signature);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{data: string, encryptionKeyId: string, symmetricKey: string, iv: string}
     *
     * @throws JsonException
     */
    public function encryptPayload(array $payload, string $taxOrganizationPublicKey, string $keyId): array
    {
        $aesKey = random_bytes(32);
        $iv = random_bytes(16);
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        $xorPayload = $this->xorWithRepeatingKey($json, $aesKey);
        $encrypted = openssl_encrypt($xorPayload, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($encrypted === false) {
            throw new MoadianApiException('رمزنگاری اطلاعات صورتحساب انجام نشد.');
        }

        $publicKey = RSA::loadPublicKey($this->toPublicKeyPem($taxOrganizationPublicKey))
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256');

        return [
            'data' => base64_encode($encrypted.$tag),
            'encryptionKeyId' => $keyId,
            'symmetricKey' => base64_encode($publicKey->encrypt(bin2hex($aesKey))),
            'iv' => bin2hex($iv),
        ];
    }

    private function xorWithRepeatingKey(string $source, string $key): string
    {
        $result = '';
        $keyLength = strlen($key);

        for ($index = 0, $length = strlen($source); $index < $length; $index++) {
            $result .= $source[$index] ^ $key[$index % $keyLength];
        }

        return $result;
    }

    private function toPublicKeyPem(string $publicKey): string
    {
        if (str_contains($publicKey, 'BEGIN PUBLIC KEY')) {
            return $publicKey;
        }

        $encoded = preg_replace('/\s+/', '', $publicKey);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split((string) $encoded, 64, "\n")."-----END PUBLIC KEY-----\n";
    }
}
