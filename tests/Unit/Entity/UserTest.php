<?php

namespace Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use App\Entity\User;
use App\Enum\UserRole;

class UserTest extends TestCase
{
    public function testUserProperties(): void
    {
        $user = new User();

        $this->assertNull($user->getId());
        $this->assertInstanceOf(\DateTimeInterface::class, $user->getCreatedAt());

        $user->setName('John Doe')
             ->setEmail('john@example.com')
             ->setPhone('1234567890')
             ->setRole(UserRole::AGENT)
             ->setPassword('secret');

        $this->assertSame('John Doe', $user->getName());
        $this->assertSame('john@example.com', $user->getEmail());
        $this->assertSame('1234567890', $user->getPhone());
        $this->assertSame(UserRole::AGENT, $user->getRole());
        $this->assertSame('secret', $user->getPassword());
        $this->assertSame(['ROLE_AGENT'], $user->getRoles());
        $this->assertSame('john@example.com', $user->getUserIdentifier());
    }
}
