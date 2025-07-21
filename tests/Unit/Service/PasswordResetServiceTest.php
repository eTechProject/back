<?php

namespace Tests\Unit\Service;

use App\Entity\User;
use App\Entity\ResetPasswordRequest;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class PasswordResetServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private ResetPasswordHelperInterface|MockObject $resetPasswordHelper;
    private MailerInterface|MockObject $mailer;
    private UserPasswordHasherInterface|MockObject $passwordHasher;
    private PasswordResetService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->resetPasswordHelper = $this->createMock(ResetPasswordHelperInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $this->service = new PasswordResetService(
            $this->entityManager,
            $this->resetPasswordHelper,
            $this->mailer,
            $this->passwordHasher
        );
    }

    public function testHandleResetRequestSendsEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('Test User');
        $user->setPassword('password');

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepo);

        // Create a real ResetPasswordToken since it's a final class
        $resetToken = new \SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken(
            'token123',
            new \DateTimeImmutable('+1 hour')
        );
        
        $this->resetPasswordHelper->expects($this->once())
            ->method('generateResetToken')
            ->with($user)
            ->willReturn($resetToken);

        $this->entityManager->expects($this->once())->method('flush');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(fn($email) => $email instanceof TemplatedEmail && $email->getTo()[0]->getAddress() === $user->getEmail()));

        $this->service->handleResetRequest('test@example.com');
    }

    public function testHandleResetRequestThrowsIfUserNotFound(): void
    {
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'missing@example.com'])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepo);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Utilisateur non trouvé.');

        $this->service->handleResetRequest('missing@example.com');
    }

    public function testHandleResetRequestThrowsOnResetPasswordException(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('Test User');
        $user->setPassword('password');

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $this->entityManager->method('getRepository')->willReturn($userRepo);

        $this->resetPasswordHelper->method('generateResetToken')
            ->willThrowException($this->createMock(ResetPasswordExceptionInterface::class));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Vous avez déjà demandé un mot de passe oublié. Veuillez vérifier votre boîte de réception pour le lien de réinitialisation.');

        $this->service->handleResetRequest('test@example.com');
    }

    /**
     * @dataProvider invalidTokenProvider
     */
    public function testResetPasswordReturnsErrorOnInvalidToken(\Exception $exception): void
    {
        $this->resetPasswordHelper->method('validateTokenAndFetchUser')->willThrowException($exception);

        $result = $this->service->resetPassword('invalid-token', 'newpass');

        $this->assertSame('Token invalide ou expiré.', $result);
    }

    public static function invalidTokenProvider(): array
    {
        return [
            [new \Exception()],
            // For static data provider, we use a simple class-string instead of mocks
            [new \Exception('Reset password exception')],
        ];
    }

    public function testResetPasswordReturnsErrorIfRequestUsed(): void
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail('test@example.com');
        $user->setPassword('password');

        $expiresAt = new \DateTime('+1 hour');
        $resetRequest = new ResetPasswordRequest($user, $expiresAt, 'selector', 'hashedToken');
        $resetRequest->setUsed(true);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $resetRequestRepo = $this->createMock(EntityRepository::class);
        $resetRequestRepo->method('findOneBy')->willReturn($resetRequest);

        $this->entityManager->method('getRepository')
            ->willReturnMap([
                [User::class, $userRepo],
                [ResetPasswordRequest::class, $resetRequestRepo],
            ]);

        $this->resetPasswordHelper->method('validateTokenAndFetchUser')->willReturn($user);

        $result = $this->service->resetPassword('valid-token', 'newpass');

        $this->assertSame('Le Lien de réinitialisation a déjà été utilisé.', $result);
    }

    public function testResetPasswordSucceeds(): void
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail('test@example.com');
        $user->setPassword('old_password');
        
        $expiresAt = new \DateTime('+1 hour');
        $resetRequest = new ResetPasswordRequest($user, $expiresAt, 'selector', 'hashedToken');
        $resetRequest->setUsed(false);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $resetRequestRepo = $this->createMock(EntityRepository::class);
        $resetRequestRepo->method('findOneBy')->willReturn($resetRequest);

        $this->entityManager->method('getRepository')
            ->willReturnMap([
                [User::class, $userRepo],
                [ResetPasswordRequest::class, $resetRequestRepo],
            ]);

        $this->resetPasswordHelper->method('validateTokenAndFetchUser')->willReturn($user);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'newpass')
            ->willReturn('hashed_new_password');

        $this->entityManager->expects($this->once())->method('flush');

        // Reset the password
        $result = $this->service->resetPassword('valid-token', 'newpass');
        
        // Verify password was updated and request was marked as used
        $this->assertSame('hashed_new_password', $user->getPassword());
        $this->assertTrue($resetRequest->isUsed());
        $this->assertTrue($result);
    }
}
