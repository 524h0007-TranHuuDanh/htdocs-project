CREATE DATABASE IF NOT EXISTS note_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE note_management;

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
    font_size VARCHAR(10) DEFAULT '16px',
    theme_color VARCHAR(10) DEFAULT 'light',
    note_color VARCHAR(20) DEFAULT '#ffffff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    content TEXT,
    is_pinned TINYINT(1) DEFAULT 0,
    pinned_at DATETIME DEFAULT NULL,
    is_trashed TINYINT(1) DEFAULT 0,
    color VARCHAR(20) DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE note_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
);

CREATE TABLE labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE note_labels (
    note_id INT NOT NULL,
    label_id INT NOT NULL,
    PRIMARY KEY (note_id, label_id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
);

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