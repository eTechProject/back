<?php

namespace App\DTO\Dashboard\Request;

use Symfony\Component\Validator\Constraints as Assert;

class DashboardFiltersDTO
{
    public ?string $dateRange = null;

    #[Assert\Choice(
        choices: ['today', 'last7days', 'thisMonth', 'last30days', 'thisYear'],
        message: 'Choix invalide. Valeurs autorisées: {{ choices }}'
    )]
    public ?string $choice = null;

    public ?string $dateStart = null;
    public ?string $dateEnd = null;
    // Ajoutez d'autres filtres si besoin
}
