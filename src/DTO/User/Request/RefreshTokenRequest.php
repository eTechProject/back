<?php

namespace App\DTO\User\Request;

use Symfony\Component\Validator\Constraints as Assert;

class RefreshTokenRequest
{
    #[Assert\NotBlank(message: 'Le refresh_token est requis')]
    #[Assert\Type(type: 'string', message: 'Le refresh_token doit être une chaîne')]
    public string $refresh_token;

    public function __construct(string $refresh_token = '')
    {
        $this->refresh_token = $refresh_token;
    }
}
