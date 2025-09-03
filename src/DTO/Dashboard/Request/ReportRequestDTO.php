<?php

namespace App\DTO\Dashboard\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ReportRequestDTO
{
    #[Assert\NotBlank(message: "La date de début est obligatoire")]
    #[Assert\Date(message: "Format de date invalide")]
    private ?string $startDate = null;

    #[Assert\NotBlank(message: "La date de fin est obligatoire")]
    #[Assert\Date(message: "Format de date invalide")]
    private ?string $endDate = null;

    #[Assert\Choice(
        choices: ['pdf', 'excel', 'csv'], 
        message: "Le format doit être pdf, excel ou csv"
    )]
    private string $format = 'pdf';

    #[Assert\Choice(
        choices: ['revenue', 'users', 'orders', 'products', 'complete'], 
        message: "Type de rapport invalide"
    )]
    private string $type = 'complete';

    public function getStartDate(): ?string
    {
        return $this->startDate;
    }

    public function setStartDate(?string $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?string
    {
        return $this->endDate;
    }

    public function setEndDate(?string $endDate): self
    {
        $this->endDate = $endDate;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getStartDateTime(): ?\DateTimeImmutable
    {
        return $this->startDate ? new \DateTimeImmutable($this->startDate) : null;
    }

    public function getEndDateTime(): ?\DateTimeImmutable
    {
        return $this->endDate ? new \DateTimeImmutable($this->endDate . ' 23:59:59') : null;
    }
}
