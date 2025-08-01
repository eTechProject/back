<?php

namespace App\Service;

use App\DTO\SecuredZone\Request\CreateSecuredZoneDTO;
use App\DTO\SecuredZone\Response\SecuredZoneDTO;
use App\Entity\SecuredZones;
use App\Repository\SecuredZonesRepository;
use App\Enum\EntityType;
use Doctrine\DBAL\Connection;

class SecuredZoneService
{
    public function __construct(
        private SecuredZonesRepository $securedZonesRepository,
        private CryptService $cryptService,
        private Connection $connection
    ) {}

    public function createSecuredZoneFromRequest(CreateSecuredZoneDTO $request): SecuredZones
    {
        $securedZone = new SecuredZones();
        $securedZone->setName($request->name);

        // Convert coordinates array to WKT Polygon string
        $wkt = $this->createPolygonWKTFromCoordinates($request->coordinates);
        $securedZone->setGeom($wkt);

        return $securedZone;
    }

    public function toDTO(SecuredZones $securedZone): SecuredZoneDTO
    {
        return new SecuredZoneDTO(
            securedZoneId: $this->cryptService->encryptId($securedZone->getId(), EntityType::SECURED_ZONE->value),
            name: $securedZone->getName(),
            coordinates: $this->extractCoordinatesFromPolygonWKT($securedZone->getGeom()),
            createdAt: $securedZone->getCreatedAt()
        );
    }

    public function findById(int $id): ?SecuredZones
    {
        return $this->securedZonesRepository->find($id);
    }

    public function findAll(): array
    {
        return $this->securedZonesRepository->findAll();
    }
    
    /**
     * Debug method to inspect the raw WKT format stored in database
     */
    public function debugWktFormat(int $id): ?array
    {
        $zone = $this->findById($id);
        if (!$zone) {
            return null;
        }
        
        return [
            'id' => $zone->getId(),
            'name' => $zone->getName(),
            'raw_wkt' => $zone->getGeom(),
            'parsed_coordinates' => $this->extractCoordinatesFromPolygonWKT($zone->getGeom())
        ];
    }

    /**
     * Creates a WKT (Well-Known Text) polygon string from array of coordinates
     */
    private function createPolygonWKTFromCoordinates(array $coordinates): string
    {
        $points = [];
        foreach ($coordinates as $coordinate) {
            if (!isset($coordinate[0]) || !isset($coordinate[1])) {
                throw new \InvalidArgumentException('Coordonnées invalides. Format attendu: [[lng, lat], ...]');
            }
            // Ensure coordinates are properly formatted as floats
            // Note: WKT format for PostGIS expects longitude (X) then latitude (Y): "X Y"
            $points[] = sprintf("%.6f %.6f", (float)$coordinate[0], (float)$coordinate[1]);
        }

        // Ensure the polygon is closed (first point = last point)
        if (count($points) > 0 && $points[0] !== $points[count($points) - 1]) {
            $points[] = $points[0];
        }

        if (count($points) < 4) {
            throw new \InvalidArgumentException('Un polygone doit avoir au moins 3 points distincts (4 points avec la fermeture)');
        }

        // Create WKT polygon string: POLYGON((x1 y1, x2 y2, x3 y3, ...))
        return 'POLYGON((' . implode(',', $points) . '))';
    }

    /**
     * Extracts coordinates array from WKT polygon string
     */
    private function extractCoordinatesFromPolygonWKT(string $wkt): array
    {
        $coordinates = [];
        
        // Debug the actual WKT format we're receiving
        if (empty($wkt)) {
            return [];
        }
        
        // Use a more robust regex to extract coordinates from any WKT polygon format
        if (preg_match('/POLYGON\(\((.*?)\)\)/i', $wkt, $matches)) {
            $pointsStr = $matches[1];
            $pointStrings = explode(',', $pointsStr);
            
            foreach ($pointStrings as $pointString) {
                // Clean up any extra spaces
                $pointString = trim($pointString);
                $parts = preg_split('/\s+/', $pointString);
                
                if (count($parts) >= 2) {
                    // Make sure we're parsing as floats
                    $coordinates[] = [(float)$parts[0], (float)$parts[1]];
                }
            }
            
            // Remove the last point if it's the same as the first (closing point)
            $count = count($coordinates);
            if ($count > 1 && 
                $coordinates[0][0] === $coordinates[$count - 1][0] && 
                $coordinates[0][1] === $coordinates[$count - 1][1]) {
                array_pop($coordinates);
            }
        } else {
            // For debugging: log or add to response what the actual format was
            error_log("Failed to parse WKT string: " . $wkt);
        }
        
        return $coordinates;
    }
}
