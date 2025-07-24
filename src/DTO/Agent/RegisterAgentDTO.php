<?php

namespace App\DTO\Agent;

use Symfony\Component\Validator\Constraints as Assert;
use App\Enum\Genre;

class RegisterAgentDTO
{
    #[Assert\NotBlank(message: 'Le sexe est requis')]
    #[Assert\Choice(
        callback: [Genre::class, 'values'],
        message: 'Le sexe doit être M ou F'
    )]
    public string $sexe;

    #[Assert\NotBlank(message: 'L\'identifiant utilisateur est requis')]
    #[Assert\Type(type: 'integer', message: 'L\'ID utilisateur doit être un entier')]
    public int $userId;

    public function __construct(array $data)
    {
        $this->sexe = $data['sexe'] ?? '';
        $this->userId = (int) ($data['userId'] ?? 0);
    }

    public function getEnumSexe(): Genre
    {
        return Genre::from($this->sexe);
    }
}
