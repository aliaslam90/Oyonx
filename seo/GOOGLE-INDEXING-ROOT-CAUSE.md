# Google indexing and brand-search investigation

Observed 15 October 2026, 18:41–18:48 Asia/Karachi. Baseline: `a68ba1b5bf4ec37ea2afb489756e958983dae197`. Review branch: `fix/google-indexing-audit`.

## Conclusion and limits

A total Google indexing failure is **not established**. In the signed-in browser sample, `oyonx` showed the official homepage as the first observed organic result and a “Did you mean: oyonnax” suggestion. `oyonx.com` also showed the homepage. `site:oyonx.com` returned homepage, blog and mixed www/non-www pages. Quoted queries behaved differently, including correction of `"oyonx"`. These samples are personalized and location dependent; they are not a substitute for URL Inspection, an exhaustive index inventory, or a ranking guarantee.

Several technical defects are confirmed independently by live HTTP GETs. Their existence is established; their causal contribution to any specific missing result is not measurable without Search Console evidence. The previous brand graph IS deployed: the initial live homepage HTML matched the baseline repository entry byte for byte. Another schema-only change would not address the confirmed defects.

## Confirmed findings

| Issue | Evidence | Severity | Fix | Verification |
|---|---|---|---|---|
| Nested pages initially declare the homepage as canonical | All 17 baseline sitemap URLs returned the same HTML with `https://oyonx.com/` canonical; browser changes it after rendering | High | Generate 12 distinct metadata shells with route-specific titles, descriptions, canonicals and OG URLs | Pass locally; new behavior not live |
| WWW duplicate host | `https://www.oyonx.com/` returned 200 without redirect, same HTML hash as apex; Google sample contains www result URLs | High | Add permanent www-to-apex rewrite preserving path/query | Pass on local Apache; CDN integration unverified |
| Unknown clean paths return 200 homepage | `/seo-audit-not-a-real-page-20261015` and `/services/not-a-real-service` returned 200 and baseline homepage HTML | High | Replace unlimited SPA fallback with known route rules and a real 404 response | Pass locally; Google soft-404 classification unverified |
| Main content requires JS | Live initial HTML: empty `#root`, zero H1 elements, zero anchors; rendered browser displays the website | High, unresolved architecture constraint | Add non-JS visitor fallback; document source recovery requirement for genuine prerendering | Partial; full React content still requires JS |
| Sitemap includes five homepage section aliases | `/about`, `/services`, `/portfolio`, `/plans`, `/contact` render the same homepage, with section scrolling | Medium | Exclude aliases from sitemap; direct requests 301 to matching homepage fragments; client metadata canonicalizes those states to homepage | Pass locally; existing URLs remain usable |
| Metadata/cache consistency | Live sitemap had 30-day cache-control and a cache HIT; HTML declares 1200×1200 OG image although local file is 1024×1024 | Low/Medium | Correct OG dimensions; request revalidation for HTML/XML/TXT at origin; require host CDN cache verification | Pass at origin locally; CDN may override |
| Main navbar uses buttons rather than anchors | Baseline rendered main navigation exposes Home/About/Services/etc as buttons | Medium | Convert only navigation controls to anchors with real destinations, keeping existing scroll handler and classes; preserve modifier-click behavior | Desktop/mobile navigation passes locally |
| Sitemap modification dates lack provenance | Snapshot dates cannot be tied to verified content revisions | Low | Omit optional lastmod values rather than fabricate new dates | XML validation and HTTP test pass |
| Original frontend source/build missing | No package.json, source maps, component source, workflow files or original build scripts | High maintenance/prerendering blocker | Record scoped bundle edits and recover original Vite project before architecture work | Confirmed unresolved |
| Search Console inaccessible to current session | Domain property displayed “Oops, you don't have access”; property selector had no Oyonx property | High diagnostic blocker | Owner must supply inspection/report exports listed below | Unverified Google decisions |

## Checks that did NOT reveal a blocker

- Apex HTTPS homepage: 200, no redirect, index/follow meta robots, no observed X-Robots-Tag noindex.
- robots.txt: 200, `Allow: /`, correct sitemap reference; JS and CSS are not blocked.
- sitemap.xml: 200 and valid XML; all 17 original URLs responded, although their initial HTML was duplicated.
- logo.png: 200 image/png; the live image remains accessible. CDN optimization changes its bytes relative to the local PNG; this alone is not a fault.
- HTTPS certificate verification succeeded for apex and www in curl (SSL verification result 0).
- HTTP apex and HTTP www each redirected 301 to HTTPS on the same host. HTTPS enforcement already exists at the Hostinger edge.
- A Googlebot user-agent sample returned the same 200 HTML hash as the ordinary audit agent. This does not verify access from Google's actual IP ranges or its Web Rendering Service.
- Initial local browser checks captured no JS errors; page forms remain present. No valid inquiry was sent and mailbox delivery is not claimed.
- The existing linked brand graph parses, remains singular across routes and points to real local logo dimensions and existing site social links. Company registration/legal name and external profile ownership have not been independently verified; no new legal/credential assertions were added.

