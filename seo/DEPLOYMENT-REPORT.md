# Deployment and verification report

Review branch: `fix/google-indexing-audit`.
Baseline production HTML: `a68ba1b5bf4ec37ea2afb489756e958983dae197` (byte-for-byte homepage comparison at audit time).
Implementation commit: recorded after review-branch publication below.

## Status

| Stage | Status |
| --- | --- |
| Local implementation | Complete for focused canonical, routing, metadata and sitemap remedies |
| Full React prerendering | Not implemented; original source/build required |
| Automated local verification | 197 HTTP assertions, 54 PHP inquiry checks, brand-schema checks, JS syntax, XML and generator idempotency passed |
| Browser verification | Eight desktop routes and four mobile routes checked; desktop/mobile home screenshots; mobile menu/section navigation passed; no captured console errors or broken loaded images |
| New changes on main | No |
| New changes deployed | No; approval required |
| Existing live version independently checked | Yes; 28 HTTP GET samples and five Google query samples |
| Google indexing confirmed via Search Console | No; current account lacks Oyonx property access |

## Files and behavior

- `.htaccess`: permanent www redirect, known-route mapping, section alias redirects, missing-page 404, generated-path denial and origin cache revalidation.
- `index.html`, `seo-pages/*.html`: matching original-response and rendered route metadata, singular existing brand graph, explicit non-JS fallback.
- `404.html`: real missing-page response document with noindex.
- `seo/routes.json`, `scripts/build-seo-pages.mjs`: editable metadata map and dependency-free generator for 12 distinct routes, sitemap and routing rules.
- `sitemap.xml`: only distinct canonical pages; no unverifiable lastmod values.
- `assets/*.js`: scoped metadata/canonical/navbar edits and revised inter-module names. CSS, images, PHP handler and inquiry helpers are unchanged.
- `tests/seo-http.mjs`: HTTP-level route, canonical, redirect, error, asset, access-control and sitemap regressions.
- `scripts/audit-live-seo.mjs`: repeatable live GET audit; writes raw captures and summary under `/private/tmp/oyonx-live-audit`.
- `seo/GOOGLE-INDEXING-ROOT-CAUSE.md`, `seo/evidence/*`: findings, observed evidence, screenshots, limitations and manual actions.

## Reproduction

```sh
node scripts/update-brand-schema.mjs
node scripts/build-seo-pages.mjs
node tests/brand-schema.mjs
xmllint --noout sitemap.xml
php -l send-inquiry.php
php -l server/inquiry.php
php tests/inquiry.php
node tests/seo-http.mjs http://127.0.0.1:4174
node scripts/audit-live-seo.mjs
```

For HTTP tests serve a temporary copy with Apache `mod_rewrite`, `mod_authz_core`, `mod_dir`, `mod_mime`, `mod_headers` and AllowOverride All; do not use an unconditional Node SPA fallback to test Apache rules. The host's actual PHP mail transport needs a separate controlled delivery check. PHP validation was executed with the cached PHP WASM CLI because native PHP is absent. The Apache preview does not execute PHP; it is not evidence for PHP HTTP transport.

The baseline and generated sitemaps parsed with xmllint. All 197 HTTP assertions passed against Apache. The existing 54 PHP tests sent no email. The generator produced identical outputs on a second run. Browser checks found existing duplicate H1s on blog index/articles (hidden SEO H1 plus visible heading); this is a low-priority source cleanup, not evidence of an indexing block. No layout/style changes were made to address it.

## Approval and staging plan

Do not merge or deploy until the owner approves this branch. The request explicitly requires approval before merging into main or production deployment.

1. Confirm the actual Hostinger document root and deployment method. No working deployment credentials or configured deployment workflow were available in this session.
2. Use a staging copy first. Upload the complete branch, including `.htaccess`, all generated `seo-pages` files and current JS assets together. Keep the previous version for rollback.
3. Verify the edge's existing HTTP-to-HTTPS redirect and the new www-to-apex rule. The current CDN redirects HTTP www to HTTPS www first; origin rules alone cannot prove a one-hop edge chain. If two hops remain, consolidate the redirect at Hostinger's edge with separate infrastructure authorization. Never trust arbitrary X-Forwarded-Proto headers to bypass HTTPS checks.
4. Purge/revalidate the affected HTML/sitemap CDN cache if needed through the existing hosting workflow. Origin no-cache headers can be overridden by CDN configuration.
5. Check all four domain variants, every sitemap URL, query-string preservation, trailing-slash normalization, unknown-page 404s, real JS/CSS content types, PHP GET=405, and one controlled enquiry receipt.
6. Confirm route-specific canonicals in both raw response and rendered DOM. Re-run browser navigation/mobile checks and verify no JS errors.
7. Recover original source and implement genuine route prerendering separately before considering the architecture acceptance criteria fully met.

No DNS, nameserver, MX, mailbox, certificate or hosting infrastructure configuration was modified. The feature branch must not be wired to production automatically during review.

## Search Console checklist after live verification

1. Inspect `https://oyonx.com/` in the verified Oyonx property.
2. Run Test Live URL; confirm crawling and indexing are allowed, resources load and intended content renders.
3. In the indexed view, record Google-selected canonical, declared canonical and last crawl; live testing does not supply a new Google-selected canonical immediately.
4. Request indexing when the live URL is correct and eligible.
5. Submit/resubmit `https://oyonx.com/sitemap.xml`; verify processing and discovered URLs.
6. Inspect all three service pages, About, Blog and representative articles; request indexing selectively after successful live tests.
7. Monitor Page Indexing exclusion categories and canonical decisions after recrawling.
8. Monitor branded impressions/clicks segmented by country/device over time.
9. Reassess after Google processes the changes; do not repeatedly change technical signals merely because results have not changed immediately.

Neither sitemap submission nor an indexing request guarantees indexing, rankings, site-name selection, a Knowledge Panel, or removal of spelling corrections.
