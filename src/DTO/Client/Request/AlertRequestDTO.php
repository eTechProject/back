<?php

namespace App\DTO\Client\Request;

use Symfony\Component\Validator\Constraints as Assert;
use App\Enum\AlertType;

class AlertRequestDTO
{
    #[Assert\NotBlank]
    public string $userId;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [AlertType::class, 'values'])]
    public string $type;

}
