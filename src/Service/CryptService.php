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
        if (empty($id)) {
            throw new \InvalidArgumentException('L\'ID à chiffrer ne peut pas être vide');
        }
        
        $iv = substr($this->key, 0, 16);
        $encrypted = openssl_encrypt((string)$id, 'aes-256-cbc', $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Erreur lors du chiffrement de l\'ID');
        }
        
        // Base64 encode puis suppression des caractères spéciaux pour l'URL
        $urlSafeEncrypted = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($encrypted));
        return $urlSafeEncrypted;
    }

    public function decryptId(string $encryptedId): int
    {
        try {
            // Restaurer les caractères Base64 standard
            $base64Encrypted = str_replace(['-', '_'], ['+', '/'], $encryptedId);
            // Ajouter le padding si nécessaire
            $base64Encrypted = str_pad($base64Encrypted, strlen($base64Encrypted) + ((4 - strlen($base64Encrypted) % 4) % 4), '=');
            
            $iv = substr($this->key, 0, 16);
            $decrypted = openssl_decrypt(base64_decode($base64Encrypted), 'aes-256-cbc', $this->key, 0, $iv);
            
            if ($decrypted === false) {
                throw new \InvalidArgumentException('ID chiffré invalide');
            }
            
            return (int) $decrypted;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Impossible de déchiffrer l\'ID: ' . $e->getMessage());
        }
    }
}