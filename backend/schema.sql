-- ============================================================
-- INVENTRA · Esquema multi-empresa
-- Borra y recrea TODO. Si quieres conservar datos, usa migration.sql
-- ============================================================

DROP DATABASE IF EXISTS Inventra;
CREATE DATABASE Inventra CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE Inventra;

-- ------------------------------------------------------------
-- 1) USERS
-- role:
--   admin   -> dueño de UNA empresa (la crea al registrarse)
--   manager -> empleado con permisos de gestión (necesita invitación)
--   user    -> empleado básico (necesita invitación)
-- company_id NULL = usuario sin empresa (esperando invitación)
-- ------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','user') NOT NULL DEFAULT 'user',
    company_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_email (email)
);

-- ------------------------------------------------------------
-- 2) COMPANIES
-- Cada empresa tiene un único admin (su creador).
-- ------------------------------------------------------------
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    admin_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id)
);

ALTER TABLE users
    ADD CONSTRAINT fk_users_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- 3) INVITATIONS
-- El admin invita por email + rol. Cuando ese email se registra
-- o inicia sesión, se aplica automáticamente la invitación.
-- ------------------------------------------------------------
CREATE TABLE invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('manager','user') NOT NULL DEFAULT 'user',
    status ENUM('pending','accepted','revoked') NOT NULL DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_email_company (email, company_id),
    INDEX idx_email (email),
    INDEX idx_status (status)
);

-- ------------------------------------------------------------
-- 4) PRODUCTS
-- Pertenecen a la EMPRESA (company_id).
-- user_id = creador (auditoría).
-- ------------------------------------------------------------
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    image_url VARCHAR(500) NULL,
    price DECIMAL(10,2) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    category VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_company (company_id),
    INDEX idx_category (category),
    INDEX idx_stock (stock)
);

-- ------------------------------------------------------------
-- 5) SALES
-- Pertenecen a la EMPRESA y al vendedor.
-- ------------------------------------------------------------
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_company (company_id),
    INDEX idx_product (product_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);

SHOW TABLES;
