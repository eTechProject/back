<?php

namespace App\DTO\ServiceOrder\Request;

use Symfony\Component\Validator\Constraints as Assert;

class AssignAgentsDTO
{
    #[Assert\NotBlank(message: 'L\'ID de l\'ordre de service est requis')]
    public string $orderId;

    #[Assert\NotBlank(message: 'Au moins un agent doit être assigné')]
    #[Assert\Type(
        type: 'array',
        message: 'Les assignations d\'agents doivent être un tableau'
    )]
    #[Assert\Count(
        min: 1,
        minMessage: 'Au moins un agent doit être assigné'
    )]
    #[Assert\All([
        new Assert\Collection([
            'agentId' => [
                new Assert\NotBlank(message: 'L\'ID de l\'agent est requis'),
                new Assert\Type(type: 'string', message: 'L\'ID de l\'agent doit être une chaîne')
            ],
            'coordinates' => [
                new Assert\NotBlank(message: 'Les coordonnées sont requises'),
                new Assert\Type(type: 'array', message: 'Les coordonnées doivent être un tableau'),
                new Assert\Count(
                    min: 2,
                    max: 2,
                    exactMessage: 'Les coordonnées doivent contenir exactement 2 éléments [longitude, latitude]'
                ),
                new Assert\All([
                    new Assert\Type(type: 'numeric', message: 'Chaque coordonnée doit être numérique')
                ])
            ]
        ])
    ])]
    public array $agentAssignments;

    public function __construct(
        string $orderId = '',
        array $agentAssignments = []
    ) {
        $this->orderId = $orderId;
        $this->agentAssignments = $agentAssignments;
    }
}
