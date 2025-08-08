<?php

namespace App\Service;

use App\DTO\User\Request\RegisterUserDTO;
use App\DTO\Client\Request\CreateClientDTO;
use App\DTO\User\Request\UpdateUserDTO;
use App\DTO\User\Request\UpdateUserProfileDTO;
use App\DTO\User\Request\ChangePasswordDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Enum\UserRole;
use App\Enum\EntityType;
use App\DTO\User\Internal\UserDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class UserService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
        private CryptService $cryptService,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {}

    public function createUserFromRequest(RegisterUserDTO $request): User
    {
        if ($this->userRepository->findOneByEmail($request->email)) {
            throw new \InvalidArgumentException('Email already exists');
        }

        $user = new User();
        $user->setName($request->name);
        $user->setEmail($request->email);
        $user->setPhone($request->phone);

        $role = match (strtolower($request->role)) {
            'admin' => UserRole::ADMIN,
            'agent' => UserRole::AGENT,
            'client' => UserRole::CLIENT,
            default => UserRole::CLIENT,
        };
        $user->setRole($role);

        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $request->password
        );
        $user->setPassword($hashedPassword);

        // Persist the user within the service
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Créer un client depuis une requête admin avec envoi d'email
     */
    public function createClientFromRequest(CreateClientDTO $request): User
    {
        if ($this->userRepository->findOneByEmail($request->email)) {
            throw new \InvalidArgumentException('Email already exists');
        }

        $password = $request->password ?? $this->generateRandomPassword();

        $user = new User();
        $user->setName($request->name);
        $user->setEmail($request->email);
        $user->setPhone($request->phone);
        $user->setRole(UserRole::CLIENT);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Envoi du mot de passe par email
        $email = (new TemplatedEmail())
            ->from('no-reply@guard-info.com')
            ->to($request->email)
            ->subject('Votre compte client Guard Security Service')
            ->htmlTemplate('emails/client_password.html.twig')
            ->context([
                'name' => $request->name,
                'password' => $password,
            ]);
        $this->mailer->send($email);

        return $user;
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function updateUser(int $id, UpdateUserDTO $dto): ?User
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return null;
        }

        if ($dto->name !== null) {
            $user->setName($dto->name);
        }

        if ($dto->email !== null) {
            // Vérifier que l'email n'est pas déjà utilisé par un autre utilisateur
            $existingUser = $this->userRepository->findOneByEmail($dto->email);
            if ($existingUser && $existingUser->getId() !== $id) {
                throw new \InvalidArgumentException('Email already exists');
            }
            $user->setEmail($dto->email);
        }

        if ($dto->phone !== null) {
            $user->setPhone($dto->phone);
        }

        if ($dto->password !== null) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
            $user->setPassword($hashedPassword);
        }

        $this->entityManager->flush();
        return $user;
    }

    /**
     * Supprimer un utilisateur
     */
    public function deleteUser(int $id): bool
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return false;
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
        return true;
    }

    /**
     * Récupérer des utilisateurs par rôle avec pagination
     */
    public function getUsersPaginatedByRole(UserRole $role, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', $role)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('u.createdAt', 'DESC');

        $users = $queryBuilder->getQuery()->getResult();
        $total = $this->userRepository->count(['role' => $role]);

        return [$users, $total];
    }

    /**
     * Rechercher des utilisateurs par rôle et nom
     */
    public function searchUsersByRole(UserRole $role, ?string $name): array
    {
        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', $role);

        if ($name) {
            $queryBuilder
                ->andWhere('u.name LIKE :name')
                ->setParameter('name', '%' . $name . '%');
        }

        return $queryBuilder
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer un utilisateur par ID
     */
    public function getUserById(int $id): ?User
    {
        return $this->userRepository->find($id);
    }

    /**
     * Générer un mot de passe aléatoire
     */
    private function generateRandomPassword(int $length = 12): string
    {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Mettre à jour le profil utilisateur depuis la nouvelle API
     */
    public function updateUserFromRequest(int $id, UpdateUserProfileDTO $request): User
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new \InvalidArgumentException('Utilisateur non trouvé');
        }

        if ($request->name !== null) {
            $user->setName($request->name);
        }

        if ($request->email !== null) {
            $user->setEmail($request->email);
        }

        if ($request->phone !== null) {
            $user->setPhone($request->phone);
        }

        $this->entityManager->flush();

        return $user;
    }
    
    /**
     * Changer le mot de passe utilisateur depuis la nouvelle API
     */
    public function updatePasswordFromRequest(int $id, ChangePasswordDTO $request): User
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new \InvalidArgumentException('Utilisateur non trouvé');
        }

        // Vérifier le mot de passe actuel
        if (!$this->passwordHasher->isPasswordValid($user, $request->current_password)) {
            throw new \InvalidArgumentException('Mot de passe actuel incorrect');
        }

        // Hasher le nouveau mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $request->new_password
        );
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Récupérer un utilisateur par ID chiffré
     */
    public function getUserByEncryptedId(string $encryptedId): User
    {
        $decryptedId = $this->cryptService->decryptId($encryptedId, EntityType::USER->value);
        $user = $this->userRepository->find($decryptedId);
        
        if (!$user) {
            throw new \InvalidArgumentException('Utilisateur non trouvé');
        }
        
        return $user;
    }
    public function toDTO(User $user): UserDTO
    {
        return new UserDTO(
            userId: $this->cryptService->encryptId((string)$user->getId(), EntityType::USER->value),
            email: $user->getEmail(),
            name: $user->getName(),
            role: $user->getRole(),
            phone: $user->getPhone()
        );
    }
}
