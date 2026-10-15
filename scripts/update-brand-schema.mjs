import { readFile, writeFile } from 'node:fs/promises';

const root = new URL('../', import.meta.url);
const graph = JSON.parse(await readFile(new URL('seo/brand-entity.json', root), 'utf8'));
// A JSON-LD script is an HTML raw-text element: escape markup delimiters.
const serialized = JSON.stringify(graph, null, 2)
  .replace(/</g, '\\u003c')
  .replace(/>/g, '\\u003e')
  .replace(/&/g, '\\u0026');
const start = '<!-- OYONX BRAND ENTITY START -->';
const end = '<!-- OYONX BRAND ENTITY END -->';
const block = `${start}\n    <script id="oyonx-brand-entity" type="application/ld+json">\n${serialized}\n    </script>\n    ${end}`;
const file = new URL('index.html', root);
let html = await readFile(file, 'utf8');
if (html.includes(start) && html.includes(end)) {
  const from = html.indexOf(start);
  const to = html.indexOf(end) + end.length;
  html = html.slice(0, from) + block + html.slice(to);
} else if (!html.includes(start) && !html.includes(end) && html.includes('</head>')) {
  html = html.replace('</head>', `${block}\n  </head>`);
} else {
  throw new Error('Unexpected HTML structure or incomplete brand-entity markers.');
}
await writeFile(file, html);
console.log('Updated static brand graph in index.html.');
