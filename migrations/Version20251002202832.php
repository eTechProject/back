<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002202832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent_location_significant (id SERIAL NOT NULL, agent_id INT NOT NULL, task_id INT NOT NULL, geom geometry(POINT, 4326) NOT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reason VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5A8F7BD3414710B ON agent_location_significant (agent_id)');
        $this->addSql('CREATE INDEX IDX_5A8F7BD8DB60186 ON agent_location_significant (task_id)');
        $this->addSql('COMMENT ON COLUMN agent_location_significant.recorded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE agent_locations_archive (id SERIAL NOT NULL, agent_id INT NOT NULL, task_id INT NOT NULL, geom geometry(LINESTRING, 4326) NOT NULL, start_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, point_count INT NOT NULL, avg_speed DOUBLE PRECISION DEFAULT NULL, path_length DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3F3E3AC73414710B ON agent_locations_archive (agent_id)');
        $this->addSql('CREATE INDEX IDX_3F3E3AC78DB60186 ON agent_locations_archive (task_id)');
        $this->addSql('COMMENT ON COLUMN agent_locations_archive.start_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN agent_locations_archive.end_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE agent_locations_raw (id SERIAL NOT NULL, tasks_id INT NOT NULL, agent_id INT NOT NULL, geom geometry(POINT, 4326) NOT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accuracy DOUBLE PRECISION NOT NULL, speed DOUBLE PRECISION DEFAULT NULL, battery_level DOUBLE PRECISION DEFAULT NULL, is_significant BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DF2DE25DE3272D31 ON agent_locations_raw (tasks_id)');
        $this->addSql('CREATE INDEX IDX_DF2DE25D3414710B ON agent_locations_raw (agent_id)');
        $this->addSql('COMMENT ON COLUMN agent_locations_raw.recorded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE agents (id SERIAL NOT NULL, user_id INT NOT NULL, address VARCHAR(255) DEFAULT NULL, sexe VARCHAR(1) NOT NULL, profile_picture_url VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9596AB6EA76ED395 ON agents (user_id)');
        $this->addSql('CREATE TABLE alert (id SERIAL NOT NULL, iduser INT NOT NULL, idorder INT NOT NULL, type VARCHAR(32) NOT NULL, timestamp TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_17FD46C15E5C27E9 ON alert (iduser)');
        $this->addSql('CREATE INDEX IDX_17FD46C1232CFF81 ON alert (idorder)');
        $this->addSql('COMMENT ON COLUMN alert.timestamp IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE messages (id SERIAL NOT NULL, order_id INT NOT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, content TEXT NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DB021E968D9F6D38 ON messages (order_id)');
        $this->addSql('CREATE INDEX IDX_DB021E96F624B39D ON messages (sender_id)');
        $this->addSql('CREATE INDEX IDX_DB021E96CD53EDB6 ON messages (receiver_id)');
        $this->addSql('COMMENT ON COLUMN messages.sent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE notifications (id SERIAL NOT NULL, user_id INT DEFAULT NULL, titre VARCHAR(255) NOT NULL, message TEXT NOT NULL, type VARCHAR(50) NOT NULL, cible VARCHAR(20) NOT NULL, is_read BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE TABLE packs (id SERIAL NOT NULL, nb_agents INT NOT NULL, prix NUMERIC(10, 2) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, description TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE payment (id SERIAL NOT NULL, client_id INT NOT NULL, pack_id INT NOT NULL, status VARCHAR(20) NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6D28840D19EB6921 ON payment (client_id)');
        $this->addSql('CREATE INDEX IDX_6D28840D1919B217 ON payment (pack_id)');
        $this->addSql('CREATE TABLE payment_history (id SERIAL NOT NULL, payment_id INT NOT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, provider VARCHAR(50) DEFAULT \'cybersource\' NOT NULL, amount NUMERIC(10, 2) NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, provider_response TEXT DEFAULT NULL, stripe_payment_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3EF37EA14C3A3BB ON payment_history (payment_id)');
        $this->addSql('CREATE TABLE refresh_token (id SERIAL NOT NULL, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C74F21955F37A13B ON refresh_token (token)');
        $this->addSql('CREATE INDEX IDX_C74F2195A76ED395 ON refresh_token (user_id)');
        $this->addSql('CREATE TABLE reset_password_request (id SERIAL NOT NULL, user_id INT NOT NULL, used BOOLEAN NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7CE748AA76ED395 ON reset_password_request (user_id)');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE secured_zones (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, geom geometry(POLYGON, 4326) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN secured_zones.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE service_orders (id SERIAL NOT NULL, secured_zone_id INT NOT NULL, client_id INT NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DBE8F8D8D915E713 ON service_orders (secured_zone_id)');
        $this->addSql('CREATE INDEX IDX_DBE8F8D819EB6921 ON service_orders (client_id)');
        $this->addSql('COMMENT ON COLUMN service_orders.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE tasks (id SERIAL NOT NULL, order_id INT NOT NULL, agent_id INT NOT NULL, status VARCHAR(20) NOT NULL, type VARCHAR(30) NOT NULL, description TEXT DEFAULT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, assign_position geometry(POINT, 4326) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_505865978D9F6D38 ON tasks (order_id)');
        $this->addSql('CREATE INDEX IDX_505865973414710B ON tasks (agent_id)');
        $this->addSql('COMMENT ON COLUMN tasks.end_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tasks.start_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE users (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE agent_location_significant ADD CONSTRAINT FK_5A8F7BD3414710B FOREIGN KEY (agent_id) REFERENCES agents (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE agent_location_significant ADD CONSTRAINT FK_5A8F7BD8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE agent_locations_archive ADD CONSTRAINT FK_3F3E3AC73414710B FOREIGN KEY (agent_id) REFERENCES agents (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE agent_locations_archive ADD CONSTRAINT FK_3F3E3AC78DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE agent_locations_raw ADD CONSTRAINT FK_DF2DE25DE3272D31 FOREIGN KEY (tasks_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE agent_locations_raw ADD CONSTRAINT FK_DF2DE25D3414710B FOREIGN KEY (agent_id) REFERENCES agents (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE agents ADD CONSTRAINT FK_9596AB6EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_17FD46C15E5C27E9 FOREIGN KEY (iduser) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_17FD46C1232CFF81 FOREIGN KEY (idorder) REFERENCES service_orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E968D9F6D38 FOREIGN KEY (order_id) REFERENCES service_orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D19EB6921 FOREIGN KEY (client_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D1919B217 FOREIGN KEY (pack_id) REFERENCES packs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payment_history ADD CONSTRAINT FK_3EF37EA14C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE service_orders ADD CONSTRAINT FK_DBE8F8D8D915E713 FOREIGN KEY (secured_zone_id) REFERENCES secured_zones (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE service_orders ADD CONSTRAINT FK_DBE8F8D819EB6921 FOREIGN KEY (client_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_505865978D9F6D38 FOREIGN KEY (order_id) REFERENCES service_orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_505865973414710B FOREIGN KEY (agent_id) REFERENCES agents (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SCHEMA storage');
        $this->addSql('CREATE SCHEMA auth');
        $this->addSql('CREATE SCHEMA graphql');
        $this->addSql('CREATE SCHEMA graphql_public');
        $this->addSql('CREATE SCHEMA vault');
        $this->addSql('CREATE SCHEMA topology');
        $this->addSql('CREATE SCHEMA realtime');
        $this->addSql('CREATE SCHEMA pgbouncer');
        $this->addSql('CREATE SCHEMA extensions');
        $this->addSql('CREATE SEQUENCE topology.topology_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE graphql.seq_schema_version INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('ALTER TABLE agent_location_significant DROP CONSTRAINT FK_5A8F7BD3414710B');
        $this->addSql('ALTER TABLE agent_location_significant DROP CONSTRAINT FK_5A8F7BD8DB60186');
        $this->addSql('ALTER TABLE agent_locations_archive DROP CONSTRAINT FK_3F3E3AC73414710B');
        $this->addSql('ALTER TABLE agent_locations_archive DROP CONSTRAINT FK_3F3E3AC78DB60186');
        $this->addSql('ALTER TABLE agent_locations_raw DROP CONSTRAINT FK_DF2DE25DE3272D31');
        $this->addSql('ALTER TABLE agent_locations_raw DROP CONSTRAINT FK_DF2DE25D3414710B');
        $this->addSql('ALTER TABLE agents DROP CONSTRAINT FK_9596AB6EA76ED395');
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_17FD46C15E5C27E9');
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_17FD46C1232CFF81');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E968D9F6D38');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E96F624B39D');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E96CD53EDB6');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840D19EB6921');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840D1919B217');
        $this->addSql('ALTER TABLE payment_history DROP CONSTRAINT FK_3EF37EA14C3A3BB');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F2195A76ED395');
        $this->addSql('ALTER TABLE reset_password_request DROP CONSTRAINT FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE service_orders DROP CONSTRAINT FK_DBE8F8D8D915E713');
        $this->addSql('ALTER TABLE service_orders DROP CONSTRAINT FK_DBE8F8D819EB6921');
        $this->addSql('ALTER TABLE tasks DROP CONSTRAINT FK_505865978D9F6D38');
        $this->addSql('ALTER TABLE tasks DROP CONSTRAINT FK_505865973414710B');
        $this->addSql('DROP TABLE agent_location_significant');
        $this->addSql('DROP TABLE agent_locations_archive');
        $this->addSql('DROP TABLE agent_locations_raw');
        $this->addSql('DROP TABLE agents');
        $this->addSql('DROP TABLE alert');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE packs');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE payment_history');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE secured_zones');
        $this->addSql('DROP TABLE service_orders');
        $this->addSql('DROP TABLE tasks');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
