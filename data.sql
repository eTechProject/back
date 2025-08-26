-- ----------------------------
-- Users
-- ----------------------------
INSERT INTO users (id, name, email, password, role, created_at) VALUES
(1, 'Aneliot', 'aneliot@example.com', '$2y$13$EXAMPL3HASH1111111111111111111111111111111111111111111', 'agent', NOW()),
(2, 'Larion', 'larion@example.com', '$2y$13$EXAMPL3HASH2222222222222222222222222222222222222222222', 'agent', NOW()),
(3, 'ClientTest', 'client@example.com', '$2y$13$zj1HkK1Q8fjHh5o9Ue2cOeGx5yLqBxCgTzXK1lDxeJ7K2M0Gf8nPq', 'client', NOW());

-- ----------------------------
-- Agents
-- ----------------------------
INSERT INTO agents (id, user_id, sexe, address) VALUES
(1, 1, 'M', 'Antananarivo'),
(2, 2, 'M', 'Antsirabe');

-- ----------------------------
-- Secured Zones
-- ----------------------------
INSERT INTO secured_zones (id, name, geom, created_at) VALUES
(1, 'Zone Industrielle', ST_GeomFromText('POLYGON((0 0,0 1,1 1,1 0,0 0))',4326), NOW()),
(2, 'Zone Résidentielle', ST_GeomFromText('POLYGON((1 1,1 2,2 2,2 1,1 1))',4326), NOW());

-- ----------------------------
-- Service Orders
-- ----------------------------
INSERT INTO service_orders (id, secured_zone_id, client_id, description, status, created_at) VALUES
(1, 1, 3, 'Surveillance zone industrielle', 'pending', NOW()),
(2, 2, 3, 'Surveillance zone résidentielle', 'pending', NOW());

-- ----------------------------
-- Tasks
-- ----------------------------
INSERT INTO tasks (id, order_id, agent_id, status, description, start_date, assign_position) VALUES
(1, 1, 1, 'pending', 'Patrouille 1', NOW(), ST_GeomFromText('POINT(0.5 0.5)',4326)),
(2, 1, 2, 'pending', 'Patrouille 2', NOW(), ST_GeomFromText('POINT(0.5 0.6)',4326));

-- ----------------------------
-- Packs
-- ----------------------------
INSERT INTO packs (id, nb_agents, prix, date_creation, description) VALUES
(1, 2, 99.99, NOW(), 'Pack de surveillance de base'),
(2, 3, 149.99, NOW(), 'Pack de surveillance avancé');

-- ----------------------------
-- Payment
-- ----------------------------
INSERT INTO payment (id, client_id, pack_id, status, start_date, created_at, updated_at) VALUES
(1, 3, 1, 'actif', NOW(), NOW(), NOW());

-- ----------------------------
-- Payment History
-- ----------------------------
INSERT INTO payment_history (id, payment_id, date, provider, amount, status, created_at) VALUES
(1, 1, NOW(), 'cybersource', 99.99, 'success', NOW());
