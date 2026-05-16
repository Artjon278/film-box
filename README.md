# FilmBox

A web app for tracking films, writing reviews, and building personal watchlists.
University project — built with **PHP + MySQL** on the backend and the
**TMDb API** for movie data.

## Stack

| Layer    | Technology                         |
|----------|------------------------------------|
| Frontend | HTML, CSS, vanilla JavaScript      |
| Backend  | PHP 8.x                            |
| Database | MySQL / MariaDB                    |
| API      | [TMDb](https://www.themoviedb.org/)|

## Project structure

```
film-box/
├── index.html            ← Landing page (static demo for now)
├── register.php          ← Account creation
├── login.php             ← Authentication
├── logout.php
├── dashboard.php         ← User's tracker (requires login)
├── includes/
│   ├── config.php        ← DB + TMDb key + base URL
│   ├── db.php            ← PDO connection (singleton)
│   ├── auth.php          ← Sessions, login/logout, rate limit
│   ├── csrf.php          ← CSRF token helpers
│   ├── tmdb.php          ← TMDb API wrapper (search, details, trending)
│   ├── header.php        ← Shared top nav
│   └── footer.php
├── assets/
│   └── css/style.css     ← Shared styles (dark + amber palette)
└── sql/
    └── schema.sql        ← Full database schema
```

## Local setup (XAMPP / Laragon)

1. **Copy this folder** into your web server's document root, e.g.
   `C:\xampp\htdocs\film-box\`.

2. **Create the database.** Open phpMyAdmin (or MySQL CLI) and run
   `sql/schema.sql`. This creates the `filmbox` database and all tables.

3. **Get a TMDb API key.** Sign up free at
   <https://www.themoviedb.org/signup>, then create a key at
   <https://www.themoviedb.org/settings/api>.

4. **Configure.** Open `includes/config.php` and set:
   - `TMDB_API_KEY` — your TMDb key
   - `DB_USER` / `DB_PASS` — your MySQL credentials (defaults are XAMPP-friendly)
   - `BASE_URL` — `'/film-box'` if served at `http://localhost/film-box/`

5. **Visit** <http://localhost/film-box/> in your browser.

## Security notes (already implemented)

- **Passwords** hashed with `password_hash()` (bcrypt). Never stored plaintext.
- **SQL injection** prevented via PDO prepared statements everywhere.
- **CSRF** tokens on every POST form, verified with `hash_equals`.
- **XSS** mitigated by escaping all dynamic output with `e()` (htmlspecialchars).
- **Session fixation** prevented by `session_regenerate_id()` on login.
- **Rate limiting** on the login form (5 attempts / 15 min per IP).
- **HttpOnly + SameSite** cookies via `session_set_cookie_params`.

## Roadmap

- [x] Auth (register, login, logout)
- [x] Database schema
- [x] TMDb wrapper
- [ ] Movie search page
- [ ] Movie detail page (trailer, cast, watch providers)
- [ ] Add / remove from personal list with status (want/watching/watched/dropped)
- [ ] Reviews + ratings
- [ ] Statistics dashboard with Chart.js
- [ ] Custom user-created lists
- [ ] Recommendations ("Because you watched X")

## Team

- Person A — Auth, security, dashboard, statistics
- Person B — TMDb integration, search, movie details, reviews
