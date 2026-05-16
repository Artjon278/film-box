<?php
// ============================================================
// FilmBox configuration
// Copy this file to config.php and fill in your real values.
// ============================================================

// --- Database (default XAMPP/Laragon values) ---
const DB_HOST = 'localhost';
const DB_NAME = 'filmbox';
const DB_USER = 'root';
const DB_PASS = '';

// --- TMDb API ---
// Get a free key at https://www.themoviedb.org/settings/api
// Supports both v3 API key and v4 Bearer token (starts with "eyJ…")
const TMDB_API_KEY  = 'PUT_YOUR_TMDB_API_KEY_HERE';
const TMDB_BASE_URL = 'https://api.themoviedb.org/3';
const TMDB_IMG_URL  = 'https://image.tmdb.org/t/p';

// --- App ---
const APP_NAME = 'FilmBox';
// Base URL path (no trailing slash). If served at http://localhost/film-box/,
// use '/film-box'. If served at the root, use ''.
const BASE_URL = '/film-box';

// --- Security ---
const SESSION_LIFETIME = 86400; // 24 hours in seconds

// Show PHP errors during development. Set false before deploying.
const DEBUG = true;
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
