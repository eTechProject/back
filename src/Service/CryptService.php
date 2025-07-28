<?php
namespace App\Service;

use App\Enum\EntityType;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CryptService
{
    private string $baseSecret;
    private string $key;

    public function __construct(string $appSecret)
    {
        $this->baseSecret = $appSecret; 
        $this->key = hash('sha256', $this->baseSecret, true);
    }

    /**
     * Generate entity-specific key by combining base secret with entity type
     */
    private function getEntityKey(string $entityType): string
    {
        // Use HMAC-SHA256 to derive a proper entity-specific key
        return hash('sha256', $this->baseSecret . '_entity_' . $entityType);
    }

    /**
     * Encrypts an ID using AES-256-CBC
     * @param string $id The ID to encrypt
     * @param string $entityType Optional entity type for key derivation
     * @return string The encrypted ID
     */
    public function encryptId(string $id, string $entityType = null): string
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('L\'ID à chiffrer ne peut pas être vide');
        }
        
        // Use either entity-specific key or default key
        $useKey = $entityType ? $this->getEntityKey($entityType) : $this->key;
        $iv = substr($useKey, 0, 16);
        
        $encrypted = openssl_encrypt((string)$id, 'aes-256-cbc', $useKey, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Erreur lors du chiffrement de l\'ID');
        }
        
        // Base64 encode puis suppression des caractères spéciaux pour l'URL
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($encrypted));
    }

    /**
     * Decrypts an encrypted ID 
     * @param string $encryptedId The encrypted ID to decrypt
     * @param string $entityType Optional entity type for key derivation
     * @return int The decrypted ID as an integer
     * @throws \InvalidArgumentException If the ID cannot be decrypted or is invalid
     */
    public function decryptId(string $encryptedId, string $entityType = null): int
    {
        try {
            // Use either entity-specific key or default key
            $useKey = $entityType ? $this->getEntityKey($entityType) : $this->key;
            $iv = substr($useKey, 0, 16);
            
            // Restaurer les caractères Base64 standard
            $base64Encrypted = str_replace(['-', '_'], ['+', '/'], $encryptedId);
            // Ajouter le padding si nécessaire
            $base64Encrypted = str_pad($base64Encrypted, strlen($base64Encrypted) + ((4 - strlen($base64Encrypted) % 4) % 4), '=');
            
            $decrypted = openssl_decrypt(base64_decode($base64Encrypted), 'aes-256-cbc', $useKey, 0, $iv);
            
            if ($decrypted === false) {
                throw new \InvalidArgumentException('ID chiffré invalide');
            }
            
            return (int) $decrypted;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Impossible de déchiffrer l\'ID: ' . $e->getMessage());
        }
    }
}
