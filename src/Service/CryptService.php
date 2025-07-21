<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CryptService
{
    private string $key;

    public function __construct(string $appSecret)
    {
        $this->key = base64_encode($appSecret); 
    }

    public function encryptId(int|string $id): string
    {
        $iv = substr($this->key, 0, 16);
        $encrypted = openssl_encrypt((string)$id, 'aes-256-cbc', $this->key, 0, $iv);
        return substr(str_replace(['+', '/', '='], '', base64_encode($encrypted)), 0, 20);
    }

    public function decryptId(string $encryptedId): int
    {
        $iv = substr($this->key, 0, 16);
        $decrypted = openssl_decrypt(base64_decode($encryptedId), 'aes-256-cbc', $this->key, 0, $iv);
        return (int) $decrypted;
    }
}