<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;

class PasswordResetService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function handleResetRequest(string $email): void
    {
        try {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                return;
            }

            $token = $this->resetPasswordHelper->generateResetToken($user);
            $this->entityManager->flush();

            $emailObject = (new TemplatedEmail())
                ->from(new Address('no-reply@guard-info.com', 'Guard Security Service'))
                ->to($user->getEmail())
                ->subject('Your password reset request')
                ->htmlTemplate('reset_password/email.html.twig')
                ->context([
                    'user' => $user,
                    'resetToken' => $token,
                ]);


            $this->mailer->send($emailObject);
        } catch (ResetPasswordExceptionInterface $e) {
            return;
        }
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