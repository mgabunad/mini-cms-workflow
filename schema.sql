-- Mini CMS Workflow — database schema and seed data
-- Import this in phpMyAdmin: Import tab > choose this file > Go
-- All seed users have the password: password123

DROP DATABASE IF EXISTS cms_practice;
CREATE DATABASE cms_practice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cms_practice;

-- 1. Roles (admin, editor, designer, client)
CREATE TABLE roles (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- 2. Permissions (what someone is allowed to do)
CREATE TABLE permissions (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
);

-- 3. Which role has which permission (many-to-many)
CREATE TABLE role_permissions (
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- 4. Clients (the businesses the agency works for)
CREATE TABLE clients (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- 5. Users (staff have client_id NULL; client users are tied to one client)
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role_id    INT NOT NULL,
    client_id  INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

-- 6. Content items that move through the workflow
--    draft -> in_review -> approved -> published   (reject sends it back to draft)
CREATE TABLE content_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    client_id  INT NOT NULL,
    title      VARCHAR(200) NOT NULL,
    body       TEXT,
    status     ENUM('draft','in_review','approved','published') NOT NULL DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 7. History log: who moved what, and when
CREATE TABLE content_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    user_id    INT NOT NULL,
    action     VARCHAR(50) NOT NULL,
    note       VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES content_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 8. Invoices for published content (one invoice per item)
CREATE TABLE invoices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    content_id  INT NOT NULL UNIQUE,
    amount      DECIMAL(10,2) NOT NULL,
    status      ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at     TIMESTAMP NULL,
    FOREIGN KEY (client_id)  REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content_items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ---------- Seed data ----------

INSERT INTO roles (id, name) VALUES
(1, 'admin'), (2, 'editor'), (3, 'designer'), (4, 'client');

INSERT INTO permissions (id, code, label) VALUES
(1, 'content.view_all', 'View content for all clients'),
(2, 'content.create',   'Create and edit drafts'),
(3, 'content.submit',   'Submit drafts for review'),
(4, 'content.approve',  'Approve or reject content'),
(5, 'content.publish',  'Publish approved content'),
(6, 'users.manage',     'Manage users and roles'),
(7, 'invoices.manage',  'Create invoices and mark them paid');

-- admin: everything
INSERT INTO role_permissions (role_id, permission_id) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7);
-- editor: view all, create, submit, approve, publish
INSERT INTO role_permissions (role_id, permission_id) VALUES
(2,1),(2,2),(2,3),(2,4),(2,5);
-- designer: view all, create, submit
INSERT INTO role_permissions (role_id, permission_id) VALUES
(3,1),(3,2),(3,3);
-- client: approve (only their own client's items, enforced in code)
INSERT INTO role_permissions (role_id, permission_id) VALUES
(4,4);

INSERT INTO clients (id, name) VALUES
(1, 'Grace Community Church'),
(2, 'Hoosier Coffee Co.');

-- password for everyone: password123
INSERT INTO users (id, name, email, password, role_id, client_id) VALUES
(1, 'Admin User',    'admin@test.com',    '$2y$12$KAp.I8nTIYoW7UxbVbK9zOWN.NRwDPpxQPgR5pBT9NTHKRMKjp8H.', 1, NULL),
(2, 'Ella Editor',   'editor@test.com',   '$2y$12$KAp.I8nTIYoW7UxbVbK9zOWN.NRwDPpxQPgR5pBT9NTHKRMKjp8H.', 2, NULL),
(3, 'Dan Designer',  'designer@test.com', '$2y$12$KAp.I8nTIYoW7UxbVbK9zOWN.NRwDPpxQPgR5pBT9NTHKRMKjp8H.', 3, NULL),
(4, 'Pastor Mike',   'church@test.com',   '$2y$12$KAp.I8nTIYoW7UxbVbK9zOWN.NRwDPpxQPgR5pBT9NTHKRMKjp8H.', 4, 1);

INSERT INTO content_items (client_id, title, body, status, created_by) VALUES
(1, 'Sunday Service Reminder', 'Join us this Sunday at 10 AM!', 'draft', 3),
(1, 'Youth Night Poster',      'Friday 7 PM, pizza included.',  'in_review', 3),
(2, 'Fall Latte Launch',       'Pumpkin spice is back.',         'approved', 2);
