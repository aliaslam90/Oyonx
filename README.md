# Oyonx website

Production website snapshot for https://oyonx.com.

## Contents

- index.html and assets/: compiled React frontend.
- send-inquiry.php: inquiry endpoint using PHP mail().
- robots.txt and sitemap.xml: search engine configuration.

## Hosting

Serve this folder as the document root on a PHP-enabled host. Configure client-side route fallback to index.html for requests that do not match existing files or directories. GitHub Pages cannot execute the PHP inquiry endpoint.

## Development

This snapshot does not include the original React source, package manifest, or build configuration. Obtain the source project before making maintainable frontend changes. Do not edit hashed bundles as a normal development workflow.

## Review (15 October 2026)

- Main JavaScript bundles pass Node syntax checks; scanned asset references resolve locally.
- Live homepage renders, and no browser warnings or errors were captured in the initial review.
- Follow-up: add the original frontend source and build instructions.
- Follow-up: provide server route fallback configuration for deployment.
- Follow-up: add request size limits, field type/length validation, and rate limiting to the inquiry endpoint.
- Copy edits: “Our Clients Says It All” should be “Our Clients Say It All”; “A SNEAK PEAK” should be “A SNEAK PEEK”; footer copyright currently says 2025.
- Inquiry delivery was not tested because submitting it would send email. PHP runtime validation was unavailable locally.
