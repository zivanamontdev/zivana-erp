-- Zivana ERP — Skema Database
-- Lihat cookbook/schema.md untuk dokumentasi lengkap tiap tabel dan relasinya.
-- File ini ditambah bertahap per fase implementasi (lihat cookbook/todo.md).
--
-- Fase 0-1: Auth & RBAC (roles, permissions, role_permissions, users, password_resets)

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================================================
-- AUTH & RBAC
-- =========================================================

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modul VARCHAR(50) NOT NULL,
    section VARCHAR(100) NULL,
    sub_section VARCHAR(100) NULL,
    aksi ENUM('lihat', 'edit') NOT NULL,
    display_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- karyawan_id sengaja tanpa FOREIGN KEY dulu: tabel karyawan baru dibuat
-- di Fase 4 (Human Capital). Constraint FK ditambahkan lewat file di
-- database/migrations/ setelah tabel karyawan tersedia.
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    karyawan_id INT NULL,
    role_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    remember_token VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed 4 role tetap, dikonfirmasi dari layar Sistem -> RBAC
-- (lihat cookbook/design-system.md bagian 5.3). Idempotent lewat
-- UNIQUE KEY uq_roles_nama di atas.
INSERT INTO roles (nama) VALUES
    ('Superadmin'),
    ('Admin'),
    ('Koordinator Guru'),
    ('Guru')
ON DUPLICATE KEY UPDATE nama = VALUES(nama);
