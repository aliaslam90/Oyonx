# Oyonx brand entity audit — 15 October 2026

## Stack and metadata locations

This repository is a deployable, compiled Vite/React SPA with React Router and a PHP inquiry endpoint. There is no `package.json`, original JSX/TSX component source, source map, Next.js layout, or original build configuration. Vite dependency helpers and React/React Router code are present in the JavaScript bundle.

- Global HTML and static head metadata: `/index.html`.
- Global brand graph source: `/seo/brand-entity.json`, rendered into the HTML head by `/scripts/update-brand-schema.mjs`.
- Route metadata: compiled main module referenced by `index.html`. `Wk` applies titles, canonical URLs, descriptions, Open Graph, Twitter and per-page JSON-LD; `Lm` removes only nodes marked `data-oyonx-seo`.
- Route schemas: `Bk`, `Ok`, `Uk`, `Vk` and `_k` in the main module generate page schemas, breadcrumbs, services, FAQs and articles. The static brand script is not marked for route cleanup.
- Navbar, footer and home contact section: compiled main module. Navbar includes the Oyonx wordmark, footer includes the brand name and official social links. The separate contact route imports the shared contact section.
- About, service and blog views: lazy-loaded `AboutPage-*.js`, `ServicePage-*.js`, `BlogIndexPage-*.js` and `BlogPostPage-*.js` modules in `/assets/`.
- Crawl rules: `/robots.txt`, which allows crawling and declares `https://oyonx.com/sitemap.xml`.
- Sitemap: `/sitemap.xml`, including the canonical homepage and service, about, contact and blog routes.
- SPA route fallback: `/.htaccess`, which serves the same HTML shell on clean route requests.

## Brand evidence extracted from existing code

| Field | Value | Evidence |
| --- | --- | --- |
| Brand | Oyonx | Navbar, footer, metadata and existing organization schema |
| Logo | `/logo.png`, 1024 × 1024 PNG | Existing organization logo reference and PNG IHDR dimensions; visually inspected |
| Other logos | `/hero-logo.png` (120 × 30), `/apple-touch-icon.png` (180 × 180) | Existing files; the hero wordmark is too short for Google's 112 × 112 minimum |
| Email | `info@oyonx.com` | Shared frontend contact configuration and PHP inquiry recipient |
| Phone | `+971585600575` | Existing organization schema and WhatsApp contact link |
| Address | Dubai, United Arab Emirates | Existing locality schema and site copy; no street address or postal code found |
| Service areas | Dubai, Abu Dhabi, United Arab Emirates | Existing service schema |
| Facebook | `https://www.facebook.com/oyonx00` | Existing footer/contact social links |
| Instagram | `https://www.instagram.com/oyonxofficial/` | Existing footer/contact social links |
| LinkedIn | `https://www.linkedin.com/company/oyonx/` | Existing footer/contact social links |
| TikTok | `https://www.tiktok.com/@officialoyonx` | Existing footer/contact social links |

The WhatsApp `wa.me` link is a contact action with prefilled text, not an identity profile, so it is not included in `sameAs`. No actual Oyonx Dribbble, Behance, X/Twitter, Clutch or other directory profile URLs were found. Decorative social icons and client portfolio links are not identity evidence. Profile ownership was inferred from existing site links, not independently authenticated with each platform. `legalName` and the brand slogan follow the supplied implementation brief; no company-registration evidence is present in this snapshot.

## Implementation

One static `application/ld+json` script in the HTML head defines a linked graph with:

- `WebSite` at `https://oyonx.com/#website`, name `Oyonx`, requested alternate names, language and publisher reference.
- One `Organization` / `ProfessionalService` at `https://oyonx.com/#organization`, using the same alternate names, the supplied brand slogan, a linked 1024 × 1024 `ImageObject`, existing contacts, city-level address, service areas, specialties and four extracted social URLs.

The graph is available without executing JavaScript, on the homepage and every SPA route. It survives React metadata cleanup. Existing `og:site_name` remains `Oyonx`. Page-specific `Service.provider` and `Article.publisher` references point to the global organization ID.

The old JavaScript-only `Organization` and separate `LocalBusiness` definitions were removed. Their separate business ID, conflicting AED 199–699 price range, coordinates and opening hours are not carried forward. There is no verified street address to support a more precise physical location. All JavaScript module filenames and import references were revised together to avoid mixing cached modules from the previous deployment.

## Maintenance and validation

Edit `seo/brand-entity.json`, then run:

```sh
node scripts/update-brand-schema.mjs
node tests/brand-schema.mjs
```

The generator safely escapes HTML delimiters and is idempotent. Tests check the HTML/source graph match, entity references, no duplicate legacy definitions, real logo dimensions, extracted profiles, crawl rules, module dependencies and seven route-schema cases. All JavaScript syntax checks passed. Browser checks on home, about, contact, two service routes, blog index and a blog article showed exactly one website and one organization, with page-specific schemas preserved and no captured JavaScript errors.

After deploying to the PHP host, inspect the live homepage and logo, run Google's Rich Results Test / Schema Markup Validator as appropriate, and use Search Console URL Inspection to request homepage recrawling. Site-name markup is not itself a rich-result type; a Rich Results Test result alone does not validate Google's site-name selection. No live deployment or Search Console submission was performed in this repository change.

Google's site-name selection and query spell correction are automated. This implementation supplies clearer identity signals; it cannot guarantee a Knowledge Graph entry, a particular site name, or removal of the “oyonnax” query correction. Consistent off-site profiles and references remain relevant.

References:

- [Google site-name guidance](https://developers.google.com/search/docs/appearance/site-names)
- [Google organization and logo guidance](https://developers.google.com/search/docs/appearance/structured-data/organization)
