<?php

namespace App\DTO\Agent\Request;

use Symfony\Component\Validator\Constraints as Assert;

class RecordLocationDTO
{
    #[Assert\NotBlank(message: 'La longitude est requise')]
    #[Assert\Type(type: 'numeric', message: 'La longitude doit être numérique')]
    #[Assert\Range(
        min: -180,
        max: 180,
        notInRangeMessage: 'La longitude doit être comprise entre {{ min }} et {{ max }}'
    )]
    public float $longitude;

    #[Assert\NotBlank(message: 'La latitude est requise')]
    #[Assert\Type(type: 'numeric', message: 'La latitude doit être numérique')]
    #[Assert\Range(
        min: -90,
        max: 90,
        notInRangeMessage: 'La latitude doit être comprise entre {{ min }} et {{ max }}'
    )]
    public float $latitude;

    #[Assert\NotBlank(message: 'La précision est requise')]
    #[Assert\Type(type: 'numeric', message: 'La précision doit être numérique')]
    #[Assert\Range(
        min: 0,
        notInRangeMessage: 'La précision doit être positive'
    )]
    public float $accuracy;

    #[Assert\Type(type: 'numeric', message: 'La vitesse doit être numérique')]
    #[Assert\Range(
        min: 0,
        notInRangeMessage: 'La vitesse doit être positive'
    )]
    public ?float $speed = null;

    #[Assert\Type(type: 'numeric', message: 'Le niveau de batterie doit être numérique')]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le niveau de batterie doit être entre {{ min }} et {{ max }}%'
    )]
    public ?float $batteryLevel = null;

    #[Assert\Type(type: 'bool', message: 'isSignificant doit être un booléen')]
    public ?bool $isSignificant = null;

    #[Assert\Choice(
        choices: ['start_task', 'end_task', 'zone_entry', 'zone_exit', 'manual_report', 'anomaly', 'long_stop', 'out_of_zone'],
        message: 'Raison invalide. Valeurs autorisées: {{ choices }}'
    )]
    public ?string $reason = null;

    #[Assert\NotBlank(message: 'L\'ID de la tâche est requis')]
    #[Assert\Type(type: 'string', message: 'L\'ID de la tâche doit être une chaîne')]
    public string $taskId;

    public function __construct(
        float $longitude = 0.0,
        float $latitude = 0.0,
        float $accuracy = 0.0,
        ?float $speed = null,
        ?float $batteryLevel = null,
        ?bool $isSignificant = null,
        ?string $reason = null,
        string $taskId = ''
    ) {
        $this->longitude = $longitude;
        $this->latitude = $latitude;
        $this->accuracy = $accuracy;
        $this->speed = $speed;
        $this->batteryLevel = $batteryLevel;
        $this->isSignificant = $isSignificant;
        $this->reason = $reason;
        $this->taskId = $taskId;
    }
}