## Evidence and environment

`evidence/live-before.json` records 28 live GET samples, including timestamps, redirect chains, final URLs, headers, SHA-256 body hashes, robots directives and initial HTML characteristics. `evidence/google-query-sample.json` records all five requested Google searches. `evidence/browser-review.json` records eight local rendered routes. `evidence/screenshots/` contains desktop/mobile before-and-after home captures. These are time-bound samples, not continuous monitoring.

DNS read-only samples: apex A answers `77.37.48.156`, `191.101.228.95`; www CNAME `www.oyonx.com.cdn.hstgr.net`, A answers `77.37.48.87`, `91.108.100.184`. Curl may reach different CDN IPs. Headers identify Hostinger/hPanel and `server: hcdn`. HTML was DYNAMIC while sitemap was HIT with a long cache lifetime. No DNS, nameserver, MX, certificate or email configuration was changed.

There is no deployment workflow in this checkout. The connector returned no PR-triggered workflow runs for the baseline commit; that endpoint does not enumerate all push-triggered actions or external deployments. The live baseline is identifiable, but its upload/deployment mechanism and host document-root path are unknown. A branch push must not be assumed to deploy.

## Rendering decision

The generated files are **route-specific metadata shells, not full React prerenders**. Their noscript summary/navigation is for visitors who cannot run JS. Google normally enables JavaScript, so that fallback does not remove its rendering dependency. No user-agent filtering, cloaking, hydration changes or framework migration was introduced.

A DOM-snapshot workaround was evaluated and rejected for production: this SPA uses createRoot rather than hydration, animated inline opacity/transforms, viewport-triggered sections, lazy route modules and forms. Capturing one browser state would not establish a reproducible, visually stable initial page and could add a flash or stale state. The original component source and build configuration are needed to implement reliable static rendering/hydration and test it. The architecture acceptance criterion remains open; do not describe the whole mission as complete.

The normal React view keeps its hero, stylesheets, images, animation code, breakpoints, forms and PHP endpoint. Scoped production-bundle changes are limited to home metadata/H1, canonical normalization for section aliases, OG image dimensions, and navbar link semantics. Module names/references were revised together for cache isolation. A source project was not invented.

## Discovery versus indexing

1. Discovery: sitemap and rendered anchors expose URLs; new no-JS fallback also has ordinary anchors.
2. Crawling: sample HTTP status/robots/SSL checks permit public fetches. Actual Google crawl history remains unknown.
3. Rendering: browser rendering works in sampled views; important main content still needs JS.
4. Indexing: Search Console status, chosen canonical and exclusions remain unavailable.
5. Ranking: observed samples show the site but cannot establish general rankings or impressions.
6. Brand recognition: spelling suggestions persist in some samples even when the homepage appears; structured data does not control spelling or Knowledge Panels.

## Exact Search Console evidence required from owner

Supply screenshots or exports for the Oyonx verified property (omit unrelated private account details):

- Homepage URL Inspection **indexed** view: coverage/verdict, referring sitemap, last crawl, crawled as, crawl allowed, fetch result, indexing allowed, user-declared canonical and Google-selected canonical.
- Homepage **Test Live URL** result and tested HTML/screenshot/resources, including any failed JS/CSS loads.
- Page Indexing report summary and CSV examples for soft 404, duplicate/canonical, crawled-not-indexed, discovered-not-indexed, robots and noindex categories.
- Sitemaps report for `https://oyonx.com/sitemap.xml`: last read, processing status and discovered URL count.
- Search Performance export for the last three months, query filters `oyonx`, `oyonx.com`, `oyonx agency`, `oyonx dubai`, with impressions/clicks, pages, countries and devices.
- Manual Actions and Security Issues status screenshots.
- URL Inspection samples for `/services/ui-ux`, `/services/development`, `/services/marketing`, `/about-us`, `/blog`, and one blog article.

Do not request indexing until the intended build is live and the live tests pass. Submission is a request, not a guarantee.

## Google guidance consulted

- [JavaScript SEO basics and original HTML canonicals](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics)
- [Troubleshooting JavaScript rendering and soft 404](https://developers.google.com/search/docs/crawling-indexing/javascript/fix-search-javascript)
- [Canonicalization troubleshooting and URL Inspection](https://developers.google.com/search/docs/crawling-indexing/canonicalization-troubleshooting)
- [Sitemaps and trustworthy lastmod values](https://developers.google.com/search/blog/2023/06/sitemaps-lastmod-ping)
- [Site name guidance](https://developers.google.com/search/docs/appearance/site-names)
