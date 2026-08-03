<?php

use App\Services\Moadian\MoadianCrypto;
use phpseclib3\Crypt\RSA;

it('signs data with an RSA private key', function () {
    $key = RSA::createKey(2048);
    $privateKey = $key->toString('PKCS8');
    $publicKey = $key->getPublicKey()->toString('PKCS8');

    $signature = (new MoadianCrypto)->sign('signed-content', $privateKey);

    expect(openssl_verify('signed-content', base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256))->toBe(1);
});

it('encrypts invoice payload with AES GCM and RSA OAEP SHA256', function () {
    $key = RSA::createKey(2048);
    $privateKey = $key->toString('PKCS8');
    $publicKey = $key->getPublicKey()->toString('PKCS8');
    $payload = ['header' => ['taxid' => 'ABC123'], 'body' => [['fee' => 1000]]];

    $encrypted = (new MoadianCrypto)->encryptPayload($payload, $publicKey, 'key-id');
    $rsa = RSA::loadPrivateKey($privateKey)
        ->withPadding(RSA::ENCRYPTION_OAEP)
        ->withHash('sha256')
        ->withMGFHash('sha256');
    $aesKey = hex2bin($rsa->decrypt(base64_decode($encrypted['symmetricKey'])));
    $encryptedBytes = base64_decode($encrypted['data']);
    $xorPayload = openssl_decrypt(
        substr($encryptedBytes, 0, -16),
        'aes-256-gcm',
        $aesKey,
        OPENSSL_RAW_DATA,
        hex2bin($encrypted['iv']),
        substr($encryptedBytes, -16),
    );
    $json = '';

    for ($index = 0, $length = strlen($xorPayload); $index < $length; $index++) {
        $json .= $xorPayload[$index] ^ $aesKey[$index % strlen($aesKey)];
    }

    expect($encrypted)
        ->toHaveKeys(['data', 'encryptionKeyId', 'symmetricKey', 'iv'])
        ->and($encrypted['encryptionKeyId'])->toBe('key-id')
        ->and(json_decode($json, true))->toBe($payload);
});
