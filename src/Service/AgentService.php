<?php

namespace App\Service;

use App\Entity\Agents;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\EntityType;
use App\Enum\Genre;
use App\DTO\Agent\Request\RegisterAgentDTO;
use App\DTO\Agent\Request\UpdateAgentDTO;
use App\DTO\Agent\Response\AgentResponseDTO;
use App\DTO\User\Internal\UserDTO;
use App\Repository\UserRepository;
use App\Repository\AgentsRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\CryptService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Twig\Environment;

class AgentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private AgentsRepository $agentsRepository,
        private CryptService $cryptService,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private Environment $twig
    ) {}

    /**
     * Valide l'accès d'un utilisateur à un agent spécifique
     * Décrypte l'ID et vérifie les permissions
     */
    public function validateAgentAccess(string $encryptedId, User $currentUser): ?Agents
    {
        // Décrypter l'ID de l'agent
        $agentId = $this->cryptService->decryptId($encryptedId, EntityType::AGENT->value);
        if (!$agentId) {
            return null;
        }

        // Récupérer l'agent
        $agent = $this->getAgent($agentId);
        if (!$agent) {
            return null;
        }

        // Vérifier que l'utilisateur connecté peut accéder à cet agent
        if ($agent->getUser() && $agent->getUser()->getId() !== $currentUser->getId()) {
            return null;
        }

        return $agent;
    }

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
        $agent->setAddress($dto->address);

        $agent->setProfilePictureUrl($dto->getProfilePictureUrl());

        $agent->setUser($user);

        $this->em->persist($agent);
        $this->em->flush();




        // Envoi du mot de passe par email avec template Twig
        $civilite = ($dto->sexe === 'F') ? 'Mme' : 'Mr';
        $email = (new TemplatedEmail())
            ->from('no-reply@guard-info.com')
            ->to($dto->email)
            ->subject('Votre compte agent Guard Security Service')
            ->htmlTemplate('emails/agent_password.html.twig')
            ->context([
                'civilite' => $civilite,
                'password' => $password,
            ]);
        $this->mailer->send($email);

        return $agent;
    }

    public function updateAgent(int $id, UpdateAgentDTO $dto): ?Agents
    {
        $agent = $this->agentsRepository->find($id);
        if (!$agent) return null;

        // Mise à jour des propriétés de l'agent
        if (property_exists($dto, 'address') && $dto->address !== null) {
            $agent->setAddress($dto->address);
        }
        
        if (property_exists($dto, 'picture_url') && $dto->picture_url !== null) {
            $agent->setProfilePictureUrl($dto->picture_url);
        }
        
        if (property_exists($dto, 'sexe') && $dto->sexe !== null) {
            $agent->setSexe(Genre::from($dto->sexe));
        }
        
        // Récupérer l'utilisateur lié à l'agent pour mettre à jour ses informations
        $user = $agent->getUser();
        if ($user) {
            // Mise à jour du nom
            if (property_exists($dto, 'name') && $dto->name !== null) {
                $user->setName($dto->name);
            }
            
            // Mise à jour du téléphone
            if (property_exists($dto, 'phone') && $dto->phone !== null) {
                $user->setPhone($dto->phone);
            }
        }

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

    /**
     * Récupérer un agent par son utilisateur
     */
    public function getAgentByUser(User $user): ?Agents
    {
        return $this->agentsRepository->findOneBy(['user' => $user]);
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
            $user->getRole(),
            $user->getPhone()
        );

        return new AgentResponseDTO(
            $this->cryptService->encryptId($agent->getId(), EntityType::AGENT->value),
            $agent->getAddress(),
            $agent->getSexe(),
            $agent->getProfilePictureUrl(),
            $userDto
        );
    }
    
    /**
     * Get all agents who are available (no tasks or all tasks completed)
     * 
     * @return array<AgentResponseDTO>
     */
    public function getAvailableAgents(): array
    {
        $availableAgents = $this->agentsRepository->findAvailableAgents();
        $availableAgentDTOs = [];

        foreach ($availableAgents as $agent) {
            $availableAgentDTOs[] = $this->getAgentProfile($agent);
        }

        return $availableAgentDTOs;
    }
    
    public function searchAgents(?string $name, ?string $taskStatus = null): array
    {
        return $this->agentsRepository->searchAgents($name, $taskStatus);
    }

    public function getAgentsPaginated(int $page, int $limit): array 
    {
        $offset = ($page - 1) * $limit;

        $queryBuilder = $this->agentsRepository->createQueryBuilder('a')
            ->join('a.user', 'u')
            ->orderBy('u.createdAt', 'DESC');

        $query = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery();

        $agents = $query->getResult();

        // Compter total agents (pour pagination)
        $total = $this->agentsRepository->count([]);

        return [$agents, $total];
    }

    private function generateRandomPassword(int $length = 12): string
    {
        return bin2hex(random_bytes($length / 2));
    }


}
