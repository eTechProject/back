<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730112306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IF EXISTS idx_9596ab6ea76ed395');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9596AB6EA76ED395 ON agents (user_id)');
        $this->addSql('ALTER TABLE tasks ADD assign_position geometry(POINT, 4326) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_9596AB6EA76ED395');
        $this->addSql('CREATE INDEX idx_9596ab6ea76ed395 ON agents (user_id)');
        $this->addSql('ALTER TABLE tasks DROP assign_position');
    }
}
