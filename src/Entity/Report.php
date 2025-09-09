<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 10)]
    private string $format;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $generatedAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $meta = null;

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getType(): string
    {
        return $this->type;
    }
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
    public function getFormat(): string
    {
        return $this->format;
    }
    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }
    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }
    public function setGeneratedAt(\DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;
        return $this;
    }
    public function getMeta(): ?array
    {
        return $this->meta;
    }
    public function setMeta(?array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }
}
