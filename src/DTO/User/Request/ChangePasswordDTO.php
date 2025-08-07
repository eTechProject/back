<?php

namespace App\DTO\User\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordDTO
{
    #[Assert\NotBlank(message: 'Le mot de passe actuel est requis')]
    public string $current_password;

    #[Assert\NotBlank(message: 'Le nouveau mot de passe est requis')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$ %^&*-]).{8,}$/',
        message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial'
    )]
    public string $new_password;
}
