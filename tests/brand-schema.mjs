import assert from 'node:assert/strict';
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import { runInNewContext } from 'node:vm';

const root = fileURLToPath(new URL('../', import.meta.url));
const read = (path) => readFileSync(join(root, path), 'utf8');
const html = read('index.html');
const scripts = [...html.matchAll(/<script\b([^>]*)type="application\/ld\+json"([^>]*)>([\s\S]*?)<\/script>/g)];
assert.equal(scripts.length, 1, 'one server-visible brand graph');
assert.match(scripts[0][0], /id="oyonx-brand-entity"/);
assert(!scripts[0][0].includes('data-oyonx-seo'), 'route cleanup must not own the brand graph');
assert(html.indexOf(scripts[0][0]) < html.indexOf('</head>'), 'brand graph is in head');
const graph = JSON.parse(scripts[0][3]);
assert.deepEqual(graph, JSON.parse(read('seo/brand-entity.json')));
const [site, org] = graph['@graph'];
assert.equal(graph['@context'], 'https://schema.org');
assert.equal(site['@type'], 'WebSite');
assert.equal(site.name, 'Oyonx');
assert.equal(site.url, 'https://oyonx.com/');
assert.equal(site['@id'], 'https://oyonx.com/#website');
assert.equal(site.publisher['@id'], org['@id']);
assert.equal(org['@id'], 'https://oyonx.com/#organization');
assert.deepEqual(org['@type'], ['Organization', 'ProfessionalService']);
assert.deepEqual(site.alternateName, org.alternateName);
assert.equal(org.name, site.name);
assert.equal(org.legalName, 'Oyonx');
assert.equal(org.brand.name, 'Oyonx');
assert.equal(org.brand.slogan, 'Easier to use, harder to leave.');
assert.equal(org.image['@id'], org.logo['@id']);
assert.equal(org.logo.url, org.logo.contentUrl);
assert.equal(org.email, 'info@oyonx.com');
assert.equal(org.telephone, '+971585600575');
assert.equal(org.address.addressLocality, 'Dubai');
assert.equal(org.address.addressCountry, 'AE');
assert(!('streetAddress' in org.address), 'do not fabricate a street address');
assert.equal(org.sameAs.length, 4);
assert.equal(new Set(org.sameAs).size, 4);
assert(org.sameAs.every(url => new URL(url).protocol === 'https:'));
assert(!JSON.stringify(graph).includes('REPLACE_WITH'));
const logo = readFileSync(join(root, new URL(org.logo.url).pathname));
assert.equal(logo.readUInt32BE(16), org.logo.width);
assert.equal(logo.readUInt32BE(20), org.logo.height);
assert(org.logo.width >= 112 && org.logo.height >= 112);
assert.match(read('robots.txt'), /Allow: \/\s/);
assert.match(read('robots.txt'), /Sitemap: https:\/\/oyonx.com\/sitemap.xml/);
assert.match(read('sitemap.xml'), /<loc>https:\/\/oyonx.com\/<\/loc>/);
assert.match(html, /property="og:site_name" content="Oyonx"/);

const main = html.match(/type="module" crossorigin src="\/([^\"]+)"/)[1];
const bundle = read(main);
assert(!bundle.includes('"@type":"LocalBusiness"'), 'no competing legacy business');
assert(!bundle.includes('priceRange:"AED 199'), 'no stale pricing claim');
for (const url of org.sameAs) assert(bundle.includes(url), 'social identity comes from existing site');
// Execute only the isolated pure schema builders, not the application bundle.
const builders = bundle.slice(bundle.indexOf('function Ok(t)'), bundle.indexOf('const Ho='));
const context = { ot: 'https://oyonx.com', zo: 'https://oyonx.com/og-image.png' };
runInNewContext(`${builders}; this.buildRouteSchema = Bk;`, context);
const routes = ['home', 'about', 'contact', 'blog', 'services-ui-ux', 'services-development', 'services-marketing'];
for (const id of routes) {
  const page = context.buildRouteSchema(id, [{ name: 'Home', path: '/' }, { name: id, path: '/' + id }], {
    faqItems: [{ q: 'Question?', a: 'Answer.' }],
    article: { title: 'Example', excerpt: 'Example', author: 'Ali', publishedDate: '2026-10-15' },
    articleUrl: 'https://oyonx.com/blog/example'
  });
  const nodes = page['@graph'];
  assert(nodes.some(node => node['@type'] === 'BreadcrumbList'));
  assert(!nodes.some(node => ['Organization', 'ProfessionalService', 'LocalBusiness', 'WebSite'].includes(node['@type'])));
  if (id === 'home') assert(nodes.some(node => node['@type'] === 'FAQPage'));
  if (id === 'blog') assert.equal(nodes.find(node => node['@type'] === 'Article').publisher['@id'], org['@id']);
  if (id.startsWith('services-')) assert.equal(nodes.find(node => node['@type'] === 'Service').provider['@id'], org['@id']);
}
for (const file of readdirSync(join(root, 'assets')).filter(file => file.endsWith('.js'))) {
  for (const match of read('assets/' + file).matchAll(/["']\.\/([^"']+\.js)["']/g)) {
    assert(existsSync(join(root, 'assets', match[1])), `missing dependency: ${match[1]}`);
  }
}
console.log('Brand graph, logo, crawlability, module links and seven route-schema checks passed.');
