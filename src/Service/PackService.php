<?php

namespace App\Service;

use App\DTO\Pack\Request\CreatePackDTO;
use App\DTO\Pack\Request\UpdatePackDTO;
use App\DTO\Pack\Response\PackDTO;
use App\Entity\Pack;
use App\Enum\EntityType;
use App\Repository\PackRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour la gestion des packs
 */
class PackService
{
    public function __construct(
        private PackRepository $packRepository,
        private EntityManagerInterface $entityManager,
        private CryptService $cryptService
    ) {}

    /**
     * Crée un pack à partir d'une requête DTO
     */
    public function createPackFromRequest(CreatePackDTO $request): Pack
    {
        $pack = new Pack();
        $pack->setNbAgents($request->nbAgents);
        $pack->setPrix($request->prix);
        $pack->setDescription($request->description);

        return $pack;
    }

    /**
     * Sauvegarde un pack avec transaction
     */
    public function createPackWithTransaction(CreatePackDTO $request): Pack
    {
        $this->entityManager->beginTransaction();

        try {
            $pack = $this->createPackFromRequest($request);
            
            $this->validatePackBusinessRules($pack);
            
            $this->entityManager->persist($pack);
            $this->entityManager->flush();

            $this->entityManager->commit();

            return $pack;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Convertit une entité Pack en DTO de réponse
     */
    public function toDTO(Pack $pack): PackDTO
    {
        return new PackDTO(
            id: $this->cryptService->encryptId($pack->getId(), EntityType::PACK->value),
            nbAgents: $pack->getNbAgents(),
            prix: $pack->getPrix(),
            dateCreation: $pack->getDateCreation(),
            description: $pack->getDescription()
        );
    }

    /**
     * Trouve un pack par ID
     */
    public function findById(int $id): ?Pack
    {
        return $this->packRepository->find($id);
    }

    /**
     * Trouve tous les packs
     */
    public function findAll(): array
    {
        return $this->packRepository->findAll();
    }

    /**
     * Trouve les packs avec pagination
     */
    public function getPacksPaginated(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        $packs = $this->packRepository->findBy([], ['dateCreation' => 'DESC'], $limit, $offset);
        $total = $this->packRepository->count([]);
        
        return [$packs, $total];
    }

    /**
     * Valide la logique métier d'un pack
     */
    public function validatePackBusinessRules(Pack $pack): void
    {
        if ($pack->getNbAgents() <= 0) {
            throw new \InvalidArgumentException('Un pack doit avoir au moins 1 agent');
        }

        if ((float)$pack->getPrix() <= 0) {
            throw new \InvalidArgumentException('Le prix du pack doit être positif');
        }
    }

    /**
     * Met à jour un pack à partir d'un DTO
     */
    public function updatePack(int $id, UpdatePackDTO $updateDTO): ?Pack
    {
        $pack = $this->findById($id);
        if (!$pack) {
            return null;
        }

        $this->entityManager->beginTransaction();

        try {
            if ($updateDTO->nb_agents !== null) {
                $pack->setNbAgents($updateDTO->nb_agents);
            }

            if ($updateDTO->prix !== null) {
                $pack->setPrix($updateDTO->prix);
            }

            if ($updateDTO->description !== null) {
                $pack->setDescription($updateDTO->description);
            }

            $this->validatePackBusinessRules($pack);

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $pack;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Supprime un pack par ID
     */
    public function deletePack(int $id): bool
    {
        $pack = $this->findById($id);
        if (!$pack) {
            return false;
        }

        $this->entityManager->beginTransaction();

        try {
            $this->entityManager->remove($pack);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return true;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
