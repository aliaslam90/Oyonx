import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import {execFileSync} from 'node:child_process';

const root=fileURLToPath(new URL('../',import.meta.url));
const read=file=>fs.readFileSync(path.join(root,file),'utf8');
const write=(file,content)=>fs.writeFileSync(path.join(root,file),content);
const entry='index-4d06a083a3ff.js';
const baseline=read('assets/'+entry);
const template=read('scripts/plugins/page.mjs');
const css=read('scripts/plugins/page.css');
const products=JSON.parse(read('scripts/plugins/products.json'));
const routes=JSON.parse(read('seo/routes.json'));
const route=routes.find(route=>route.path==='/plugins');
if(!route)throw new Error('Missing /plugins route metadata');
const metadata={...route,keywords:'Oyonx Figma plugins, Figma widgets, Chrome browser extensions, FrameForge, design tools',breadcrumbs:[{name:'Home',path:'/'},{name:'Plugins',path:'/plugins'}]};
const revision=createHash('sha256').update(template+css+JSON.stringify(products)+JSON.stringify(metadata)+baseline+fs.readFileSync(fileURLToPath(import.meta.url),'utf8')).digest('hex').slice(0,12);
const name='index-plugins-'+revision+'.js';
const moduleName='PluginsPage-'+revision+'.js';
const stylesheet='plugins-'+revision+'.css';
const originals=fs.readdirSync(path.join(root,'assets')).filter(file=>/\.js$/.test(file)&&!file.includes('-plugins-')&&!file.startsWith('PluginsPage-'));
const names=new Map(originals.map(file=>[file,file===entry?name:file.replace(/\.js$/,'-plugins-'+revision+'.js')]));
function replaceOnce(source,before,after){if(source.split(before).length!==2)throw new Error('Unexpected snapshot: '+before.slice(0,100));return source.replace(before,after)}
let bundle=replaceOnce(baseline,'{label:"Portfolio",id:"portfolio"},{label:"Plans",id:"plans"}','{label:"Portfolio",id:"portfolio"},{label:"Plugins",id:"plugins"},{label:"Plans",id:"plans"}');
bundle=replaceOnce(bundle,'const N=m&&l===k.id,R=A?', 'const N=k.id==="plugins"?d.pathname==="/plugins":m&&l===k.id,R=A?');
bundle=replaceOnce(bundle,'return u.jsx("a",{href:k.id==="home"?"/":"/#"+k.id,','if(k.id==="plugins")return u.jsx(hc,{to:"/plugins","aria-current":N?"page":void 0,className:R,style:L,onClick:()=>{o(!1),window.scrollTo(0,0)},children:k.label},k.id);return u.jsx("a",{href:k.id==="home"?"/":"/#"+k.id,');
bundle=replaceOnce(bundle,'function s8(){','const OyonxPlugins=b.lazy(()=>import("./'+moduleName+'").then(t=>({default:t.PluginsPage})));function s8(){');
bundle=replaceOnce(bundle,'children:[u.jsx(ht,{path:"/",element:u.jsx(Pr,{})})','children:[u.jsx(ht,{path:"/plugins",element:u.jsx(b.Suspense,{fallback:u.jsx(Mi,{}),children:u.jsx(OyonxPlugins,{})})}),u.jsx(ht,{path:"/plugins-preview",element:u.jsx(cc,{to:"/plugins",replace:!0})}),u.jsx(ht,{path:"/",element:u.jsx(Pr,{})})');
bundle=replaceOnce(bundle,'About:[{label:"About Us",href:"/about-us"},{label:"Blog",href:"/blog"}', 'About:[{label:"About Us",href:"/about-us"},{label:"Plugins",href:"/plugins"},{label:"Blog",href:"/blog"}');
bundle=replaceOnce(bundle,'export{YS as C,','export{b as R,YS as C,');
bundle=replaceOnce(bundle,'if(!c||c==="home"){if(t.hash){','if(!c||c==="home"){if(t.pathname==="/plugins")return;if(t.hash){');
function remap(source){for(const [before,after]of names)source=source.replaceAll(before,after);return source}
for(const original of originals){write('assets/'+names.get(original),remap(original===entry?bundle:read('assets/'+original)))}
write('assets/'+moduleName,template.replace('__ENTRY__',name).replace('__PRODUCTS__',JSON.stringify(products,null,2)).replace('__METADATA__',JSON.stringify(metadata,null,2)));
write('assets/'+stylesheet,css);
let html=read('index.html').replace(/src="\/assets\/index-[^"]+\.js"/,'src="/assets/'+name+'"');
html=html.replace(/\s*<link rel="stylesheet" href="\/assets\/plugins-[^"]+\.css">/g,'');
html=html.replace('</head>','    <link rel="stylesheet" href="/assets/'+stylesheet+'">\n  </head>');
write('index.html',html);
execFileSync(process.execPath,[path.join(root,'scripts/build-seo-pages.mjs')],{stdio:'inherit'});
console.log('Plugins integrated using Oyonx shared layout. Entry: '+name);
