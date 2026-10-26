# Plugins integration — local review

The page is integrated at /plugins in the existing React router and shared layout. Desktop and mobile menus use the same navigation renderer; Plugins is between Portfolio and Plans, with an active state. The existing footer also links to Plugins. Existing home-section navigation remains unchanged.

## Design source

The actual Oyonx header, SVG wordmark, scroll progress, shrinking floating navigation, footer, and React runtime are reused. The page loads the original assets/index-DgpSWuTr.css. Its heading styles, Inter/Space Grotesk fonts, black/white/neutral colors, round buttons, borders, cards, spacing and motion settings come from the existing homepage and About modules. The small additional stylesheet is scoped to .oyonx-plugins and handles the collection grid, disclosure layout and responsive spacing. No formal separate brand guidelines were supplied; the current local website is the design reference. The former serif/off-white prototype redirects to /plugins.

## Editable files

- scripts/plugins/page.mjs: the new page module template.
- scripts/plugins/page.css: scoped layout additions.
- scripts/plugins/products.json: seven tool descriptions/features.
- seo/routes.json: title, description and canonical route information.
- scripts/build-plugins.mjs: guarded integration into the preserved compiled snapshot; run with node scripts/build-plugins.mjs.

The builder keeps the original compiled assets untouched and generates versioned entry/lazy modules that share one React instance. It regenerates SEO shells, Apache rules and the sitemap through the existing generator. It fails if expected baseline patch points change. This is a focused snapshot integration, not recovery of the missing original React source or a general React build pipeline. Obtain the original source before replacing the baseline or performing larger frontend changes.

## Verification

Run node tests/brand-schema.mjs and node tests/seo-http.mjs against an Apache preview. The HTTP checks now derive the sitemap count from the route manifest. PHP/inquiry code is unchanged. Browser checks cover shared-header navigation, active state, filtering, feature disclosures, return to home sections and mobile navigation.

## Release status

Prepared on feat/figma-plugins for review against fix/google-indexing-audit (PR #1). Main has not been changed and nothing has been uploaded to Hostinger. Preserve the existing approval requirement for production. Product listing links, marketplace statuses and Community statistics still need independent verification before release. This styling change introduces no purchase or account promises.

The pre-change full local backup is /private/tmp/oyonx-before-plugins-integration-20261026.tar.gz.

## Verified Community links and release candidate

Five published tools now link to their verified canonical Figma Community listings. Font Replacer Pro and Design System Creator remain under review and have no installation links. Public stats are dated 26 October 2026. See COMMUNITY-VERIFICATION.md and scripts/plugins/verification-2026-10-26.json.

The Hostinger upload ZIP and deployment instructions are in the ignored .release/ directory. No live changes have been made. The ZIP is a full snapshot and includes the checkout’s earlier SEO/routing work.

## GitHub deployment preparation

The plugins review branch builds on the existing SEO review branch. Merge/deployment must account for PR #1; the plugins change is not an independent main-branch patch. The Hostinger deployment branch, document root and live rollback backup have not yet been verified because hPanel requires sign-in. No GitHub deployment hook or Actions workflow was found in the repository during inspection.
