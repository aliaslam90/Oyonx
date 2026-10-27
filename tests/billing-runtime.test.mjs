import test from 'node:test';
import assert from 'node:assert/strict';
import {createLicense} from '../payments/sdk/figma-main.mjs';
function setup(product='fontstyle-pro',overrides={}){
 const memory=new Map([['oyonx-access-token-v1','a'.repeat(64)]]),requests=[],messages=[];
 const host={clientStorage:{getAsync:async key=>memory.get(key),setAsync:async(key,value)=>memory.set(key,value),deleteAsync:async key=>memory.delete(key)},ui:{postMessage:message=>messages.push(message)},notify(){},showUI(){},closePlugin(){}};
 let failure=null;
 const transport=async(url,options)=>{const action=new URL(url).searchParams.get('action');requests.push({action,options});if(failure===action)return{ok:false,status:503,json:async()=>({ok:false,message:'Offline'})};return{ok:true,status:200,json:async()=>({ok:true,allowed:true})};};
 const license=createLicense(product,{host,transport,requestKey:()=> 'b'.repeat(64),...overrides});
 return{license,memory,requests,messages,fail:action=>failure=action,host};
}
test('successful guide charges one completed use and keeps private token out of file data',async()=>{
 const t=setup();const result=await t.license.run(async()=>{t.license.postMessage({type:'complete'});return 42;});
 assert.equal(result,42);assert.deepEqual(t.requests.map(r=>r.action),['access','use','use-complete']);assert(!t.memory.has('oyonx-pending-fontstyle-pro'));
 assert.equal(t.requests[1].options.headers.Authorization,'Bearer '+'a'.repeat(64));
});
test('operation failure cancels reservation without using allowance',async()=>{
 const t=setup();await assert.rejects(t.license.run(async()=>{throw Error('No fonts changed');}),/No fonts/);
 assert.deepEqual(t.requests.map(r=>r.action),['access','use','use-cancel']);
});
test('no success signal does not charge a use',async()=>{
 const t=setup();await t.license.run(async()=>{});assert.equal(t.requests.at(-1).action,'use-cancel');
});
test('payment outage prevents mutations',async()=>{
 const t=setup();t.fail('use');let ran=false;await assert.rejects(t.license.run(async()=>{ran=true;}),/Offline/);assert.equal(ran,false);
});
test('failed finalization retains durable completed operation and retries it before another action',async()=>{
 const t=setup();t.fail('use-complete');await assert.rejects(t.license.run(async()=>t.license.postMessage({type:'complete'})),/Offline/);
 assert.equal(t.memory.get('oyonx-pending-fontstyle-pro').state,'completed');
 t.fail(null);await t.license.run(async()=>t.license.postMessage({type:'complete'}));
 const actions=t.requests.map(r=>r.action);assert.deepEqual(actions.slice(3),['access','use-complete','use','use-complete']);
});
test('missing account displays sign-in and never mutates',async()=>{
 const t=setup();t.memory.clear();let shown=false,ran=false;t.host.showUI=()=>shown=true;
 await assert.rejects(t.license.run(async()=>{ran=true;}),/Sign in/);assert.equal(shown,true);assert.equal(ran,false);assert.equal(t.requests.length,0);
});
test('widget view/selection no-op is free; changed state is charged',async()=>{
 const t=setup('stickynotes-pro');let value='hello';const pair=t.license.trackedState([value,next=>value=next]);
 await t.license.widgetEvent(()=>{})();assert.equal(t.requests.at(-1).action,'use-cancel');
 await t.license.widgetEvent(()=>pair[1]('world'))();assert.equal(value,'world');assert.equal(t.requests.at(-1).action,'use-complete');
});
test('widget message save uses tracked mutation rather than plugin success signal',async()=>{
 const t=setup('stickynotes-pro');const pair=t.license.trackedState(['',()=>{}]);await t.license.wrapHandler(()=>pair[1]('text'),['save-text'])({type:'save-text'});
 assert.equal(t.requests.at(-1).action,'use-complete');
});
test('simultaneous actions cannot share a reservation or completion signal',async()=>{
 const t=setup();let release;const wait=new Promise(resolve=>release=resolve);const first=t.license.run(async()=>{await wait;t.license.postMessage({type:'complete'});});
 await assert.rejects(t.license.run(async()=>{}),/already running/);release();await first;
 assert.equal(t.requests.filter(r=>r.action==='use').length,1);
});
test('font replacement with zero modified layers is free',async()=>{
 const t=setup('font-replacer-pro');await t.license.run(async()=>t.license.postMessage({type:'RESULT',result:{changedLayers:0}}));assert.equal(t.requests.at(-1).action,'use-cancel');
});

test('an error after a successful mutation still finalizes the use',async()=>{
 const t=setup();await assert.rejects(t.license.run(async()=>{t.license.postMessage({type:'complete'});throw Error('Later UI error');}),/Later UI/);
 assert.equal(t.requests.at(-1).action,'use-complete');
});
