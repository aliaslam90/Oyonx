/** Runs in the Figma plugin UI. Persist tokens in figma.clientStorage through the
 * plugin main context, never in shared document/plugin data. */
const endpoint='https://oyonx.com/api/billing.php?action=';
export async function accessRequest(action,{product,token,requestKey,fetchImpl=fetch}) {
  if(!/^[a-f0-9]{64}$/.test(token||''))throw Error('Sign in to your Oyonx account first.');
  if(!/^[a-f0-9]{64}$/.test(requestKey||''))throw Error('Invalid action request key.');
  const response=await fetchImpl(endpoint+action,{
    method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+token},
    body:JSON.stringify({product,requestKey}),signal:AbortSignal.timeout(15000)
  });
  const result=await response.json();
  if(!response.ok||result.ok!==true||(action==='use'&&result.allowed!==true))throw Error(result.message||'Unable to verify plugin access. Try again.');
  return result;
}
export function actionRequestKey(){return Array.from(crypto.getRandomValues(new Uint8Array(32)),n=>n.toString(16).padStart(2,'0')).join('');}
/** The caller is responsible for retaining an unfinished finalize record across
 * UI restarts. Refuse another mutation while that record is unresolved. */
export async function runLicensedAction(options,operation,{savePending,clearPending}={}) {
  if(typeof savePending!=='function'||typeof clearPending!=='function')throw Error('Plugin must provide durable pending-operation storage.');
  const args={...options,requestKey:actionRequestKey()};
  await accessRequest('use',args);
  await savePending({product:args.product,requestKey:args.requestKey,state:'reserved'});
  let value;
  try { value=await operation(); }
  catch(error){
    try{await accessRequest('use-cancel',args);await clearPending();}catch{}
    throw error;
  }
  await savePending({product:args.product,requestKey:args.requestKey,state:'completed'});
  await accessRequest('use-complete',args);
  await clearPending();
  return value;
}
