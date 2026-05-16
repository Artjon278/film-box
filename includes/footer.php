<footer class="site-footer">
    <div class="footer-bg-grain"></div>

    <div class="footer-main">
        <div class="footer-col footer-brand">
            <a href="<?= e(BASE_URL) ?>/" class="nav-logo" style="margin-bottom:1rem;">
                <span class="dot"></span>
                <?= e(APP_NAME) ?>
            </a>
            <p class="footer-tag">
                Track films you love. Rate them. Write reviews.
                Built as a university project — open, fast, ad-free.
            </p>
            <div class="footer-credit">
                <span class="footer-credit-label">DATA BY</span>
                <a href="https://www.themoviedb.org/" target="_blank" rel="noopener" class="tmdb-mark" title="The Movie Database">
                    <svg viewBox="0 0 273 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <defs>
                            <linearGradient id="tmdbg" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#90cea1"/>
                                <stop offset="56%" stop-color="#3cbec9"/>
                                <stop offset="100%" stop-color="#00b3e5"/>
                            </linearGradient>
                        </defs>
                        <rect x="0" y="60" rx="20" ry="20" width="273" height="80" fill="url(#tmdbg)"/>
                        <text x="50%" y="110" text-anchor="middle" fill="#0a0a0a"
                              font-family="Helvetica, Arial, sans-serif" font-weight="900" font-size="44"
                              letter-spacing="2">TMDB</text>
                    </svg>
                </a>
            </div>
        </div>

        <div class="footer-col">
            <h4>Explore</h4>
            <ul>
                <li><a href="<?= e(BASE_URL) ?>/">Home</a></li>
                <li><a href="<?= e(BASE_URL) ?>/search.php">Discover</a></li>
                <li><a href="<?= e(BASE_URL) ?>/search.php?q=trending">Trending</a></li>
                <li><a href="<?= e(BASE_URL) ?>/landing-demo.html">Original demo</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Your library</h4>
            <ul>
                <?php if (!empty($_current)): ?>
                    <li><a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/lists.php">My lists</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?= e(BASE_URL) ?>/login.php">Log in</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/register.php">Sign up</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Genres</h4>
            <ul>
                <li><a href="<?= e(BASE_URL) ?>/search.php?q=Drama">Drama</a></li>
                <li><a href="<?= e(BASE_URL) ?>/search.php?q=Action">Action</a></li>
                <li><a href="<?= e(BASE_URL) ?>/search.php?q=Horror">Horror</a></li>
                <li><a href="<?= e(BASE_URL) ?>/search.php?q=Comedy">Comedy</a></li>
                <li><a href="<?= e(BASE_URL) ?>/search.php?q=Animation">Animation</a></li>
            </ul>
        </div>

        <div class="footer-col footer-stay">
            <h4>Stay in the loop</h4>
            <p>One email a week. Top-rated films, hand-picked. No spam.</p>
            <form class="footer-newsletter" onsubmit="event.preventDefault(); this.querySelector('button').textContent='SUBSCRIBED ✓'; this.querySelector('button').classList.add('done'); this.querySelector('input').value='';">
                <input type="email" placeholder="your@email.com" required>
                <button type="submit">SUBSCRIBE</button>
            </form>
        </div>
    </div>

    <div class="footer-bar">
        <div class="footer-bar-left">
            <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></span>
            <span class="footer-sep">·</span>
            <span>University project, <?= date('Y') ?></span>
        </div>
        <div class="footer-bar-right">
            <a href="#" title="Twitter" aria-label="Twitter">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </a>
            <a href="#" title="GitHub" aria-label="GitHub">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.4 3-.405 1.02.005 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
            </a>
            <a href="#" title="RSS" aria-label="RSS">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M4 11v2c4.97 0 9 4.03 9 9h2c0-6.075-4.925-11-11-11zm0-4v2c8.284 0 15 6.716 15 15h2C21 13.611 13.389 6 4 6zm2 11c-1.105 0-2 .895-2 2s.895 2 2 2 2-.895 2-2-.895-2-2-2z"/></svg>
            </a>
        </div>
    </div>
</footer>

