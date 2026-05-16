-- FilmBox database schema
-- Run this in phpMyAdmin or MySQL CLI to create the database

CREATE DATABASE IF NOT EXISTS filmbox
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE filmbox;

-- =====================================================
-- USERS
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  avatar        VARCHAR(255) DEFAULT NULL,
  bio           TEXT         DEFAULT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- =====================================================
-- MOVIES CACHE — local snapshot of TMDb data to reduce API calls
-- =====================================================
CREATE TABLE IF NOT EXISTS movies_cache (
  tmdb_id        INT PRIMARY KEY,
  title          VARCHAR(255) NOT NULL,
  original_title VARCHAR(255),
  poster_path    VARCHAR(255),
  backdrop_path  VARCHAR(255),
  overview       TEXT,
  release_date   DATE,
  runtime        INT,
  vote_average   DECIMAL(3,1),
  genres         VARCHAR(500),
  cached_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- USER_MOVIES — user's personal tracking (want / watching / watched / dropped)
-- =====================================================
CREATE TABLE IF NOT EXISTS user_movies (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  tmdb_id    INT NOT NULL,
  status     ENUM('want','watching','watched','dropped') NOT NULL DEFAULT 'want',
  rating     TINYINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_movie (user_id, tmdb_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_status (user_id, status),
  CONSTRAINT chk_rating CHECK (rating IS NULL OR (rating BETWEEN 1 AND 10))
) ENGINE=InnoDB;

-- =====================================================
-- REVIEWS — written reviews by users
-- =====================================================
CREATE TABLE IF NOT EXISTS reviews (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  tmdb_id    INT NOT NULL,
  content    TEXT NOT NULL,
  rating     TINYINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_movie (tmdb_id),
  INDEX idx_user (user_id),
  CONSTRAINT chk_review_rating CHECK (rating IS NULL OR (rating BETWEEN 1 AND 10))
) ENGINE=InnoDB;

-- =====================================================
-- CUSTOM_LISTS — user-created lists (e.g. "Top 10 Horror 2024")
-- =====================================================
CREATE TABLE IF NOT EXISTS custom_lists (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  name        VARCHAR(100) NOT NULL,
  description TEXT,
  is_public   BOOLEAN DEFAULT TRUE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS list_items (
  list_id  INT NOT NULL,
  tmdb_id  INT NOT NULL,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (list_id, tmdb_id),
  FOREIGN KEY (list_id) REFERENCES custom_lists(id) ON DELETE CASCADE
) ENGINE=InnoDB;
