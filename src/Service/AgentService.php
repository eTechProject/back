<?php

namespace App\Service;

use App\Entity\Agents;
use App\Enum\UserRole;
use App\Enum\EntityType;
use App\DTO\Agent\RegisterAgentDTO;
use App\DTO\Agent\AgentProfileDTO;
use App\DTO\Agent\AgentResponseDTO; // Ajouté
use App\DTO\User\UserDTO;           // Ajouté
use App\Repository\UserRepository;
use App\Repository\AgentsRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\CryptService;       // Ajouté

class AgentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private AgentsRepository $agentsRepository,
        private CryptService $cryptService // Ajouté
    ) {}

    public function createAgent(RegisterAgentDTO $dto): Agents
    {
        $user = $this->userRepository->find($dto->userId);
        if (!$user) {
            throw new \Exception("Utilisateur introuvable");
        }

        if ($user->getRole() !== UserRole::AGENT) {
            $user->setRole(UserRole::AGENT);
            $this->em->persist($user);
        }

        $agent = new Agents();
        $agent->setSexe($dto->getEnumSexe());
        $agent->setUser($user);

        $this->em->persist($agent);
        $this->em->flush();

        return $agent;
    }

    public function updateAgent(int $id, AgentProfileDTO $dto): ?Agents
    {
        $agent = $this->agentsRepository->find($id);
        if (!$agent) return null;

        $agent->setAddress($dto->address);
        $agent->setProfilePictureUrl($dto->profilePictureUrl);

        $this->em->flush();
        return $agent;
    }

    public function getAllAgents(): array
    {
        return $this->agentsRepository->findAll();
    }

    public function getAgent(int $id): ?Agents
    {
        return $this->agentsRepository->find($id);
    }

    public function deleteAgent(int $id): bool
    {
        $agent = $this->agentsRepository->find($id);
        if (!$agent) return false;

        $user = $agent->getUser();

        $this->em->remove($agent);

        if ($user) {
            $this->em->remove($user);
        }

        $this->em->flush();
        return true;
    }

    // Retourne le profil d'un agent sous forme de DTO sécurisé
    public function getAgentProfile(Agents $agent): AgentResponseDTO
    {
        $user = $agent->getUser();

        $userDto = new UserDTO(
            $this->cryptService->encryptId($user->getId(), EntityType::USER->value),
            $user->getEmail(),
            $user->getName(),
            $user->getRole()
        );

        return new AgentResponseDTO(
            $this->cryptService->encryptId($agent->getId(), EntityType::AGENT->value),
            $agent->getAddress(),
            $agent->getSexe(),
            $agent->getProfilePictureUrl(),
            $userDto
        );
    }
}
