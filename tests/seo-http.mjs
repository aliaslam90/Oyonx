import assert from 'node:assert/strict';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
const root = fileURLToPath(new URL('../', import.meta.url));
const base = process.argv[2] || 'http://127.0.0.1:4174';
const routes = JSON.parse(fs.readFileSync(join(root, 'seo/routes.json'), 'utf8'));
const decode = s => s.replaceAll('&amp;', '&').replaceAll('&quot;', '"').replaceAll('&lt;', '<').replaceAll('&gt;', '>');
let checks = 0;
function equal(a,b,label) { assert.equal(a,b,label); checks++; }
async function get(path, headers={}) {
  const response = await fetch(base+path, {redirect:'manual', headers:{Connection:'close',...headers},signal:AbortSignal.timeout(10000)});
  const body = await response.text();
  return {status:response.status,headers:response.headers,body};
}
for (const route of routes) {
  const r = await get(route.path);
  equal(r.status,200,route.path);
  equal(decode(r.body.match(/<title>(.*?)<\/title>/s)[1]),route.title,'title '+route.path);
  equal(r.body.match(/<link rel="canonical" href="([^"]+)"/)[1],'https://oyonx.com'+route.path,'canonical '+route.path);
  equal(r.body.match(/property="og:url" content="([^"]+)"/)[1],'https://oyonx.com'+route.path,'OG '+route.path);
  equal(decode(r.body.match(/name="description" content="([^"]+)"/)[1]),route.description,'description '+route.path);
  equal((r.body.match(/rel="canonical"/g)||[]).length,1,'one canonical');
  equal((r.body.match(/id="oyonx-brand-entity"/g)||[]).length,1,'one entity graph');
  equal(r.headers.get('x-robots-tag')?.includes('noindex')||false,false,'no header noindex');
  assert.match(r.body, /name="robots" content="index, follow/);checks++;
  assert.match(r.body, /<noscript><main/);checks++;
  assert(r.body.includes(escapeHTML(route.h1)));checks++;
  const modules = [...r.body.matchAll(/(?:src|href)="(\/assets\/[^\"]+)"/g)].map(m=>m[1]);
  for (const path of modules) equal((await get(path)).status,200,'asset '+path);
}
function escapeHTML(s){return s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');}
for (const path of ['/not-a-real-page','/services/not-a-real-service','/blog/not-a-real-post','/assets/missing.js']) {
  const r=await get(path);equal(r.status,404,path);assert.match(r.body,/noindex, follow/);checks++;
}
for(const path of ['/about','/services','/portfolio','/plans','/contact']) {
  const r=await get(path);equal(r.status,301,path);equal(new URL(r.headers.get('location'),base).pathname+new URL(r.headers.get('location'),base).hash,'/#'+path.slice(1),'section destination');
}
for(const path of ['/index.html','/blog/','/studio']) {
  const r=await get(path);equal(r.status,301,path);
  equal(new URL(r.headers.get('location'),base).pathname,{'/index.html':'/','/blog/':'/blog','/studio':'/about-us'}[path],'clean destination');
}
// Fetch may normalize Host; curl explicitly tests the origin's www handling.
const www=execFileSync('curl',['-sS','--max-time','10','-D','-','-o','/dev/null','-H','Host: www.oyonx.com',base+'/services/ui-ux?source=test'],{encoding:'utf8'});
equal(Number(www.match(/^HTTP\/\S+ (\d+)/)[1]),301,'www redirect');
equal(www.match(/^Location:\s*(.+)/im)[1].trim(),'https://oyonx.com/services/ui-ux?source=test','www path and query');
for(const path of ['/.git/config','/server/inquiry.php','/tests/inquiry.php','/README.md','/seo-pages/about-us.html','/seo/evidence/live-before.json','/scripts/build-seo-pages.mjs'])equal((await get(path)).status,403,'private '+path);
const robots=await get('/robots.txt');equal(robots.status,200,'robots');assert.match(robots.body,/Sitemap: https:\/\/oyonx.com\/sitemap.xml/);checks++;
const sitemap=await get('/sitemap.xml');equal(sitemap.status,200,'sitemap');
const urls=[...sitemap.body.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m=>m[1]);
equal(urls.length,12,'distinct pages only');equal(new Set(urls).size,urls.length,'no duplicates');
equal(sitemap.body.includes('<lastmod>'),false,'no unverifiable modification dates');
assert.deepEqual(urls,routes.map(r=>'https://oyonx.com'+r.path));checks++;
equal((await get('/logo.png')).status,200,'logo');
console.log(`Passed ${checks} HTTP SEO assertions across ${routes.length} pages. TLS/edge behavior and React prerendering are outside this test.`);