<style>
.site-footer {
    position: relative;
    background: linear-gradient(180deg, var(--deep) 0%, #050505 100%);
    border-top: 1px solid var(--border);
    margin-top: 6rem;
    overflow: hidden;
}
.site-footer::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--amber), transparent);
}
.footer-bg-grain {
    position: absolute;
    inset: 0;
    opacity: 0.025;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    background-size: 200px;
}

.footer-main {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1.4fr;
    gap: 3rem;
    padding: 4.5rem 3rem 3rem;
    max-width: 1400px;
    margin: 0 auto;
}

.footer-col h4 {
    font-family: var(--font-display);
    font-size: 0.78rem;
    letter-spacing: 0.3em;
    color: var(--ivory);
    margin-bottom: 1.3rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--border);
}

.footer-col ul { list-style: none; padding: 0; }
.footer-col li { margin-bottom: 0.7rem; }
.footer-col li a {
    font-size: 0.88rem;
    color: var(--muted);
    text-decoration: none;
    transition: color 0.2s, padding 0.2s;
    display: inline-block;
}
.footer-col li a:hover {
    color: var(--amber);
    padding-left: 0.3rem;
}

.footer-brand .nav-logo { margin-bottom: 1.2rem !important; }
.footer-tag {
    font-size: 0.9rem;
    color: var(--muted);
    line-height: 1.7;
    max-width: 320px;
    margin-bottom: 1.5rem;
}

.footer-credit {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.footer-credit-label {
    font-family: var(--font-display);
    font-size: 0.65rem;
    letter-spacing: 0.3em;
    color: var(--muted);
}
.tmdb-mark {
    display: inline-block;
    width: 100px;
    opacity: 0.85;
    transition: opacity 0.2s, transform 0.2s;
}
.tmdb-mark:hover { opacity: 1; transform: translateY(-1px); }

.footer-stay p {
    font-size: 0.85rem;
    color: var(--muted);
    line-height: 1.6;
    margin-bottom: 1rem;
}
.footer-newsletter { display: flex; }
.footer-newsletter input {
    flex: 1;
    padding: 0.7rem 0.9rem;
    font-family: var(--font-body);
    font-size: 0.82rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-right: none;
    color: var(--text-bright);
    outline: none;
    min-width: 0;
}
.footer-newsletter input::placeholder { color: var(--muted); }
.footer-newsletter input:focus { border-color: var(--amber-dim); }
.footer-newsletter button {
    padding: 0.7rem 1rem;
    font-family: var(--font-display);
    font-size: 0.7rem;
    letter-spacing: 0.18em;
    color: var(--black);
    background: var(--amber);
    border: 1px solid var(--amber);
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.footer-newsletter button:hover {
    background: var(--amber-dim);
    box-shadow: 0 0 20px var(--amber-glow);
}
.footer-newsletter button.done {
    background: #2a6e3f;
    border-color: #2a6e3f;
    color: #fff;
    box-shadow: none;
    cursor: default;
}

.footer-bar {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.4rem 3rem;
    border-top: 1px solid var(--border);
    max-width: 1400px;
    margin: 0 auto;
    font-size: 0.78rem;
    color: var(--muted);
    flex-wrap: wrap;
    gap: 1rem;
}
.footer-bar-left { display: flex; align-items: center; gap: 0.7rem; flex-wrap: wrap; }
.footer-sep { color: var(--amber-dim); }
.footer-bar-right { display: flex; gap: 0.7rem; }
.footer-bar-right a {
    width: 34px; height: 34px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid var(--border);
    border-radius: 50%;
    color: var(--muted);
    transition: all 0.2s;
}
.footer-bar-right a:hover {
    border-color: var(--amber);
    color: var(--amber);
    transform: translateY(-2px);
}

@media (max-width: 1024px) {
    .footer-main { grid-template-columns: 2fr 1fr 1fr; gap: 2.5rem; }
    .footer-stay { grid-column: 1 / -1; max-width: 480px; }
}
@media (max-width: 700px) {
    .footer-main {
        grid-template-columns: 1fr 1fr;
        padding: 3rem 1.5rem 2rem;
        gap: 2rem;
    }
    .footer-brand { grid-column: 1 / -1; }
    .footer-stay { grid-column: 1 / -1; }
    .footer-bar { padding: 1.2rem 1.5rem; flex-direction: column; text-align: center; }
}
</style>

</body>
</html>
