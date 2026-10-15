# Oyonx website

Production website snapshot for https://oyonx.com.

## Contents

- `index.html` and `assets/`: compiled React frontend.
- `send-inquiry.php` and `server/inquiry.php`: inquiry endpoint and validation/throttling logic.
- `.htaccess`: Apache/LiteSpeed SPA routing and access restrictions.
- `robots.txt` and `sitemap.xml`: search engine configuration.
- `tests/inquiry.php`: validation and rate-limit regression checks; no email is sent.

## Hosting

Upload the website, including all `.htaccess` files and the `server/` directory, to the document root on an Apache/LiteSpeed host with PHP 7.4+ and PHP `mail()` configured. Apache needs `mod_rewrite`, authorization support, and `AllowOverride All` (or equivalent permitted directives). GitHub Pages cannot execute the PHP inquiry endpoint.

The root `.htaccess` serves route-specific metadata shells for known clean routes, preserves existing files and PHP endpoints, and returns HTTP 404 for unknown routes and missing assets. Five homepage-section aliases redirect to homepage fragments and are excluded from the sitemap. It disables directory listings and denies access to repository metadata, generated-shell URLs, development documentation, server helpers, SEO evidence, scripts and tests. Nginx hosts must translate these rules into their server configuration; `.htaccess` is not read by Nginx.

The PHP process needs a writable system temporary directory. Rate-limit state is stored in a private, site-specific directory there, outside the document root. The limit is five POST attempts per peer IP per ten minutes, including invalid requests; the sixth returns HTTP 429 with `Retry-After`. Storage failures return 503 instead of bypassing throttling. This store is suitable for one hosting instance; multiple instances need shared rate-limit storage. When behind a reverse proxy, configure the web server to supply a trustworthy client `REMOTE_ADDR`; the endpoint deliberately ignores forwarded headers.

Requests must use `Content-Type: application/json` and fit within 16 KiB. Fields must be strings: name ≤200 bytes, email ≤254, phone ≤50, project details (`vision`) ≤10,000 and honeypot (`honey`) ≤200. Name and email are required for legitimate enquiries. Unicode names and multiline project details are accepted; header/control-character injection is rejected.

## Verification

With PHP installed:

```sh
php -l send-inquiry.php
php -l server/inquiry.php
php tests/inquiry.php
```

All 54 inquiry checks passed using the isolated PHP WASM CLI on 15 October 2026. All 11 Apache routing, asset and access checks passed. JavaScript syntax checks, corrected homepage copy and a lazy-loaded service page also passed in the local browser. The corrected frontend uses a new asset filename, with lazy-loaded module references updated, to avoid stale cached copy. Footer copyright uses the current year automatically.

## Brand entity SEO

The linked `WebSite` and `Organization` / `ProfessionalService` graph is embedded directly in the HTML head and remains present across client-side routes. Its editable source is `seo/brand-entity.json`. After changing it, run `node scripts/update-brand-schema.mjs`, `node scripts/build-seo-pages.mjs`, then `node tests/brand-schema.mjs`. Route metadata comes from `seo/routes.json`. The generator also updates the sitemap and Apache route rules; it does not rebuild React or prerender its full content.

See `seo/GOOGLE-INDEXING-ROOT-CAUSE.md` and `seo/DEPLOYMENT-REPORT.md` for current evidence, test commands, unresolved rendering/source limitations and the approval requirement before merging or deploying. `seo/AUDIT.md` records the previous brand-schema audit.

## Source limitation

The supplied snapshot contains no original React source, source maps, package manifest or build configuration. Nearby Oyonx folders checked did not contain this source either. The copy corrections were applied narrowly to the compiled bundle; this is not a reconstructed source project. Obtain the original source project before substantial frontend development. No valid React rebuild command can be supplied until that project is available.

Live deployment and delivery to the actual inquiry mailbox have not been verified by the local tests. After deploying, check a direct route reload and make one controlled inquiry to confirm hosting mail configuration and receipt.
