<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to truncate all tables and reset sequences
 */
final class Version20250902123602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Truncate all tables and reset sequences';
    }

    public function up(Schema $schema): void
    {
        // Truncate all tables to clear existing data
        $this->truncateAllTables();
    }

    public function down(Schema $schema): void
    {
        // Note: This migration cannot be safely reversed as it removes data
        // The down migration is intentionally left empty to prevent accidental data loss
        $this->addSql('-- This migration truncates data and cannot be reversed');
    }

    /**
     * Truncate all tables in the correct order (respecting foreign key constraints)
     */
    private function truncateAllTables(): void
    {
        // For PostgreSQL, we use CASCADE to handle foreign key constraints
        
        // Truncate tables with CASCADE to handle foreign key constraints
        $this->addSql('TRUNCATE TABLE alert CASCADE');
        $this->addSql('TRUNCATE TABLE refresh_token CASCADE');
        $this->addSql('TRUNCATE TABLE agent_location_significant CASCADE');
        $this->addSql('TRUNCATE TABLE agent_locations_archive CASCADE');
        $this->addSql('TRUNCATE TABLE agent_locations_raw CASCADE');
        $this->addSql('TRUNCATE TABLE messages CASCADE');
        $this->addSql('TRUNCATE TABLE tasks CASCADE');
        $this->addSql('TRUNCATE TABLE service_orders CASCADE');
        $this->addSql('TRUNCATE TABLE agents CASCADE');
        $this->addSql('TRUNCATE TABLE payment_history CASCADE');
        $this->addSql('TRUNCATE TABLE payment CASCADE');
        $this->addSql('TRUNCATE TABLE notifications CASCADE');
        $this->addSql('TRUNCATE TABLE reset_password_request CASCADE');
        $this->addSql('TRUNCATE TABLE secured_zones CASCADE');
        $this->addSql('TRUNCATE TABLE packs CASCADE');
        $this->addSql('TRUNCATE TABLE users CASCADE');
        $this->addSql('TRUNCATE TABLE messenger_messages CASCADE');
        
        // Reset sequences for PostgreSQL SERIAL columns
        $this->addSql('ALTER SEQUENCE alert_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE refresh_token_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE agent_location_significant_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE agent_locations_archive_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE agent_locations_raw_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE messages_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE tasks_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE service_orders_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE agents_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE payment_history_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE payment_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE notifications_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE reset_password_request_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE secured_zones_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE packs_id_seq RESTART WITH 1');
        $this->addSql('ALTER SEQUENCE users_id_seq RESTART WITH 1');
    }
}
