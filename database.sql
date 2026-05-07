-- ============================================================
-- NoteApp Pro - Database Schema (ĐÃ SỬA LỖI)
-- Sửa: thêm các cột bị thiếu trong notes, chuẩn hóa tên cột users
-- ============================================================

CREATE DATABASE IF NOT EXISTS note_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE note_management;

-- ============================================================
-- Bảng Users
-- SỬA: đổi 'theme' -> 'theme_color', 'font_size' default -> '16px'
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    activation_token VARCHAR(100),
    is_activated TINYINT(1) DEFAULT 0,
    reset_token VARCHAR(100) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    font_size VARCHAR(10) DEFAULT '16px',       -- SỬA: đổi 'medium' -> '16px'
    theme_color VARCHAR(10) DEFAULT 'light',     -- SỬA: đổi tên cột 'theme' -> 'theme_color'
    note_color VARCHAR(20) DEFAULT '#ffffff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Bảng Notes
-- SỬA: thêm is_trashed, color, password_hash, pinned_at
-- ============================================================
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    content TEXT,
    is_pinned TINYINT(1) DEFAULT 0,
    pinned_at DATETIME DEFAULT NULL,            -- THÊM MỚI
    is_trashed TINYINT(1) DEFAULT 0,            -- THÊM MỚI: soft-delete
    color VARCHAR(20) DEFAULT NULL,             -- THÊM MỚI: màu nền ghi chú
    password_hash VARCHAR(255) DEFAULT NULL,    -- THÊM MỚI: thay note_password
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Note Images
-- ============================================================
CREATE TABLE note_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Labels
-- ============================================================
CREATE TABLE labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Note_Labels (N-N)
-- ============================================================
CREATE TABLE note_labels (
    note_id INT NOT NULL,
    label_id INT NOT NULL,
    PRIMARY KEY (note_id, label_id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Shared Notes
-- ============================================================
CREATE TABLE shared_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    owner_id INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    permission ENUM('read', 'edit') DEFAULT 'read',
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Script ALTER TABLE nếu database đã tồn tại (chạy thay vì tạo mới)
-- ============================================================
-- ALTER TABLE users
--   CHANGE COLUMN theme theme_color VARCHAR(10) DEFAULT 'light',
--   MODIFY COLUMN font_size VARCHAR(10) DEFAULT '16px';

-- ALTER TABLE notes
--   ADD COLUMN IF NOT EXISTS is_trashed TINYINT(1) DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS color VARCHAR(20) DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS pinned_at DATETIME DEFAULT NULL;

-- UPDATE notes SET is_trashed = 0 WHERE is_trashed IS NULL;