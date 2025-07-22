<?php

namespace App\Entity;

use App\Repository\AgentsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Genre;

#[ORM\Entity(repositoryClass: AgentsRepository::class)]
class Agents
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 1, enumType: Genre::class)]
    private Genre $sexe;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profile_picture_url = null;

    #[ORM\ManyToOne(inversedBy: 'agents')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getSexe(): Genre
    {
        return $this->sexe;
    }

    public function setSexe(Genre $sexe): static
    {
        $this->sexe = $sexe;
        return $this;
    }

    public function getProfilePictureUrl(): ?string
    {
        return $this->profile_picture_url;
    }

    public function setProfilePictureUrl(?string $profile_picture_url): static
    {
        $this->profile_picture_url = $profile_picture_url;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
