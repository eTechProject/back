<?php

namespace App\Service;

use App\DTO\SecuredZone\CreateSecuredZoneDTO;
use App\DTO\SecuredZone\SecuredZoneDTO;
use App\Entity\SecuredZones;
use App\Repository\SecuredZonesRepository;
use CrEOF\Spatial\PHP\Types\Geometry\Polygon;
use CrEOF\Spatial\PHP\Types\Geometry\LineString;
use CrEOF\Spatial\PHP\Types\Geometry\Point;

class SecuredZoneService
{
    public function __construct(
        private SecuredZonesRepository $securedZonesRepository,
        private CryptService $cryptService
    ) {}

    public function createSecuredZoneFromRequest(CreateSecuredZoneDTO $request): SecuredZones
    {
        $securedZone = new SecuredZones();
        $securedZone->setName($request->name);

        // Convert coordinates array to Polygon
        $polygon = $this->createPolygonFromCoordinates($request->coordinates);
        $securedZone->setGeom($polygon);

        return $securedZone;
    }

    public function toDTO(SecuredZones $securedZone): SecuredZoneDTO
    {
        return new SecuredZoneDTO(
            encryptedId: $this->cryptService->encryptId($securedZone->getId()),
            name: $securedZone->getName(),
            coordinates: $this->extractCoordinatesFromPolygon($securedZone->getGeom()),
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

    private function createPolygonFromCoordinates(array $coordinates): Polygon
    {
        $points = [];
        foreach ($coordinates as $coordinate) {
            if (!isset($coordinate[0]) || !isset($coordinate[1])) {
                throw new \InvalidArgumentException('Coordonnées invalides. Format attendu: [[lat, lng], ...]');
            }
            $points[] = new Point($coordinate[0], $coordinate[1]);
        }

        // Ensure the polygon is closed (first point = last point)
        if (count($points) > 0 && !$this->arePointsEqual($points[0], $points[count($points) - 1])) {
            $points[] = $points[0];
        }

        if (count($points) < 4) {
            throw new \InvalidArgumentException('Un polygone doit avoir au moins 3 points distincts (4 points avec la fermeture)');
        }

        $lineString = new LineString($points);
        return new Polygon([$lineString]);
    }

    private function extractCoordinatesFromPolygon(Polygon $polygon): array
    {
        $coordinates = [];
        $rings = $polygon->getRings();
        
        if (count($rings) > 0) {
            $points = $rings[0]->getPoints();
            foreach ($points as $point) {
                $coordinates[] = [$point->getX(), $point->getY()];
            }
        }

        return $coordinates;
    }

    private function arePointsEqual(Point $point1, Point $point2): bool
    {
        return $point1->getX() === $point2->getX() && $point1->getY() === $point2->getY();
    }
}
