<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enable PostGIS extension for spatial data support
 */
final class Version20250722090001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable PostGIS extension for spatial data support';
    }

    public function up(Schema $schema): void
    {
        // Enable PostGIS extension if not already enabled
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis');
        
        // Optionally enable additional PostGIS extensions
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis_topology');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis_raster');
    }

    public function down(Schema $schema): void
    {
        // Note: Dropping PostGIS extension requires dropping all dependent objects first
        // This is usually not recommended in production as it would remove all spatial data
        $this->addSql('DROP EXTENSION IF EXISTS postgis_raster CASCADE');
        $this->addSql('DROP EXTENSION IF EXISTS postgis_topology CASCADE');
        $this->addSql('DROP EXTENSION IF EXISTS postgis CASCADE');
    }

    public function isTransactional(): bool
    {
        // Extension creation/dropping should not be in a transaction
        return false;
    }
}
