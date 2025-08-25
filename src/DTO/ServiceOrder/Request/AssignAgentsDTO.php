<?php

namespace App\DTO\ServiceOrder\Request;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\Task\Request\TaskRequestDTO;

class AssignAgentsDTO
{
    #[Assert\NotBlank(message: 'L\'ID de l\'ordre de service est requis')]
    public string $orderId;

    /**
     * @var TaskRequestDTO[]
     */
    #[Assert\NotBlank(message: 'Au moins un agent doit être assigné')]
    #[Assert\Type(type: 'array', message: 'Les assignations d\'agents doivent être un tableau')]
    #[Assert\Count(min: 1, minMessage: 'Au moins un agent doit être assigné')]
    #[Assert\Valid]
    public array $agentAssignments;

    public function __construct(
        string $orderId = '',
        array $agentAssignments = []
    ) {
        $this->orderId = $orderId;
        $this->agentAssignments = $agentAssignments;
    }
}
