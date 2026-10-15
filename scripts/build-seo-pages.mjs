import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const root = fileURLToPath(new URL('../', import.meta.url));
const routes = JSON.parse(fs.readFileSync(join(root, 'seo/routes.json'), 'utf8'));
const escape = value => value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
let template = fs.readFileSync(join(root, 'index.html'), 'utf8');
const aliases = { about: 'about', services: 'services', portfolio: 'portfolio', plans: 'plans', contact: 'contact' };
const nav = routes.map(route => `<a href="${route.path}">${escape(route.path === '/' ? 'Oyonx home' : route.h1)}</a>`).join(' · ');
const fallbackStart = '<!-- OYONX NO-JS START -->';
const fallbackEnd = '<!-- OYONX NO-JS END -->';
function render(route) {
  let html = template;
  const canonical = 'https://oyonx.com' + route.path;
  html = html.replace(/<title>[\s\S]*?<\/title>/, `<title>${escape(route.title)}</title>`);
  const values = {
    description: route.description, 'og:title': route.ogTitle, 'og:description': route.ogDescription,
    'og:url': canonical, 'twitter:title': route.twitterTitle, 'twitter:description': route.twitterDescription,
    'og:image:width': '1024', 'og:image:height': '1024'
  };
  html = html.replace(/<meta (name|property)="([^"]+)" content="[^"]*"\s*\/>/g,
    (tag, type, key) => key in values ? `<meta ${type}="${key}" content="${escape(values[key])}" />` : tag);
  html = html.replace(/<link rel="canonical" href="[^"]*"\s*\/>/, `<link rel="canonical" href="${canonical}" />`);
  // Honest non-JS experience, identical for all user agents. Not a claim of React prerendering.
  const fallback = `${fallbackStart}\n    <noscript><main class="max-w-[1200px] mx-auto px-6 py-24"><a href="/">Oyonx</a><h1>${escape(route.h1)}</h1><p>${escape(route.description)}</p><nav aria-label="Website pages">${nav}</nav><p>For the interactive website, enable JavaScript. Contact <a href="mailto:info@oyonx.com">info@oyonx.com</a> or <a href="tel:+971585600575">+971 58 560 0575</a>.</p></main></noscript>\n    ${fallbackEnd}`;
  if (html.includes(fallbackStart)) {
    const a = html.indexOf(fallbackStart), b = html.indexOf(fallbackEnd, a);
    if (b < 0) throw new Error('Incomplete non-JS fallback marker');
    html = html.slice(0, a) + fallback + html.slice(b + fallbackEnd.length);
  } else {
    html = html.replace('<div id="root"></div>', fallback + '\n    <div id="root"></div>');
  }
  return html;
}
const directory = join(root, 'seo-pages');
fs.mkdirSync(directory, { recursive: true });
const filenames = new Set(routes.filter(r => r.path !== '/').map(r => r.path.slice(1).replaceAll('/', '--') + '.html'));
for (const name of fs.readdirSync(directory)) {
  if (name.endsWith('.html') && !filenames.has(name)) fs.unlinkSync(join(directory, name));
}
for (const route of routes) {
  fs.writeFileSync(route.path === '/' ? join(root, 'index.html') : join(directory, route.path.slice(1).replaceAll('/', '--') + '.html'), render(route));
}
fs.writeFileSync(join(root, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${routes.map(r => `  <url><loc>https://oyonx.com${r.path}</loc></url>`).join('\n')}\n</urlset>\n`);
// Keep one generated routing manifest; .htaccess includes it as ordinary rules below.
const start = '# OYONX ROUTES START', end = '# OYONX ROUTES END';
const lines = [start];
for (const [path, section] of Object.entries(aliases)) lines.push(`RewriteRule ^${path}/?$ /#${section} [R=301,L,NE]`);
lines.push('RewriteRule ^studio/?$ /about-us [R=301,L]');
for (const route of routes.filter(r => r.path !== '/')) {
  const path = route.path.slice(1);
  lines.push(`RewriteRule ^${path}/$ /${path} [R=301,L]`);
  lines.push(`RewriteRule ^${path}$ seo-pages/${path.replaceAll('/', '--')}.html [L]`);
}
lines.push('RewriteRule ^$ index.html [L]', end);
const file = join(root, '.htaccess');
let config = fs.readFileSync(file, 'utf8');
const a = config.indexOf(start), b = config.indexOf(end);
if (a < 0 || b < a) throw new Error('Missing .htaccess route markers');
config = config.slice(0, a) + lines.join('\n') + config.slice(b + end.length);
fs.writeFileSync(file, config);
console.log(`Generated ${routes.length} route-specific metadata shells, sitemap and routing rules. Full React rendering still requires JavaScript.`);
