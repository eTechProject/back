<?php

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\CryptService;

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
        $result = $this->cryptService->encryptId(123);
        
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test that encrypting different IDs produces different results
     */
    public function testEncryptIdProducesDifferentResults(): void
    {
        $result1 = $this->cryptService->encryptId(123);
        $result2 = $this->cryptService->encryptId(456);
        
        $this->assertNotEquals($result1, $result2);
    }

    /**
     * Test that encrypting the same ID twice produces the same result
     */
    public function testEncryptIdConsistency(): void
    {
        $result1 = $this->cryptService->encryptId(123);
        $result2 = $this->cryptService->encryptId(123);
        
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test that decrypting an encrypted ID returns the original ID
     */
    public function testEncryptDecryptRoundtrip(): void
    {
        $originalId = 123;
        $encrypted = $this->cryptService->encryptId($originalId);
        $decrypted = $this->cryptService->decryptId($encrypted);
        
        $this->assertEquals($originalId, $decrypted);
    }

    /**
     * Test decryption with string ID input (should convert to int)
     */
    public function testEncryptDecryptWithStringId(): void
    {
        $originalId = "456";
        $encrypted = $this->cryptService->encryptId($originalId);
        $decrypted = $this->cryptService->decryptId($encrypted);
        
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
        $this->cryptService->decryptId('invalid-encrypted-id');
    }

    /**
     * Test that we can encrypt and decrypt large IDs
     */
    public function testLargeIdValues(): void
    {
        $largeId = PHP_INT_MAX - 100;
        $encrypted = $this->cryptService->encryptId($largeId);
        $decrypted = $this->cryptService->decryptId($encrypted);
        
        $this->assertEquals($largeId, $decrypted);
    }
    
    /**
     * Test URL-safe character replacement in encrypted output
     */
    public function testUrlSafeCharacterReplacement(): void
    {
        $encrypted = $this->cryptService->encryptId(12345);
        
        // Check that the encrypted string doesn't contain unsafe URL characters
        $this->assertStringNotContainsString('+', $encrypted);
        $this->assertStringNotContainsString('/', $encrypted);
        $this->assertStringNotContainsString('=', $encrypted);
    }
}
