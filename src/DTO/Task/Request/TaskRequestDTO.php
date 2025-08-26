<?php

namespace App\DTO\Task\Request;


use App\Enum\TaskType;
use Symfony\Component\Validator\Constraints as Assert;


class TaskRequestDTO
{
    #[Assert\NotBlank(message: "L'ID de l'agent est requis")]
    #[Assert\Type(type: 'string', message: "L'ID de l'agent doit être une chaîne")]
    public string $agentId;

    #[Assert\NotBlank(message: "Le type de tâche est requis")]
    #[Assert\Choice(callback: [TaskType::class, 'cases'], message: "Type de tâche invalide")]
    public string $type;

    #[Assert\Type(type: 'string', message: "La description doit être une chaîne")]
    public string $description;

    #[Assert\NotBlank(message: "La date de début est requise")]
    #[Assert\DateTime(message: "La date de début doit être une date-heure valide (Y-m-d H:i:s)")]
    public string $startDate;

    #[Assert\NotBlank(message: "La date de fin est requise")]
    #[Assert\DateTime(message: "La date de fin doit être une date-heure valide (Y-m-d H:i:s)")]
    public string $endDate;

    #[Assert\NotBlank(message: "La position assignée est requise")]
    #[Assert\Type(type: 'array', message: 'La position assignée doit être un tableau')]
    #[Assert\Count(
        min: 2,
        max: 2,
        exactMessage: 'La position assignée doit contenir exactement 2 éléments [longitude, latitude]'
    )]
    #[Assert\All([
        new Assert\Type(type: 'numeric', message: 'Chaque coordonnée doit être numérique')
    ])]
    public array $assignPosition;

    public function __construct(
        string $agentId = '',
        string $type = '',
        ?string $description = '',
        string $startDate = '',
        string $endDate = '',
        array $assignPosition = []
    ) {
        $this->agentId = $agentId;
        $this->type = $type;
        $this->description = $description;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->assignPosition = $assignPosition;
    }
}
