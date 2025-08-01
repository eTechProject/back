<?php

namespace App\Doctrine;

use Jsor\Doctrine\PostGIS\Functions\ST_AsText;
use Jsor\Doctrine\PostGIS\Functions\ST_Contains;
use Jsor\Doctrine\PostGIS\Functions\ST_Distance;
use Jsor\Doctrine\PostGIS\Functions\ST_DWithin;
use Jsor\Doctrine\PostGIS\Functions\ST_GeomFromText;
use Jsor\Doctrine\PostGIS\Functions\ST_Intersects;
use Jsor\Doctrine\PostGIS\Functions\ST_MakePoint;
use Jsor\Doctrine\PostGIS\Functions\ST_Point;
use Jsor\Doctrine\PostGIS\Functions\ST_SetSRID;
use Jsor\Doctrine\PostGIS\Functions\ST_Within;
use Jsor\Doctrine\PostGIS\Functions\ST_X;
use Jsor\Doctrine\PostGIS\Functions\ST_Y;
use Jsor\Doctrine\PostGIS\Types\GeometryType;
use Jsor\Doctrine\PostGIS\Types\PointType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;

class PostGISConfiguratorService
{
    public static function configurePostGIS(Configuration $configuration): void
    {
        // Register PostGIS types
        if (!Type::hasType('geometry')) {
            Type::addType('geometry', GeometryType::class);
        }
        if (!Type::hasType('point')) {
            Type::addType('point', PointType::class);
        }

        // Register PostGIS functions
        $configuration->addCustomStringFunction('ST_AsText', ST_AsText::class);
        $configuration->addCustomStringFunction('ST_Contains', ST_Contains::class);
        $configuration->addCustomNumericFunction('ST_Distance', ST_Distance::class);
        $configuration->addCustomStringFunction('ST_DWithin', ST_DWithin::class);
        $configuration->addCustomStringFunction('ST_GeomFromText', ST_GeomFromText::class);
        $configuration->addCustomStringFunction('ST_Intersects', ST_Intersects::class);
        $configuration->addCustomStringFunction('ST_MakePoint', ST_MakePoint::class);
        $configuration->addCustomStringFunction('ST_Point', ST_Point::class);
        $configuration->addCustomStringFunction('ST_SetSRID', ST_SetSRID::class);
        $configuration->addCustomStringFunction('ST_Within', ST_Within::class);
        $configuration->addCustomNumericFunction('ST_X', ST_X::class);
        $configuration->addCustomNumericFunction('ST_Y', ST_Y::class);
    }
}