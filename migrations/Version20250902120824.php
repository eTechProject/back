<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250902120824 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alert (id SERIAL NOT NULL, iduser INT NOT NULL, idorder INT NOT NULL, type VARCHAR(32) NOT NULL, timestamp TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_17FD46C15E5C27E9 ON alert (iduser)');
        $this->addSql('CREATE INDEX IDX_17FD46C1232CFF81 ON alert (idorder)');
        $this->addSql('COMMENT ON COLUMN alert.timestamp IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE refresh_token (id SERIAL NOT NULL, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C74F21955F37A13B ON refresh_token (token)');
        $this->addSql('CREATE INDEX IDX_C74F2195A76ED395 ON refresh_token (user_id)');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_17FD46C15E5C27E9 FOREIGN KEY (iduser) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_17FD46C1232CFF81 FOREIGN KEY (idorder) REFERENCES service_orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE SEQUENCE agent_location_significant_id_seq');
        $this->addSql('SELECT setval(\'agent_location_significant_id_seq\', (SELECT MAX(id) FROM agent_location_significant))');
        $this->addSql('ALTER TABLE agent_location_significant ALTER id SET DEFAULT nextval(\'agent_location_significant_id_seq\')');
        $this->addSql('ALTER TABLE agent_location_significant ALTER recorded_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN agent_location_significant.recorded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_significant_agent RENAME TO IDX_5A8F7BD3414710B');
        $this->addSql('ALTER INDEX idx_significant_task RENAME TO IDX_5A8F7BD8DB60186');
        $this->addSql('CREATE SEQUENCE agent_locations_archive_id_seq');
        $this->addSql('SELECT setval(\'agent_locations_archive_id_seq\', (SELECT MAX(id) FROM agent_locations_archive))');
        $this->addSql('ALTER TABLE agent_locations_archive ALTER id SET DEFAULT nextval(\'agent_locations_archive_id_seq\')');
        $this->addSql('ALTER TABLE agent_locations_archive ALTER start_time TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE agent_locations_archive ALTER end_time TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN agent_locations_archive.start_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN agent_locations_archive.end_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_archive_agent RENAME TO IDX_3F3E3AC73414710B');
        $this->addSql('ALTER INDEX idx_archive_task RENAME TO IDX_3F3E3AC78DB60186');
        $this->addSql('CREATE SEQUENCE agent_locations_raw_id_seq');
        $this->addSql('SELECT setval(\'agent_locations_raw_id_seq\', (SELECT MAX(id) FROM agent_locations_raw))');
        $this->addSql('ALTER TABLE agent_locations_raw ALTER id SET DEFAULT nextval(\'agent_locations_raw_id_seq\')');
        $this->addSql('ALTER TABLE agent_locations_raw ALTER recorded_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN agent_locations_raw.recorded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_raw_task RENAME TO IDX_DF2DE25DE3272D31');
        $this->addSql('ALTER INDEX idx_raw_agent RENAME TO IDX_DF2DE25D3414710B');
        $this->addSql('CREATE SEQUENCE agents_id_seq');
        $this->addSql('SELECT setval(\'agents_id_seq\', (SELECT MAX(id) FROM agents))');
        $this->addSql('ALTER TABLE agents ALTER id SET DEFAULT nextval(\'agents_id_seq\')');
        $this->addSql('ALTER INDEX uniq_agents_user RENAME TO UNIQ_9596AB6EA76ED395');
        $this->addSql('CREATE SEQUENCE messages_id_seq');
        $this->addSql('SELECT setval(\'messages_id_seq\', (SELECT MAX(id) FROM messages))');
        $this->addSql('ALTER TABLE messages ALTER id SET DEFAULT nextval(\'messages_id_seq\')');
        $this->addSql('ALTER TABLE messages ALTER sent_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN messages.sent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_messages_order RENAME TO IDX_DB021E968D9F6D38');
        $this->addSql('ALTER INDEX idx_messages_sender RENAME TO IDX_DB021E96F624B39D');
        $this->addSql('ALTER INDEX idx_messages_receiver RENAME TO IDX_DB021E96CD53EDB6');
        $this->addSql('CREATE SEQUENCE notifications_id_seq');
        $this->addSql('SELECT setval(\'notifications_id_seq\', (SELECT MAX(id) FROM notifications))');
        $this->addSql('ALTER TABLE notifications ALTER id SET DEFAULT nextval(\'notifications_id_seq\')');
        $this->addSql('ALTER INDEX idx_notifications_user RENAME TO IDX_6000B0D3A76ED395');
        $this->addSql('CREATE SEQUENCE packs_id_seq');
        $this->addSql('SELECT setval(\'packs_id_seq\', (SELECT MAX(id) FROM packs))');
        $this->addSql('ALTER TABLE packs ALTER id SET DEFAULT nextval(\'packs_id_seq\')');
        $this->addSql('CREATE SEQUENCE payment_id_seq');
        $this->addSql('SELECT setval(\'payment_id_seq\', (SELECT MAX(id) FROM payment))');
        $this->addSql('ALTER TABLE payment ALTER id SET DEFAULT nextval(\'payment_id_seq\')');
        $this->addSql('ALTER INDEX idx_payment_client RENAME TO IDX_6D28840D19EB6921');
        $this->addSql('ALTER INDEX idx_payment_pack RENAME TO IDX_6D28840D1919B217');
        $this->addSql('ALTER TABLE payment_history ADD provider_response TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment_history ADD stripe_payment_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE SEQUENCE payment_history_id_seq');
        $this->addSql('SELECT setval(\'payment_history_id_seq\', (SELECT MAX(id) FROM payment_history))');
        $this->addSql('ALTER TABLE payment_history ALTER id SET DEFAULT nextval(\'payment_history_id_seq\')');
        $this->addSql('ALTER INDEX idx_payment_history_payment RENAME TO IDX_3EF37EA14C3A3BB');
        $this->addSql('DROP INDEX idx_reset_pwd_selector');
        $this->addSql('CREATE SEQUENCE reset_password_request_id_seq');
        $this->addSql('SELECT setval(\'reset_password_request_id_seq\', (SELECT MAX(id) FROM reset_password_request))');
        $this->addSql('ALTER TABLE reset_password_request ALTER id SET DEFAULT nextval(\'reset_password_request_id_seq\')');
        $this->addSql('ALTER TABLE reset_password_request ALTER requested_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reset_password_request ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reset_password_request ALTER used DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_reset_pwd_user RENAME TO IDX_7CE748AA76ED395');
        $this->addSql('CREATE SEQUENCE secured_zones_id_seq');
        $this->addSql('SELECT setval(\'secured_zones_id_seq\', (SELECT MAX(id) FROM secured_zones))');
        $this->addSql('ALTER TABLE secured_zones ALTER id SET DEFAULT nextval(\'secured_zones_id_seq\')');
        $this->addSql('ALTER TABLE secured_zones ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN secured_zones.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE SEQUENCE service_orders_id_seq');
        $this->addSql('SELECT setval(\'service_orders_id_seq\', (SELECT MAX(id) FROM service_orders))');
        $this->addSql('ALTER TABLE service_orders ALTER id SET DEFAULT nextval(\'service_orders_id_seq\')');
        $this->addSql('ALTER TABLE service_orders ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN service_orders.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_service_orders_secured_zone RENAME TO IDX_DBE8F8D8D915E713');
        $this->addSql('ALTER INDEX idx_service_orders_client RENAME TO IDX_DBE8F8D819EB6921');
        $this->addSql('ALTER TABLE tasks ADD type VARCHAR(30) NOT NULL');
        $this->addSql('CREATE SEQUENCE tasks_id_seq');
        $this->addSql('SELECT setval(\'tasks_id_seq\', (SELECT MAX(id) FROM tasks))');
        $this->addSql('ALTER TABLE tasks ALTER id SET DEFAULT nextval(\'tasks_id_seq\')');
        $this->addSql('ALTER TABLE tasks ALTER end_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE tasks ALTER start_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN tasks.end_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tasks.start_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_tasks_order RENAME TO IDX_505865978D9F6D38');
        $this->addSql('ALTER INDEX idx_tasks_agent RENAME TO IDX_505865973414710B');
        $this->addSql('CREATE SEQUENCE users_id_seq');
        $this->addSql('SELECT setval(\'users_id_seq\', (SELECT MAX(id) FROM users))');
        $this->addSql('ALTER TABLE users ALTER id SET DEFAULT nextval(\'users_id_seq\')');
        $this->addSql('ALTER INDEX uniq_users_email RENAME TO UNIQ_1483A5E9E7927C74');
        $this->addSql('ALTER TABLE messenger_messages ALTER queue_name TYPE VARCHAR(190)');
        $this->addSql('ALTER TABLE messenger_messages ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER available_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER delivered_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_messenger_queue_name RENAME TO IDX_75EA56E0FB7336F0');
        $this->addSql('ALTER INDEX idx_messenger_available_at RENAME TO IDX_75EA56E0E3BD61CE');
        $this->addSql('ALTER INDEX idx_messenger_delivered_at RENAME TO IDX_75EA56E016BA31DB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA topology');
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE topology.topology_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_17FD46C15E5C27E9');
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_17FD46C1232CFF81');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F2195A76ED395');
        $this->addSql('DROP TABLE alert');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('ALTER TABLE agent_locations_raw ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE agent_locations_raw ALTER recorded_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN agent_locations_raw.recorded_at IS NULL');
        $this->addSql('ALTER INDEX idx_df2de25d3414710b RENAME TO idx_raw_agent');
        $this->addSql('ALTER INDEX idx_df2de25de3272d31 RENAME TO idx_raw_task');
        $this->addSql('ALTER TABLE agents ALTER id DROP DEFAULT');
        $this->addSql('ALTER INDEX uniq_9596ab6ea76ed395 RENAME TO uniq_agents_user');
        $this->addSql('ALTER TABLE service_orders ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE service_orders ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN service_orders.created_at IS NULL');
        $this->addSql('ALTER INDEX idx_dbe8f8d819eb6921 RENAME TO idx_service_orders_client');
        $this->addSql('ALTER INDEX idx_dbe8f8d8d915e713 RENAME TO idx_service_orders_secured_zone');
        $this->addSql('ALTER TABLE agent_locations_archive ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE agent_locations_archive ALTER start_time TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE agent_locations_archive ALTER end_time TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN agent_locations_archive.start_time IS NULL');
        $this->addSql('COMMENT ON COLUMN agent_locations_archive.end_time IS NULL');
        $this->addSql('ALTER INDEX idx_3f3e3ac73414710b RENAME TO idx_archive_agent');
        $this->addSql('ALTER INDEX idx_3f3e3ac78db60186 RENAME TO idx_archive_task');
        $this->addSql('ALTER TABLE messenger_messages ALTER queue_name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE messenger_messages ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER available_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER delivered_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS NULL');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS NULL');
        $this->addSql('ALTER INDEX idx_75ea56e0e3bd61ce RENAME TO idx_messenger_available_at');
        $this->addSql('ALTER INDEX idx_75ea56e016ba31db RENAME TO idx_messenger_delivered_at');
        $this->addSql('ALTER INDEX idx_75ea56e0fb7336f0 RENAME TO idx_messenger_queue_name');
        $this->addSql('ALTER TABLE payment ALTER id DROP DEFAULT');
        $this->addSql('ALTER INDEX idx_6d28840d19eb6921 RENAME TO idx_payment_client');
        $this->addSql('ALTER INDEX idx_6d28840d1919b217 RENAME TO idx_payment_pack');
        $this->addSql('ALTER TABLE reset_password_request ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE reset_password_request ALTER used SET DEFAULT false');
        $this->addSql('ALTER TABLE reset_password_request ALTER requested_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reset_password_request ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS NULL');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS NULL');
        $this->addSql('CREATE INDEX idx_reset_pwd_selector ON reset_password_request (selector)');
        $this->addSql('ALTER INDEX idx_7ce748aa76ed395 RENAME TO idx_reset_pwd_user');
        $this->addSql('ALTER TABLE secured_zones ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE secured_zones ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN secured_zones.created_at IS NULL');
        $this->addSql('ALTER TABLE notifications ALTER id DROP DEFAULT');
        $this->addSql('ALTER INDEX idx_6000b0d3a76ed395 RENAME TO idx_notifications_user');
        $this->addSql('ALTER TABLE agent_location_significant ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE agent_location_significant ALTER recorded_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN agent_location_significant.recorded_at IS NULL');
        $this->addSql('ALTER INDEX idx_5a8f7bd3414710b RENAME TO idx_significant_agent');
        $this->addSql('ALTER INDEX idx_5a8f7bd8db60186 RENAME TO idx_significant_task');
        $this->addSql('ALTER TABLE users ALTER id DROP DEFAULT');
        $this->addSql('ALTER INDEX uniq_1483a5e9e7927c74 RENAME TO uniq_users_email');
        $this->addSql('ALTER TABLE tasks DROP type');
        $this->addSql('ALTER TABLE tasks ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE tasks ALTER end_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE tasks ALTER start_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN tasks.end_date IS NULL');
        $this->addSql('COMMENT ON COLUMN tasks.start_date IS NULL');
        $this->addSql('ALTER INDEX idx_505865973414710b RENAME TO idx_tasks_agent');
        $this->addSql('ALTER INDEX idx_505865978d9f6d38 RENAME TO idx_tasks_order');
        $this->addSql('ALTER TABLE messages ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE messages ALTER sent_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN messages.sent_at IS NULL');
        $this->addSql('ALTER INDEX idx_db021e968d9f6d38 RENAME TO idx_messages_order');
        $this->addSql('ALTER INDEX idx_db021e96cd53edb6 RENAME TO idx_messages_receiver');
        $this->addSql('ALTER INDEX idx_db021e96f624b39d RENAME TO idx_messages_sender');
        $this->addSql('ALTER TABLE packs ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE payment_history DROP provider_response');
        $this->addSql('ALTER TABLE payment_history DROP stripe_payment_id');
        $this->addSql('ALTER TABLE payment_history ALTER id DROP DEFAULT');
        $this->addSql('ALTER INDEX idx_3ef37ea14c3a3bb RENAME TO idx_payment_history_payment');
    }
}
