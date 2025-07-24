<?php

namespace App;

use Jsor\Doctrine\PostGIS\Functions\Configurator;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    
    public function boot(): void
    {
        parent::boot();
        
        // Configure PostGIS functions
        if ($this->container->has('doctrine.orm.entity_manager')) {
            $entityManager = $this->container->get('doctrine.orm.entity_manager');
            $configuration = $entityManager->getConfiguration();
            Configurator::configure($configuration);
        }
    }
}
