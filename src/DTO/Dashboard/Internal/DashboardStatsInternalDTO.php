<?php

namespace App\DTO\Dashboard\Internal;

class DashboardStatsInternalDTO
{
    private float $currentRevenue;
    private float $previousRevenue;
    private int $currentUsers;
    private int $previousUsers;
    private int $currentProducts;
    private int $previousProducts;
    private int $currentPendingOrders;
    private int $previousPendingOrders;

    public function __construct(
        float $currentRevenue,
        float $previousRevenue,
        int $currentUsers,
        int $previousUsers,
        int $currentProducts,
        int $previousProducts,
        int $currentPendingOrders,
        int $previousPendingOrders
    ) {
        $this->currentRevenue = $currentRevenue;
        $this->previousRevenue = $previousRevenue;
        $this->currentUsers = $currentUsers;
        $this->previousUsers = $previousUsers;
        $this->currentProducts = $currentProducts;
        $this->previousProducts = $previousProducts;
        $this->currentPendingOrders = $currentPendingOrders;
        $this->previousPendingOrders = $previousPendingOrders;
    }

    public function getCurrentRevenue(): float
    {
        return $this->currentRevenue;
    }

    public function getPreviousRevenue(): float
    {
        return $this->previousRevenue;
    }

    public function getRevenueVariation(): float
    {
        if ($this->previousRevenue == 0) {
            return $this->currentRevenue > 0 ? 100 : 0;
        }
        
        return (($this->currentRevenue - $this->previousRevenue) / $this->previousRevenue) * 100;
    }

    public function getCurrentUsers(): int
    {
        return $this->currentUsers;
    }

    public function getPreviousUsers(): int
    {
        return $this->previousUsers;
    }

    public function getUsersVariation(): float
    {
        if ($this->previousUsers == 0) {
            return $this->currentUsers > 0 ? 100 : 0;
        }
        
        return (($this->currentUsers - $this->previousUsers) / $this->previousUsers) * 100;
    }

    public function getCurrentProducts(): int
    {
        return $this->currentProducts;
    }

    public function getPreviousProducts(): int
    {
        return $this->previousProducts;
    }

    public function getProductsVariation(): float
    {
        if ($this->previousProducts == 0) {
            return $this->currentProducts > 0 ? 100 : 0;
        }
        
        return (($this->currentProducts - $this->previousProducts) / $this->previousProducts) * 100;
    }

    public function getCurrentPendingOrders(): int
    {
        return $this->currentPendingOrders;
    }

    public function getPreviousPendingOrders(): int
    {
        return $this->previousPendingOrders;
    }

    public function getPendingOrdersVariation(): float
    {
        if ($this->previousPendingOrders == 0) {
            return $this->currentPendingOrders > 0 ? 100 : 0;
        }
        
        return (($this->currentPendingOrders - $this->previousPendingOrders) / $this->previousPendingOrders) * 100;
    }
}
