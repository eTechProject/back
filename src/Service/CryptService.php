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

    /**
     * Encrypts an ID using AES-256-CBC
     * @param int|string $id The ID to encrypt
     * @return string The encrypted ID
     */
    public function encryptId(int|string $id): string
    {
        $iv = substr($this->key, 0, 16);
        $encrypted = openssl_encrypt((string)$id, 'aes-256-cbc', $this->key, 0, $iv);
        // Use URL-safe base64 encoding but don't truncate, which was causing issues
        return str_replace(['+', '/', '='], ['-', '_', ''], $encrypted);
    }

    /**
     * Decrypts an encrypted ID 
     * @param string $encryptedId The encrypted ID to decrypt
     * @return int The decrypted ID as an integer
     * @throws \InvalidArgumentException If the ID cannot be decrypted or is invalid
     */
    public function decryptId(string $encryptedId): int
    {
        $iv = substr($this->key, 0, 16);
        // Convert URL-safe base64 back to standard base64
        $normalizedId = str_replace(['-', '_'], ['+', '/'], $encryptedId);
        
        $decrypted = openssl_decrypt($normalizedId, 'aes-256-cbc', $this->key, 0, $iv);
        
        if ($decrypted !== false && is_numeric($decrypted)) {
            return (int)$decrypted;
        }
        
        throw new \InvalidArgumentException('ID déchiffré invalide ou corrompu.');
    }
}