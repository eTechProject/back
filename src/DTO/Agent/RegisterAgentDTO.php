<?php

namespace App\DTO\Agent;

use Symfony\Component\Validator\Constraints as Assert;
use App\Enum\Genre;

class RegisterAgentDTO
{
    #[Assert\NotBlank(message: 'Le nom est requis')]
    public string $name;

    #[Assert\NotBlank(message: 'L\'adresse est requise')]
    public string $address;

    #[Assert\NotBlank(message: 'Le sexe est requis')]
    #[Assert\Choice(
        callback: [Genre::class, 'values'],
        message: 'Le sexe doit être M ou F'
    )]
    public string $sexe;

    #[Assert\NotBlank(message: 'Le profil est requis')]
    public string $profile;

    #[Assert\NotBlank(message: 'L\'email est requis')]
    #[Assert\Email(message: 'Email invalide')]
    public string $email;

    public ?string $password = null;

    public function getEnumSexe(): Genre
    {
        return Genre::from($this->sexe);
    }
}
