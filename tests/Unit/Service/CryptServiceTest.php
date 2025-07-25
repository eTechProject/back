<?php

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\CryptService;
use App\Enum\EntityType;

class CryptServiceTest extends TestCase
{
    private CryptService $cryptService;
    private string $appSecret = 'test_app_secret_for_unit_testing';

    protected function setUp(): void
    {
        $this->cryptService = new CryptService($this->appSecret);
    }

    /**
     * Test that encrypting an ID produces a non-empty string
     */
    public function testEncryptIdReturnsString(): void
    {
        $result = $this->cryptService->encryptId(123, EntityType::USER->value);
        
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test that encrypting different IDs produces different results
     */
    public function testEncryptIdProducesDifferentResults(): void
    {
        $result1 = $this->cryptService->encryptId(123, EntityType::USER->value);
        $result2 = $this->cryptService->encryptId(456, EntityType::USER->value);
        
        $this->assertNotEquals($result1, $result2);
    }

    /**
     * Test that encrypting the same ID twice produces the same result
     */
    public function testEncryptIdConsistency(): void
    {
        $result1 = $this->cryptService->encryptId(123, EntityType::USER->value);
        $result2 = $this->cryptService->encryptId(123, EntityType::USER->value);
        
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test that decrypting an encrypted ID returns the original ID
     */
    public function testEncryptDecryptRoundtrip(): void
    {
        $originalId = 123;
        $encrypted = $this->cryptService->encryptId($originalId, EntityType::USER->value);
        $decrypted = $this->cryptService->decryptId($encrypted, EntityType::USER->value);
        
        $this->assertEquals($originalId, $decrypted);
    }

    /**
     * Test decryption with string ID input (should convert to int)
     */
    public function testEncryptDecryptWithStringId(): void
    {
        $originalId = "456";
        $encrypted = $this->cryptService->encryptId($originalId, EntityType::USER->value);
        $decrypted = $this->cryptService->decryptId($encrypted, EntityType::USER->value);
        
        $this->assertIsInt($decrypted);
        $this->assertEquals((int)$originalId, $decrypted);
    }

    /**
     * Test that decrypting an invalid ID throws an exception
     */
    public function testDecryptInvalidIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        // This is not a valid encrypted ID
        $this->cryptService->decryptId('invalid-encrypted-id', EntityType::USER->value);
    }

    /**
     * Test that we can encrypt and decrypt large IDs
     */
    public function testLargeIdValues(): void
    {
        $largeId = PHP_INT_MAX - 100;
        $encrypted = $this->cryptService->encryptId($largeId, EntityType::USER->value);
        $decrypted = $this->cryptService->decryptId($encrypted, EntityType::USER->value);
        
        $this->assertEquals($largeId, $decrypted);
    }
    
    /**
     * Test URL-safe character replacement in encrypted output
     */
    public function testUrlSafeCharacterReplacement(): void
    {
        $encrypted = $this->cryptService->encryptId(12345, EntityType::USER->value);
        
        // Check that the encrypted string doesn't contain unsafe URL characters
        $this->assertStringNotContainsString('+', $encrypted);
        $this->assertStringNotContainsString('/', $encrypted);
        $this->assertStringNotContainsString('=', $encrypted);
    }

    /**
     * Test that different entity types produce different encrypted results for the same ID
     */
    public function testDifferentEntityTypesProduceDifferentResults(): void
    {
        $id = 123;
        $userEncrypted = $this->cryptService->encryptId($id, EntityType::USER->value);
        $securedZoneEncrypted = $this->cryptService->encryptId($id, EntityType::SECURED_ZONE->value);
        $serviceOrderEncrypted = $this->cryptService->encryptId($id, EntityType::SERVICE_ORDER->value);
        
        // All results should be different
        $this->assertNotEquals($userEncrypted, $securedZoneEncrypted);
        $this->assertNotEquals($userEncrypted, $serviceOrderEncrypted);
        $this->assertNotEquals($securedZoneEncrypted, $serviceOrderEncrypted);
    }
}
