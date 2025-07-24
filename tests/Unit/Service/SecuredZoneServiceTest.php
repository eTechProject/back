<?php

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\SecuredZoneService;
use App\Service\CryptService;
use App\Repository\SecuredZonesRepository;
use App\Entity\SecuredZones;
use App\DTO\SecuredZone\CreateSecuredZoneDTO;
use Doctrine\DBAL\Connection;
use ReflectionClass;
use ReflectionMethod;

class SecuredZoneServiceTest extends TestCase
{
    private SecuredZoneService $securedZoneService;
    private SecuredZonesRepository $securedZonesRepository;
    private CryptService $cryptService;
    private Connection $connection;

    protected function setUp(): void
    {
        // Mock the dependencies
        $this->securedZonesRepository = $this->createMock(SecuredZonesRepository::class);
        $this->cryptService = $this->createMock(CryptService::class);
        $this->connection = $this->createMock(Connection::class);

        $this->securedZoneService = new SecuredZoneService(
            $this->securedZonesRepository,
            $this->cryptService,
            $this->connection
        );
    }

    /**
     * Helper method to access private methods for testing
     */
    private function invokePrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($this->securedZoneService);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->securedZoneService, $parameters);
    }

    /**
     * Test creating a WKT polygon from valid coordinates
     */
    public function testCreatePolygonWKTFromValidCoordinates(): void
    {
        $coordinates = [
            [2.3522, 48.8566], // Paris coordinates (lng, lat)
            [2.3530, 48.8570],
            [2.3540, 48.8560],
            [2.3520, 48.8550]
        ];

        $wkt = $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$coordinates]);

        // Check that WKT string is properly formatted
        $this->assertStringStartsWith('POLYGON((', $wkt);
        $this->assertStringEndsWith('))', $wkt);
        
        // Check that the polygon is closed (first and last points are the same)
        $this->assertStringContainsString('2.352200 48.856600,2.353000 48.857000,2.354000 48.856000,2.352000 48.855000,2.352200 48.856600', $wkt);
    }

    /**
     * Test creating WKT polygon with minimum required points (3 distinct points)
     */
    public function testCreatePolygonWKTWithMinimumPoints(): void
    {
        $coordinates = [
            [0.0, 0.0],
            [1.0, 0.0],
            [0.5, 1.0]
        ];

        $wkt = $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$coordinates]);

        $expected = 'POLYGON((0.000000 0.000000,1.000000 0.000000,0.500000 1.000000,0.000000 0.000000))';
        $this->assertEquals($expected, $wkt);
    }

    /**
     * Test that polygon is automatically closed if not already closed
     */
    public function testCreatePolygonWKTAutoCloses(): void
    {
        $coordinates = [
            [0.0, 0.0],
            [1.0, 0.0],
            [1.0, 1.0],
            [0.0, 1.0]
            // Note: not manually closed
        ];

        $wkt = $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$coordinates]);

        // Should automatically add the closing point
        $expected = 'POLYGON((0.000000 0.000000,1.000000 0.000000,1.000000 1.000000,0.000000 1.000000,0.000000 0.000000))';
        $this->assertEquals($expected, $wkt);
    }

    /**
     * Test that already closed polygon doesn't get double-closed
     */
    public function testCreatePolygonWKTAlreadyClosed(): void
    {
        $coordinates = [
            [0.0, 0.0],
            [1.0, 0.0],
            [1.0, 1.0],
            [0.0, 1.0],
            [0.0, 0.0] // Already closed
        ];

        $wkt = $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$coordinates]);

        // Should not add another closing point
        $expected = 'POLYGON((0.000000 0.000000,1.000000 0.000000,1.000000 1.000000,0.000000 1.000000,0.000000 0.000000))';
        $this->assertEquals($expected, $wkt);
    }

    /**
     * Test error when coordinates array has insufficient points
     */
    public function testCreatePolygonWKTWithInsufficientPoints(): void
    {
        $coordinates = [
            [0.0, 0.0],
            [1.0, 0.0]
            // Only 2 points - insufficient for a polygon
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un polygone doit avoir au moins 3 points distincts (4 points avec la fermeture)');

        $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$coordinates]);
    }

    /**
     * Test error when coordinate format is invalid
     */
    public function testCreatePolygonWKTWithInvalidCoordinateFormat(): void
    {
        $coordinates = [
            [0.0], // Missing latitude
            [1.0, 0.0],
            [0.5, 1.0]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coordonnées invalides. Format attendu: [[lng, lat], ...]');

        $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$coordinates]);
    }

    /**
     * Test extracting coordinates from valid WKT polygon string
     */
    public function testExtractCoordinatesFromValidPolygonWKT(): void
    {
        $wkt = 'POLYGON((2.352200 48.856600,2.353000 48.857000,2.354000 48.856000,2.352000 48.855000,2.352200 48.856600))';
        
        $coordinates = $this->invokePrivateMethod('extractCoordinatesFromPolygonWKT', [$wkt]);

        $expected = [
            [2.352200, 48.856600],
            [2.353000, 48.857000],
            [2.354000, 48.856000],
            [2.352000, 48.855000]
            // Note: closing point should be removed
        ];

        $this->assertEquals($expected, $coordinates);
    }

    /**
     * Test extracting coordinates from WKT with extra spaces
     */
    public function testExtractCoordinatesFromWKTWithExtraSpaces(): void
    {
        $wkt = 'POLYGON((  0.000000   0.000000  ,  1.000000   0.000000  ,  1.000000   1.000000  ,  0.000000   1.000000  ,  0.000000   0.000000  ))';
        
        $coordinates = $this->invokePrivateMethod('extractCoordinatesFromPolygonWKT', [$wkt]);

        $expected = [
            [0.0, 0.0],
            [1.0, 0.0],
            [1.0, 1.0],
            [0.0, 1.0]
        ];

        $this->assertEquals($expected, $coordinates);
    }

    /**
     * Test extracting coordinates from case-insensitive WKT
     */
    public function testExtractCoordinatesFromCaseInsensitiveWKT(): void
    {
        $wkt = 'polygon((0.0 0.0,1.0 0.0,0.5 1.0,0.0 0.0))';
        
        $coordinates = $this->invokePrivateMethod('extractCoordinatesFromPolygonWKT', [$wkt]);

        $expected = [
            [0.0, 0.0],
            [1.0, 0.0],
            [0.5, 1.0]
        ];

        $this->assertEquals($expected, $coordinates);
    }

    /**
     * Test extracting coordinates from empty WKT string
     */
    public function testExtractCoordinatesFromEmptyWKT(): void
    {
        $coordinates = $this->invokePrivateMethod('extractCoordinatesFromPolygonWKT', ['']);

        $this->assertEquals([], $coordinates);
    }

    /**
     * Test extracting coordinates from invalid WKT format
     */
    public function testExtractCoordinatesFromInvalidWKT(): void
    {
        $coordinates = $this->invokePrivateMethod('extractCoordinatesFromPolygonWKT', ['INVALID_WKT_FORMAT']);

        $this->assertEquals([], $coordinates);
    }

    /**
     * Test round-trip: coordinates -> WKT -> coordinates
     */
    public function testRoundTripCoordinatesConversion(): void
    {
        $originalCoordinates = [
            [2.3522, 48.8566],
            [2.3530, 48.8570],
            [2.3540, 48.8560],
            [2.3520, 48.8550]
        ];

        // Create WKT from coordinates
        $wkt = $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$originalCoordinates]);
        
        // Extract coordinates back from WKT
        $extractedCoordinates = $this->invokePrivateMethod('extractCoordinatesFromPolygonWKT', [$wkt]);

        // Compare with original (allowing for small floating point differences)
        $this->assertCount(count($originalCoordinates), $extractedCoordinates);
        
        for ($i = 0; $i < count($originalCoordinates); $i++) {
            $this->assertEqualsWithDelta($originalCoordinates[$i][0], $extractedCoordinates[$i][0], 0.000001);
            $this->assertEqualsWithDelta($originalCoordinates[$i][1], $extractedCoordinates[$i][1], 0.000001);
        }
    }

    /**
     * Test precision handling in coordinate conversion
     */
    public function testCoordinatePrecisionHandling(): void
    {
        $coordinates = [
            [2.3522219999, 48.8566669999],
            [2.3530000001, 48.8570000001],
            [2.3540000000, 48.8560000000]
        ];

        $wkt = $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$coordinates]);

        // Check that coordinates are formatted to 6 decimal places
        $this->assertStringContainsString('2.352222 48.856667', $wkt);
        $this->assertStringContainsString('2.353000 48.857000', $wkt);
        $this->assertStringContainsString('2.354000 48.856000', $wkt);
    }

    /**
     * Test that WKT polygon format is valid for PostGIS
     */
    public function testWKTFormatValidForPostGIS(): void
    {
        $coordinates = [
            [-74.0059, 40.7128], // New York coordinates
            [-74.0000, 40.7200],
            [-74.0100, 40.7200]
        ];

        $wkt = $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$coordinates]);

        // Should start with POLYGON((
        $this->assertStringStartsWith('POLYGON((', $wkt);
        // Should end with ))
        $this->assertStringEndsWith('))', $wkt);
        // Should contain longitude before latitude (X Y format)
        $this->assertStringContainsString('-74.005900 40.712800', $wkt);
        // Should be properly closed
        $this->assertStringContainsString('-74.005900 40.712800,-74.000000 40.720000,-74.010000 40.720000,-74.005900 40.712800', $wkt);
    }

    /**
     * Test extraction handles WKT with different coordinate orders
     */
    public function testExtractCoordinatesHandlesDifferentOrder(): void
    {
        // Test with coordinates in different order within the WKT
        $wkt = 'POLYGON((1.0 2.0,3.0 4.0,5.0 6.0,1.0 2.0))';
        
        $coordinates = $this->invokePrivateMethod('extractCoordinatesFromPolygonWKT', [$wkt]);

        $expected = [
            [1.0, 2.0],
            [3.0, 4.0],
            [5.0, 6.0]
        ];

        $this->assertEquals($expected, $coordinates);
    }

    /**
     * Test creating polygon with negative coordinates
     */
    public function testCreatePolygonWKTWithNegativeCoordinates(): void
    {
        $coordinates = [
            [-1.0, -1.0],
            [1.0, -1.0],
            [0.0, 1.0]
        ];

        $wkt = $this->invokePrivateMethod('createPolygonWKTFromCoordinates', [$coordinates]);

        $expected = 'POLYGON((-1.000000 -1.000000,1.000000 -1.000000,0.000000 1.000000,-1.000000 -1.000000))';
        $this->assertEquals($expected, $wkt);
    }

    /**
     * Test extracting coordinates with negative values
     */
    public function testExtractCoordinatesWithNegativeValues(): void
    {
        $wkt = 'POLYGON((-1.5 -2.5,1.5 -2.5,0.0 2.5,-1.5 -2.5))';
        
        $coordinates = $this->invokePrivateMethod('extractCoordinatesFromPolygonWKT', [$wkt]);

        $expected = [
            [-1.5, -2.5],
            [1.5, -2.5],
            [0.0, 2.5]
        ];

        $this->assertEquals($expected, $coordinates);
    }
}