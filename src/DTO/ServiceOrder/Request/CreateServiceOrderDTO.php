<?php

namespace App\DTO\ServiceOrder\Request;

use Symfony\Component\Validator\Constraints as Assert;
use App\Enum\Status;
use App\DTO\SecuredZone\Request\CreateSecuredZoneDTO;

class CreateServiceOrderDTO
{
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'L\'ID du client est requis')]
    public string $clientId;

    /**
     * Données de la zone sécurisée à créer avec la commande
     */
    #[Assert\NotBlank(message: 'Les données de la zone sécurisée sont requises')]
    #[Assert\Valid]
    public CreateSecuredZoneDTO $securedZone;
}
