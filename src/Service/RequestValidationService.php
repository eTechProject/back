<?php

namespace App\Service;

use App\Enum\Status;
use Symfony\Component\HttpFoundation\Request;

class RequestValidationService
{
    /**
     * Valide et extrait les paramètres de pagination d'une requête
     */
    public function validatePaginationParams(Request $request): array
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
        
        return [$page, $limit];
    }
    
    /**
     * Valide et extrait le paramètre de statut d'une requête
     */
    public function validateStatusParam(Request $request): ?Status
    {
        $statusParam = $request->query->get('status');
        
        if (!$statusParam) {
            return null;
        }
        
        $statusFilter = Status::tryFrom($statusParam);
        
        if (!$statusFilter) {
            throw new \InvalidArgumentException(
                'Statut invalide. Valeurs acceptées: pending, in_progress, completed, cancelled'
            );
        }
        
        return $statusFilter;
    }
}
