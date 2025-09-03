<?php

declare(strict_types=1);

namespace App\DTO\Dashboard\Response;

use App\DTO\Dashboard\Internal\QuickActionDTO;

class QuickActionsResponseDTO
{
    /**
     * @param QuickActionDTO[] $actions
     */
    public function __construct(
        public readonly array $actions
    ) {
    }

    public function toArray(): array
    {
        return [
            'quick_actions' => array_map(fn(QuickActionDTO $action) => $action->toArray(), $this->actions),
            'total_actions' => count($this->actions),
            'enabled_actions' => count(array_filter($this->actions, fn(QuickActionDTO $action) => $action->enabled))
        ];
    }
}
