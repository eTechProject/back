<?php

namespace App\Service;

use App\Entity\AgentLocationsArchive;
use App\Entity\AgentLocationsRaw;
use App\Entity\Agents;
use App\Entity\Tasks;
use App\Repository\AgentLocationsArchiveRepository;
use App\Repository\AgentLocationsRawRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AgentLocationArchiveService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AgentLocationsRawRepository $agentLocationsRawRepository,
        private AgentLocationsArchiveRepository $agentLocationsArchiveRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Create archive from raw locations for a specific task
     */
    public function createTaskArchive(Agents $agent, Tasks $task): ?AgentLocationsArchive
    {
        try {
            // Get all raw locations for this agent and task ordered by recorded time
            $rawLocations = $this->getRawLocationsForTask($agent, $task);

            if (empty($rawLocations)) {
                $this->logger->warning('No raw locations found for archiving', [
                    'agent_id' => $agent->getId(),
                    'task_id' => $task->getId()
                ]);
                return null;
            }

            // Create archive entity
            $archive = $this->buildArchiveFromRawLocations($agent, $task, $rawLocations);

            // Persist archive
            $this->entityManager->persist($archive);
            $this->entityManager->flush();

            $this->logger->info('Task archive created successfully', [
                'agent_id' => $agent->getId(),
                'task_id' => $task->getId(),
                'archive_id' => $archive->getId(),
                'point_count' => $archive->getPointCount(),
                'path_length' => $archive->getPathLength()
            ]);

            return $archive;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create task archive', [
                'agent_id' => $agent->getId(),
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get raw locations for a specific task ordered by time
     */
    private function getRawLocationsForTask(Agents $agent, Tasks $task): array
    {
        return $this->agentLocationsRawRepository->createQueryBuilder('raw')
            ->where('raw.agent = :agent')
            ->andWhere('raw.task = :task')
            ->setParameter('agent', $agent)
            ->setParameter('task', $task)
            ->orderBy('raw.recordedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Build archive entity from raw locations
     */
    private function buildArchiveFromRawLocations(Agents $agent, Tasks $task, array $rawLocations): AgentLocationsArchive
    {
        $archive = new AgentLocationsArchive();
        $archive->setAgent($agent);
        $archive->setTask($task);
        $archive->setPointCount(count($rawLocations));

        // Set time bounds
        $firstLocation = reset($rawLocations);
        $lastLocation = end($rawLocations);
        $archive->setStartTime($firstLocation->getRecordedAt());
        $archive->setEndTime($lastLocation->getRecordedAt());

        // Create LineString geometry from raw locations
        $lineString = $this->createLineStringFromRawLocations($rawLocations);
        $archive->setGeom($lineString);

        // Calculate statistics
        $this->calculateArchiveStatistics($archive, $rawLocations);

        return $archive;
    }

    /**
     * Create LineString WKT from raw locations
     */
    private function createLineStringFromRawLocations(array $rawLocations): string
    {
        $coordinates = [];

        foreach ($rawLocations as $rawLocation) {
            $coords = $this->extractCoordinatesFromPoint($rawLocation->getGeom());
            $coordinates[] = sprintf('%.6f %.6f', $coords[0], $coords[1]);
        }

        // If only one point, create a minimal LineString (duplicate the point)
        if (count($coordinates) === 1) {
            $coordinates[] = $coordinates[0];
        }

        return sprintf('LINESTRING(%s)', implode(',', $coordinates));
    }

    /**
     * Extract longitude and latitude from Point WKT
     */
    private function extractCoordinatesFromPoint(string $pointWKT): array
    {
        if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/', $pointWKT, $matches)) {
            return [(float)$matches[1], (float)$matches[2]];
        }

        return [0.0, 0.0];
    }

    /**
     * Calculate statistics for the archive (path length, average speed)
     */
    private function calculateArchiveStatistics(AgentLocationsArchive $archive, array $rawLocations): void
    {
        if (count($rawLocations) < 2) {
            $archive->setPathLength(0.0);
            $archive->setAvgSpeed(null);
            return;
        }

        $totalDistance = 0.0;
        $totalTime = 0;
        $speedReadings = [];

        for ($i = 1; $i < count($rawLocations); $i++) {
            $prevLocation = $rawLocations[$i - 1];
            $currentLocation = $rawLocations[$i];

            // Calculate distance between consecutive points
            $distance = $this->calculateDistance(
                $this->extractCoordinatesFromPoint($prevLocation->getGeom()),
                $this->extractCoordinatesFromPoint($currentLocation->getGeom())
            );

            $totalDistance += $distance;

            // Calculate time difference
            $timeDiff = $currentLocation->getRecordedAt()->getTimestamp() - 
                       $prevLocation->getRecordedAt()->getTimestamp();
            
            $totalTime += $timeDiff;

            // Collect speed readings for average calculation
            if ($currentLocation->getSpeed() !== null) {
                $speedReadings[] = $currentLocation->getSpeed();
            }
        }

        // Set path length in meters
        $archive->setPathLength($totalDistance);

        // Calculate average speed
        if (!empty($speedReadings)) {
            $avgSpeed = array_sum($speedReadings) / count($speedReadings);
            $archive->setAvgSpeed($avgSpeed);
        } else {
            // Fallback: calculate speed from distance and time
            if ($totalTime > 0) {
                $avgSpeed = $totalDistance / $totalTime; // m/s
                $archive->setAvgSpeed($avgSpeed);
            }
        }
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in meters
     */
    private function calculateDistance(array $coord1, array $coord2): float
    {
        $earthRadius = 6371000; // Earth radius in meters

        $lat1Rad = deg2rad($coord1[1]);
        $lat2Rad = deg2rad($coord2[1]);
        $deltaLatRad = deg2rad($coord2[1] - $coord1[1]);
        $deltaLonRad = deg2rad($coord2[0] - $coord1[0]);

        $a = sin($deltaLatRad / 2) * sin($deltaLatRad / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLonRad / 2) * sin($deltaLonRad / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if archive already exists for a task
     */
    public function archiveExistsForTask(Tasks $task): bool
    {
        $existingArchive = $this->agentLocationsArchiveRepository->findOneBy([
            'task' => $task
        ]);

        return $existingArchive !== null;
    }
}
