<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;

class PasswordResetService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function handleResetRequest(string $email): void
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return;
        }

        $token = $this->resetPasswordHelper->generateResetToken($user);
        $this->entityManager->flush();

        $email = (new TemplatedEmail())
            ->from('pokaneliot@gmail.com')
            ->to($user->getEmail())
            ->subject('Your password reset request')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'user' => $user,
                'token' => $token,
            ]);

        $this->mailer->send($email);
    }

    public function resetPassword(string $token, string $newPassword): true|string
    {
        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (\Exception $e) {
            return 'Invalid or expired token.';
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        return true;
    }
}