<?php

namespace App\DTO\Client\Request;

use Symfony\Component\Validator\Constraints as Assert;

class StopAlertRequestDTO
{

    #[Assert\NotBlank(message: 'Alert ID is required')]
    public string $alertId;
}