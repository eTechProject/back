<?php
namespace App\Service;

use App\Enum\EntityType;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CryptService
{
    private string $baseSecret;

    public function __construct(string $appSecret)
    {
        $this->baseSecret = $appSecret; 
    }

    /**
     * Generate entity-specific key by combining base secret with entity name
     */
    private function getEntityKey(string $entityType): string
    {
        // Use HMAC-SHA256 to derive a proper entity-specific key
        return hash('sha256', $this->baseSecret . '_entity_' . $entityType);
    }

    /**
     * Encrypts an ID using AES-256-CBC with entity-specific key
     * @param int|string $id The ID to encrypt
     * @param string $entityType The entity type for key derivation
     * @return string The encrypted ID
     */
    public function encryptId(int|string $id, string $entityType): string
    {
        $key = $this->getEntityKey($entityType);
        $iv = substr($key, 0, 16);
        $encrypted = openssl_encrypt((string)$id, 'aes-256-cbc', $key, 0, $iv);
        // Use URL-safe base64 encoding but don't truncate, which was causing issues
        return str_replace(['+', '/', '='], ['-', '_', ''], $encrypted);
    }

    /**
     * Decrypts an encrypted ID 
     * @param string $encryptedId The encrypted ID to decrypt
     * @param string $entityType The entity type for key derivation
     * @return int The decrypted ID as an integer
     * @throws \InvalidArgumentException If the ID cannot be decrypted or is invalid
     */
    public function decryptId(string $encryptedId, string $entityType): int
    {
        $key = $this->getEntityKey($entityType);
        $iv = substr($key, 0, 16);
        // Convert URL-safe base64 back to standard base64
        $normalizedId = str_replace(['-', '_'], ['+', '/'], $encryptedId);
        
        $decrypted = openssl_decrypt($normalizedId, 'aes-256-cbc', $key, 0, $iv);
        
        if ($decrypted !== false && is_numeric($decrypted)) {
            return (int)$decrypted;
        }
        
        throw new \InvalidArgumentException('ID déchiffré invalide ou corrompu.');
    }
}