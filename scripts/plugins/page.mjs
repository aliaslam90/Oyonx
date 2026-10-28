import {j as jsx, R as React, m as motion, u as useSEO} from './__ENTRY__';

// Uses Oyonx's existing React runtime, stylesheet and shared layout.
const products = __PRODUCTS__;
const metadata = __METADATA__;
const headingFont = {fontFamily:"'Space Grotesk', sans-serif"};
const fade = {initial:{opacity:0,y:30},whileInView:{opacity:1,y:0},viewport:{once:true},transition:{duration:.6}};
const buttonClass = 'inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-black text-white rounded-full text-[0.85rem] tracking-[0.04em] hover:opacity-90 transition-opacity';

export function PluginsPage() {
  useSEO(metadata);
  React.useEffect(()=>{const id=window.location.hash.slice(1);if(id)document.getElementById(id)?.scrollIntoView();},[]);
  const [filter,setFilter] = React.useState('all');
  const shown = products.filter(product => filter==='all' || product.category===filter);
  return jsx.jsxs('main',{id:'main',className:'oyonx-plugins bg-white',style:{fontFamily:"'Inter', sans-serif"},children:[
    jsx.jsx('section',{className:'plugins-hero relative overflow-hidden',children:jsx.jsxs(motion.div,{initial:{opacity:0,y:40},animate:{opacity:1,y:0},transition:{duration:.9,ease:[.25,.46,.45,.94]},className:'max-w-[950px] mx-auto text-center px-6',children:[
      jsx.jsx('p',{className:'text-neutral-400 text-[0.78rem] tracking-[0.15em] uppercase mb-5',children:'Tools by Oyonx'}),
      jsx.jsxs('h1',{className:'mb-6',children:[
        jsx.jsx('span',{className:'block text-[clamp(2.5rem,6.5vw,5.5rem)] leading-[1.08] tracking-[-0.02em] text-neutral-400',style:{...headingFont,fontWeight:300},children:'Figma tools & browser extensions.'}),
        jsx.jsx('span',{className:'block text-[clamp(2.5rem,6.5vw,5.5rem)] leading-[1.08] tracking-[-0.03em] text-black mt-1',style:{...headingFont,fontWeight:800},children:'Less busywork. More design.'})
      ]}),
      jsx.jsx('p',{className:'text-neutral-500 text-[0.95rem] md:text-[1.05rem] max-w-xl mx-auto leading-relaxed mb-8',children:'Practical tools for the work you do every day. Manage typography, build palettes, find icons and bring ideas together, across your canvas and browser.'}),
      jsx.jsx('a',{href:'#plugin-collection',className:buttonClass,style:{fontWeight:600},children:'Explore the Collection →'})
    ]})}),
    jsx.jsx('section',{id:'plugin-collection',className:'py-24 lg:py-32 bg-white',style:{scrollMarginTop:96},'aria-labelledby':'plugins-collection-heading',children:jsx.jsxs('div',{className:'max-w-[1200px] mx-auto px-6 lg:px-10',children:[
      jsx.jsxs(motion.div,{...fade,className:'plugins-collection-heading mb-8',children:[
        jsx.jsxs('h2',{id:'plugins-collection-heading',className:'text-[clamp(2rem,4vw,3rem)] leading-[1.15] tracking-[-0.02em] text-black',style:headingFont,children:[jsx.jsx('span',{style:{fontWeight:300},children:'Small tools. '}),jsx.jsx('span',{className:'italic',style:{fontWeight:700},children:'Better workflows.'})]}),
        jsx.jsx('p',{className:'text-neutral-500 text-[0.85rem]',role:'status','aria-live':'polite',children:shown.length+(shown.length===1?' tool to explore':' tools to explore')})
      ]}),
      jsx.jsx('p',{className:'text-neutral-500 text-[0.78rem] mb-8',children:'Figma Community stats verified 26 October 2026. FrameForge listing verified 29 October 2026.'}),
      jsx.jsx('div',{className:'flex flex-wrap items-center gap-3 mb-8',role:'group','aria-label':'Filter tools',children:[['all','All tools'],['plugins','Plugins'],['widgets','Widgets'],['extensions','Browser extensions']].map(([value,label])=>jsx.jsxs('button',{type:'button','aria-pressed':filter===value,onClick:()=>setFilter(value),className:'plugins-filter inline-flex items-center gap-2 px-5 py-2.5 border border-neutral-200 rounded-full text-[0.82rem] transition-colors',style:{background:filter===value?'#000000':'#ffffff',color:filter===value?'#ffffff':'#525252',borderColor:filter===value?'#000000':'#e5e5e5',fontWeight:filter===value?600:450},children:[label,jsx.jsx('span',{className:'text-[0.72rem]',style:{opacity:.6},children:String(value==='all'?products.length:products.filter(product=>product.category===value).length).padStart(2,'0')})]},value))}),
      jsx.jsx('div',{className:'plugins-grid',children:shown.map(product=>jsx.jsxs(motion.article,{...fade,className:'plugins-card bg-neutral-50 border border-neutral-100 rounded-2xl overflow-hidden hover:shadow-lg transition-shadow duration-500',children:[
        jsx.jsx('img',{src:'/assets/plugins/'+product.slug+'.png',alt:product.name+' original cover',loading:'lazy',decoding:'async',className:'plugins-cover',width:800,height:450}),
        jsx.jsxs('div',{className:'plugins-card-body p-6 lg:p-7',children:[
          jsx.jsx('p',{className:'text-neutral-500 text-[0.72rem] tracking-[0.12em] uppercase mb-3',children:product.category==='extensions'?'Chrome browser extension':product.category==='widgets'?'Figma & FigJam widget':'Figma plugin'}),
          jsx.jsx('h3',{className:'text-[1.2rem] text-black mb-3',style:{...headingFont,fontWeight:700},children:product.name}),
          jsx.jsx('p',{className:'text-neutral-500 text-[0.85rem] leading-relaxed',children:product.description}),
          product.stats&&jsx.jsx('p',{className:'plugins-stats text-neutral-500 text-[0.78rem]',children:product.stats.users+' users'+(product.stats.likes!==null?' · '+product.stats.likes+(product.stats.likes==='1'?' like':' likes'):'' )}),
          jsx.jsxs('details',{className:'plugins-details',children:[jsx.jsx('summary',{children:'Explore features'}),jsx.jsx('ul',{children:product.features.map(feature=>jsx.jsx('li',{children:feature},feature))})]}),
          jsx.jsx('div',{className:'plugins-card-action',children:product.status==='published'?jsx.jsx('a',{href:product.communityUrl,target:'_blank',rel:'noopener noreferrer',className:'plugins-community-link inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full text-[0.82rem] bg-black text-white hover:opacity-90 transition-opacity',style:{fontWeight:600},'aria-label':'View '+product.name+' on '+(product.category==='extensions'?'Chrome Web Store':'Figma')+' (opens in a new tab)',children:product.category==='extensions'?'View on Chrome Web Store ↗':'View on Figma ↗'}):jsx.jsxs('div',{children:[jsx.jsx('span',{className:'plugins-review-badge',children:'Under review'}),jsx.jsx('p',{className:'text-neutral-500 text-[0.78rem] mt-3',children:'Available after Figma approval.'})]})})
        ]})
      ]},product.slug))})
    ]})}),
    jsx.jsx('section',{className:'relative py-24 lg:py-32 bg-black overflow-hidden',children:jsx.jsxs(motion.div,{...fade,className:'max-w-[1200px] mx-auto px-6 lg:px-10',children:[
      jsx.jsx('p',{className:'text-neutral-500 text-[0.78rem] tracking-[0.15em] uppercase mb-5',children:'Plugin support'}),
      jsx.jsxs('h2',{className:'text-[clamp(2rem,4.5vw,3.5rem)] leading-[1.12] tracking-[-0.02em] text-white',style:headingFont,children:[jsx.jsx('span',{style:{fontWeight:300},children:'Need a hand '}),jsx.jsx('span',{className:'italic',style:{fontWeight:700},children:'with a tool?'})]}),
      jsx.jsx('p',{className:'text-neutral-400 text-[0.95rem] mt-6 mb-8 max-w-md leading-relaxed',children:'Ask a question, report an issue or tell us what would make your workflow better. We’d like to hear from you.'}),
      jsx.jsx('a',{href:'mailto:info@oyonx.com?subject=Oyonx%20plugin%20support',className:'inline-flex items-center gap-2 px-8 py-4 rounded-full text-black bg-white text-[0.9rem] hover:opacity-90 transition-opacity',style:{fontWeight:700},children:'Get in Touch →'})
    ]})})
  ]});
}
