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
                throw new \Exception('Utilisateur non trouvé.');
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
            throw new \Exception('Vous avez déjà demandé un mot de passe oublié. Veuillez vérifier votre boîte de réception pour le lien de réinitialisation.');
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function resetPassword(string $token, string $newPassword): true|string
    {
        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (\Exception $e) {
            return 'Token invalide ou expiré.';
        }

        $resetRequest = $this->entityManager
            ->getRepository(\App\Entity\ResetPasswordRequest::class)
            ->findOneBy(
                ['user' => $user, 'used' => false],
                ['requestedAt' => 'DESC']
            );

        if (!$resetRequest || $resetRequest->isUsed()) {
            return 'Le Lien de réinitialisation a déjà été utilisé.';
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        $resetRequest->setUsed(true);

        $this->entityManager->flush();

        return true;
    }
}