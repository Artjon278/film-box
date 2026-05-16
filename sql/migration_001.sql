-- Migration 001: features for Phase 2 (follows, perf indexes)
-- Run this once in phpMyAdmin after the initial schema.sql.

USE filmbox;

-- Follows (social): one row per (follower → followed) pair
CREATE TABLE IF NOT EXISTS follows (
    follower_id INT NOT NULL,
    followed_id INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followed_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_followed (followed_id),
    INDEX idx_follower (follower_id)
) ENGINE=InnoDB;
