const $=id=>document.getElementById(id);
let csrf='',tokenAction=null,token=null;
const fragment=new URLSearchParams(location.hash.slice(1));
if(['verify','reset'].includes(fragment.get('action'))&&/^[a-f0-9]{64}$/.test(fragment.get('token')||'')){
  tokenAction=fragment.get('action');token=fragment.get('token');history.replaceState(null,'',location.pathname+location.search);
}
function message(text){$('message').textContent=text;}
async function api(action,data){
  const response=await fetch('/api/billing.php?action='+encodeURIComponent(action),{method:data?'POST':'GET',credentials:'same-origin',headers:data?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{},body:data?JSON.stringify(data):undefined});
  const result=await response.json();if(!response.ok||!result.ok)throw Error(result.message||'Please try again.');return result;
}
async function session(){
  const current=await api('session');csrf=current.csrf;
  $('auth').hidden=!!current.account||!!tokenAction;$('email-action').hidden=!tokenAction;$('dashboard').hidden=!current.account||!!tokenAction;
  if(tokenAction){$('token-purpose').textContent=tokenAction==='verify'?'Confirm your email address.':'Choose a new password.';$('new-password-label').hidden=tokenAction!=='reset';$('new-password').required=tokenAction==='reset';return;}
  if(!current.account)return;
  $('identity').textContent=current.account.email+(current.account.verified?' · Email verified':' · Email verification required');
  $('resend').hidden=current.account.verified;
  for(const id of ['portal','create-token','revoke-tokens','checkout'])$(id).disabled=!current.account.verified;
  if(!current.account.verified)return;
  const access=await api('access');$('products').replaceChildren();$('product-plan').replaceChildren();
  for(const product of access.products){const row=document.createElement('div');row.className='product';row.textContent=product.name+' — '+(product.paid?'Full access':product.freeUsesRemaining+' free uses remaining');$('products').append(row);const option=document.createElement('option');option.value=product.slug;option.textContent=product.name;$('product-plan').append(option);}
  const bundle=document.createElement('option');bundle.value='bundle';bundle.textContent='Current seven bundle';$('product-plan').append(bundle);
  const available=new Set(access.plans.map(p=>p.code));const update=()=>{$('checkout').disabled=!available.has(($('product-plan').value==='bundle'?'current-seven':$('product-plan').value)+'-'+$('interval').value);};$('product-plan').onchange=update;$('interval').onchange=update;update();
  if(new URLSearchParams(location.search).get('checkout')==='complete')message('Checkout completed. Access appears after payment verification. Refresh if it is still processing.');
}
async function run(fn){try{await fn();}catch(error){message(error.message);}}
$('credentials').onsubmit=event=>{event.preventDefault();const intent=event.submitter?.value||'login';const form=new FormData(event.currentTarget);run(async()=>{const result=await api(intent,{email:form.get('email'),password:form.get('password')});$('credentials').reset();message(result.message||'Signed in.');await session();});};
$('email-token').onsubmit=event=>{event.preventDefault();run(async()=>{const result=await api(tokenAction,{token,password:$('new-password').value});token=null;tokenAction=null;$('new-password').value='';message(result.message);await session();});};
$('forgot').onclick=()=>run(async()=>{const email=new FormData($('credentials')).get('email');if(!email)throw Error('Enter your email address first.');message((await api('forgot',{email})).message);});
$('resend').onclick=()=>run(async()=>message((await api('resend',{})).message));
$('logout').onclick=()=>run(async()=>{await api('logout',{});$('plugin-token').value='';$('token-label').hidden=true;message('Signed out.');await session();});
$('create-token').onclick=()=>run(async()=>{const result=await api('plugin-token',{});$('plugin-token').value=result.token;$('token-label').hidden=false;$('plugin-token').select();message('Copy your token into the plugin. It is private and expires in 30 days.');});
$('revoke-tokens').onclick=()=>run(async()=>{await api('revoke-plugin-tokens',{});$('plugin-token').value='';$('token-label').hidden=true;message('All plugin tokens revoked.');});
for(const [id,action]of [['portal','portal'],['checkout','checkout']])$(id).onclick=()=>run(async()=>{const result=await api(action,action==='checkout'?{product:$('product-plan').value,interval:$('interval').value}:{});const url=new URL(result.url);if(url.protocol!=='https:'||!['checkout.stripe.com','billing.stripe.com'].includes(url.hostname))throw Error('Unexpected payment destination.');location.assign(url.href);});
run(session);
