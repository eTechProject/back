<?php

namespace App\DTO\Client\Request;

use Symfony\Component\Validator\Constraints as Assert;

class AlertRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    public ?int $userId = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    public ?int $orderId = null;

    #[Assert\NotBlank]
    #[Assert\Choice(['danger', 'incident', 'urgence'])]
    public ?string $type = null;

    public ?string $message = null;

    #[Assert\NotBlank]
    public ?string $position = null;
}
