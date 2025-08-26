<?php

namespace App\DTO\User\Request;

use Symfony\Component\Validator\Constraints as Assert;

class RefreshTokenRequest
{
    /**
     * @Assert\NotBlank
     */
    public $refresh_token;
}
