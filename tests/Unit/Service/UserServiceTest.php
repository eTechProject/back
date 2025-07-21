<?php

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\UserService;
use App\Repository\UserRepository;
use App\Service\CryptService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Dto\User\RegisterUserDTO;
use App\Entity\User;
use App\Enum\UserRole;

class UserServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private $passwordHasher;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|CryptService
     */
    private $cryptService;
    private UserService $userService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->cryptService = $this->createMock(CryptService::class);
        $this->userService = new UserService(
            $this->passwordHasher,
            $this->userRepository,
            $this->cryptService
        );
    }

    public function testCreateUserFromRequestSuccess(): void
    {
        $dto = new RegisterUserDTO();
        $dto->name = 'John Doe';
        $dto->email = 'john@example.com';
        $dto->phone = '1234567890';
        $dto->role = 'admin';
        $dto->password = 'password';

        $this->userRepository->method('findOneByEmail')->willReturn(null);
        $this->passwordHasher
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $user = $this->userService->createUserFromRequest($dto);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('John Doe', $user->getName());
        $this->assertSame('john@example.com', $user->getEmail());
        $this->assertSame('1234567890', $user->getPhone());
        $this->assertSame(UserRole::ADMIN, $user->getRole());
        $this->assertSame('hashed_password', $user->getPassword());
    }

    public function testCreateUserFromRequestThrowsIfEmailExists(): void
    {
        $dto = new RegisterUserDTO();
        $dto->email = 'existing@example.com';

        $this->userRepository->method('findOneByEmail')->willReturn(new User());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already exists');

        $this->userService->createUserFromRequest($dto);
    }

    public function testToDTO(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn('john@example.com');
        $user->method('getName')->willReturn('John Doe');
        $user->method('getRole')->willReturn(UserRole::CLIENT);

        $this->cryptService->expects($this->once())
            ->method('encryptId')
            ->with(1)
            ->willReturn('encrypted_id');

        $dto = $this->userService->toDTO($user);

        $this->assertSame('encrypted_id', $dto->encryptedId);
        $this->assertSame('john@example.com', $dto->email);
        $this->assertSame('John Doe', $dto->name);
        $this->assertSame(UserRole::CLIENT, $dto->role);
    }
}
