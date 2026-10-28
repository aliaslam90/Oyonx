/** Shared main-context licensing runtime. No provider secrets or document data. */
export function createLicense(product,{host=figma,transport=fetch,now=()=>Date.now(),requestKey=()=>Array.from({length:64},()=>Math.floor(Math.random()*16).toString(16)).join('')}={}) {
 const storage=host.clientStorage,tokenKey='oyonx-access-token-v1',pendingKey='oyonx-pending-'+product;
 let busy=false,active=null,loginShown=false;
 const isWidget=['stickynotes-pro','mindmap-pro'].includes(product);
 const success={
  'fontstyle-pro':['complete'], 'colorstyle-pro':['generation-complete'],
  'font-replacer-pro':['RESULT'], 'design-system-creator':['creation-complete','advanced-applied'],
  'iconsset-pro':['success','export']
 }[product]||[];
 async function request(action,token,key){
  const response=await transport('https://oyonx.com/api/billing.php?action='+action,{
   method:action==='access'?'GET':'POST',headers:{Authorization:'Bearer '+token,...(action==='access'?{}:{'Content-Type':'application/json'})},
   ...(action==='access'?{}:{body:JSON.stringify({product,requestKey:key})})
  });
  const body=await response.json();if(!response.ok||body.ok!==true)throw Object.assign(new Error(body.message||'Unable to verify Oyonx access.'),{status:response.status});return body;
 }
 function signIn(){
  if(loginShown)return;loginShown=true;
  const html=`<!doctype html><html><head><meta charset="utf-8"><style>body{font:13px/1.6 system-ui;padding:24px;color:#171717}input,button{box-sizing:border-box;width:100%;padding:12px;margin:8px 0;border:1px solid #aaa;border-radius:8px}button{background:#111;color:white}a{color:inherit}</style></head><body><h2>Sign in to Oyonx</h2><p>Create or sign in to your Oyonx account, verify your email, then generate a plugin access token.</p><a href="https://oyonx.com/account/" target="_blank" rel="noopener noreferrer">Open Oyonx account ↗</a><label>Plugin access token<input id="token" type="password" autocomplete="off"></label><button id="connect">Connect plugin</button><p id="status" role="status"></p><script>document.getElementById('connect').onclick=()=>{const token=document.getElementById('token').value.trim();document.getElementById('token').value='';parent.postMessage({pluginMessage:{type:'OYONX_CONNECT',token}},'*')};window.onmessage=e=>{const m=e.data.pluginMessage;if(m&&m.type==='OYONX_AUTH_ERROR')document.getElementById('status').textContent=m.message};<\/script></body></html>`;
  host.showUI(html,{width:380,height:420,themeColors:true});
  host.ui.onmessage=async message=>{
   if(message?.type!=='OYONX_CONNECT')return;
   const token=message.token;
   try{if(!/^[a-f0-9]{64}$/.test(token||''))throw Error('Paste a valid Oyonx plugin access token.');await request('access',token);await storage.setAsync(tokenKey,token);host.closePlugin('Signed in. Reopen this plugin to continue.');}
   catch(error){host.ui.postMessage({type:'OYONX_AUTH_ERROR',message:error.message});}
  };
 }
 async function requireAccount(){
  const token=await storage.getAsync(tokenKey);
  if(!/^[a-f0-9]{64}$/.test(token||'')){signIn();throw Error('Sign in to your Oyonx account to continue.');}
  try{await request('access',token);return token;}
  catch(error){if(error.status===401||error.status===403){await storage.deleteAsync(tokenKey);signIn();}throw error;}
 }
 async function settlePending(token){
  const pending=await storage.getAsync(pendingKey);if(!pending)return;
  if(pending.product!==product)throw Error('Unexpected pending plugin operation.');
  if(pending.state==='running')throw Error('An interrupted operation needs verification. Contact Oyonx support before another free action.');
  await request(pending.state==='completed'?'use-complete':'use-cancel',token,pending.requestKey);
  await storage.deleteAsync(pendingKey);
 }
 async function run(operation,{widget=false}={}){
  if(busy)throw Error('An Oyonx operation is already running.');busy=true;
  let token,key,reserved=false,operationSucceeded=false,finalizing=false;
  try{
   token=await requireAccount();await settlePending(token);key=requestKey();
   const response=await request('use',token,key);if(response.allowed!==true)throw Error('This operation was not authorized.');reserved=true;
   await storage.setAsync(pendingKey,{product,requestKey:key,state:'running',createdAt:now()});active={success:false,changed:false};
   const value=await operation();
   if(product==='colorstyle-pro' && value?.model)active.success=true;
   if(product==='design-system-creator' && value && typeof value==='object' && Object.values(value).some(v=>typeof v==='number'&&v>0))active.success=true;
   operationSucceeded=widget?active.changed:active.success;
   await storage.setAsync(pendingKey,{product,requestKey:key,state:operationSucceeded?'completed':'cancelled',createdAt:now()});
   finalizing=true;await request(operationSucceeded?'use-complete':'use-cancel',token,key);await storage.deleteAsync(pendingKey);
   return value;
  }catch(error){
   operationSucceeded=operationSucceeded||!!(active&&(widget?active.changed:active.success));
   if(reserved&&!finalizing){try{await storage.setAsync(pendingKey,{product,requestKey:key,state:operationSucceeded?'completed':'cancelled',createdAt:now()});await request(operationSucceeded?'use-complete':'use-cancel',token,key);await storage.deleteAsync(pendingKey);}catch{}}
   if(error.status===402){host.notify('Your free uses are used. Subscribe at oyonx.com/account/',{error:true});}
   throw error;
  }finally{busy=false;active=null;}
 }
 function postMessage(message){
  if(active&&success.includes(message?.type)){
   if(message.type==='RESULT')active.success=!!message.result&&message.result.changedLayers>0;
   else if(message.type==='advanced-applied')active.success=Array.isArray(message.applied)?message.applied.length>0:Number(message.applied)>0;
   else active.success=true;
  }
  host.ui.postMessage(message);
 }
 function report(error,message){host.notify(error.message||String(error),{error:true});if(!loginShown)host.ui.postMessage({type:'error',message:error.message,requestId:message?.requestId,error:error.message});}
 function wrapHandler(handler,types){return async message=>{
  if(!types.includes(message?.type))return handler(message);
  try{return await run(()=>handler(message),{widget:isWidget});}catch(error){report(error,message);}
 };}
 function widgetEvent(handler){return async (...args)=>{try{return await run(()=>handler(...args),{widget:true});}catch(error){report(error);}};}
 function widgetMenu(handler){return async event=>{
  // Opening a read/editor dialog is not a use; saving text is metered separately.
  if(['edit','copy'].includes(event.propertyName))return handler(event);
  return widgetEvent(handler)(event);
 };}
 function trackedState(pair){return [pair[0],value=>{if(active&&JSON.stringify(value)!==JSON.stringify(pair[0]))active.changed=true;return pair[1](value);}];}
 function trackedMap(map){return {get:key=>map.get(key),has:key=>map.has(key),keys:()=>map.keys(),values:()=>map.values(),entries:()=>map.entries(),get size(){return map.size;},set:(key,value)=>{if(active&&JSON.stringify(map.get(key))!==JSON.stringify(value))active.changed=true;return map.set(key,value);},delete:key=>{if(active&&map.has(key))active.changed=true;return map.delete(key);}};}
 function markChanged(){if(active)active.changed=true;}
 return {markChanged,requireAccount,run,postMessage,wrapHandler,widgetEvent,widgetMenu,trackedState,trackedMap};
}
