<?php

namespace App\Service;

use App\Entity\Agents;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\EntityType;
use App\DTO\Agent\RegisterAgentDTO;
use App\DTO\Agent\AgentProfileDTO;
use App\DTO\Agent\AgentResponseDTO;
use App\DTO\User\UserDTO;
use App\Repository\UserRepository;
use App\Repository\AgentsRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\CryptService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AgentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private AgentsRepository $agentsRepository,
        private CryptService $cryptService,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function createAgent(RegisterAgentDTO $dto): Agents
    {
        $password = $dto->password ?? $this->generateRandomPassword();

        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRole(UserRole::AGENT);
        $user->setName($dto->name);


        $this->em->persist($user);

        $agent = new Agents();
        $agent->setSexe($dto->getEnumSexe());

        if (property_exists($dto, 'firstName')) {
            $agent->setFirstName($dto->firstName);
        }
        if (property_exists($dto, 'lastName')) {
            $agent->setLastName($dto->lastName);
        }
        if (property_exists($dto, 'address')) {
            $agent->setAddress($dto->address);
        }
        if (property_exists($dto, 'profile')) {
            $agent->setProfilePictureUrl($dto->profile);
        }

        $agent->setUser($user);

        $this->em->persist($agent);
        $this->em->flush();

        $this->exportCredentialsToCsv($dto->email, $password);

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

    private function generateRandomPassword(int $length = 12): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    private function exportCredentialsToCsv(string $email, string $password): void
    {
        $dir = __DIR__ . '/../../var/secure';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $filePath = $dir . '/credentials.csv';
        $isNewFile = !file_exists($filePath);
        $file = fopen($filePath, 'a');

        if ($isNewFile) {
            fputcsv($file, ['Email', 'Mot de passe temporaire', 'Date de création']);
        }

        fputcsv($file, [$email, $password, (new \DateTime())->format('Y-m-d H:i:s')]);

        fclose($file);
    }
}
