<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueEmail extends Constraint
{
    public string $message = 'Cette adresse email est déjà utilisée par un autre utilisateur.';
    public ?int $excludeUserId = null;

    public function __construct(
        ?int $excludeUserId = null,
        ?string $message = null,
        ?array $groups = null,
        $payload = null
    ) {
        parent::__construct([], $groups, $payload);
        
        $this->excludeUserId = $excludeUserId;
        $this->message = $message ?? $this->message;
    }
}
