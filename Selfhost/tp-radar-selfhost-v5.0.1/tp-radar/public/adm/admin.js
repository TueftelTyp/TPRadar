'use strict';
const el=id=>document.getElementById(id);let csrf='',state={products:[],prices:[],stores:[],audit:[]},tab='products',editing=null;
const html=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function notice(message){el('toast').textContent=message;el('toast').hidden=false;}
async function api(action,body=null){let opts={method:'POST',headers:{'Content-Type':'application/json'}};if(body!==null)opts.body=JSON.stringify({...body,csrf});else if(action==='status')opts={method:'GET'};const response=await fetch('api.php?action='+encodeURIComponent(action),opts);let data;try{data=await response.json();}catch{throw Error('Serverantwort unlesbar.');}if(!response.ok||!data.ok)throw Error(data.error||'Unbekannter Fehler');return data;}
function showLogin(){el('login').hidden=false;el('dashboard').hidden=true;}
function showDash(){el('login').hidden=true;el('dashboard').hidden=false;load();}
async function load(){try{const data=await api('list',{});state=data;render();}catch(e){notice(e.message);if(/Sitzung/.test(e.message))showLogin();}}
function number(n){return Number(n).toLocaleString('de-DE');}
function render(){const q=el('search').value.toLowerCase().trim();let arr=state[tab]||[];arr=arr.filter(x=>JSON.stringify(x).toLowerCase().includes(q));let heads=[],rows='';
 if(tab==='products') {heads=['ID','Produkt','Eigenschaften','Status','Aktion'];rows=arr.map(x=>`<tr><td>${x.id}</td><td><strong>${html(x.name)}</strong><small>${html(x.ean||'ohne EAN')}</small></td><td>${x.rolls} Rollen · ${x.sheets} Blatt · ${x.layers} Lagen</td><td>${Number(x.is_active)?'Aktiv':'Deaktiviert'}</td><td><button data-edit="products" data-id="${x.id}">Bearbeiten</button></td></tr>`).join('');}
 if(tab==='prices') {heads=['ID','Produkt','Geschäft','Preis/Datum','Status','Aktion'];rows=arr.map(x=>`<tr><td>${x.id}</td><td>${html(x.product_name)}</td><td>${html(x.store_name)}</td><td>${(x.price_cents/100).toFixed(2).replace('.',',')} €<small>${html(x.observed_on)}</small></td><td class="${x.status==='pending'?'bad':''}">${html(x.status)}</td><td><button data-edit="prices" data-id="${x.id}">Prüfen</button></td></tr>`).join('');}
 if(tab==='stores') {heads=['ID','Geschäft','Status','Aktion'];rows=arr.map(x=>`<tr><td>${x.id}</td><td>${html(x.name)}</td><td>${Number(x.is_active)?'Aktiv':'Deaktiviert'}</td><td><button data-edit="stores" data-id="${x.id}">Bearbeiten</button></td></tr>`).join('');}
 if(tab==='audit') {heads=['Zeit','Aktion','Bereich','ID'];rows=arr.map(x=>`<tr><td>${html(x.created_at)}</td><td>${html(x.action)}</td><td>${html(x.entity_type)}</td><td>${x.entity_id}</td></tr>`).join('');}
 el('createStore').hidden=(tab!=='stores');
 el('tableWrap').innerHTML=`<table><thead><tr>${heads.map(x=>`<th>${x}</th>`).join('')}</tr></thead><tbody>${rows||`<tr><td colspan="${heads.length}">Keine Einträge gefunden.</td></tr>`}</tbody></table>`;
 el('tableWrap').querySelectorAll('[data-edit]').forEach(b=>b.addEventListener('click',()=>edit(b.dataset.edit,Number(b.dataset.id))));
}
function field(key,label,value,type='text',extra=''){return `<label>${html(label)}<input name="${html(key)}" type="${type}" value="${html(value??'')}" ${extra}></label>`;}
function edit(type,id){const item=state[type].find(x=>Number(x.id)===id);if(!item)return;editing={type,id};el('dialogTitle').textContent=type==='products'?'Produkt bearbeiten':type==='stores'?'Geschäft bearbeiten':'Preis prüfen/bearbeiten';let fields='';
 if(type==='products')fields=field('name','Name',item.name,'text','required maxlength="120"')+field('ean','EAN (optional)',item.ean||'','text','pattern="[0-9]{8,14}"')+field('rolls','Rollen',item.rolls,'number','required min="1" max="500"')+field('sheets','Blätter pro Rolle',item.sheets,'number','required min="1" max="2000"')+field('layers','Lagen',item.layers,'number','required min="1" max="10"')+`<label class="inline"><input type="checkbox" name="is_active" ${Number(item.is_active)?'checked':''}> Aktiv (sonst aus öffentlicher Ansicht ausblenden)</label><p>Änderungen an Rollen, Blättern oder Lagen verändern die berechneten Preise aller historischen Angebote dieses Produkts.</p>`;
 if(type==='stores')fields=field('name','Geschäft',item.name,'text','required maxlength="80"')+`<label class="inline"><input type="checkbox" name="is_active" ${Number(item.is_active)?'checked':''}> Aktiv</label>`+`<label>Mit anderem Geschäft zusammenführen (optional)<select name="target_id"><option value="">Nicht zusammenführen</option>${state.stores.filter(s=>Number(s.id)!==id).map(s=>`<option value="${s.id}">${html(s.name)} (#${s.id})</option>`).join('')}</select></label><label class="inline"><input type="checkbox" name="confirm_merge"> Zusammenführung ausdrücklich bestätigen (nur bei Zielauswahl)</label>`;
 if(type==='prices')fields=`<p>${html(item.product_name)} · ${html(item.store_name)}</p>`+field('price_cents','Preis in Cent',item.price_cents,'number','required min="1" max="999999"')+field('observed_on','Beobachtet am',item.observed_on,'date','required')+`<label>Prüfstatus<select name="status"><option value="confirmed" ${item.status==='confirmed'?'selected':''}>Bestätigt</option><option value="pending" ${item.status==='pending'?'selected':''}>Ausstehend</option><option value="rejected" ${item.status==='rejected'?'selected':''}>Abgelehnt / deaktiviert</option></select></label><label class="inline"><input type="checkbox" name="confirm_reject"> Ablehnung ausdrücklich bestätigen (bei Status „Abgelehnt“)</label>`;
 el('editFields').innerHTML=fields;el('editor').showModal();}
async function submitEdit(ev){ev.preventDefault();const submit=el('editForm').querySelector('button[type=submit]');if(submit.disabled)return;submit.disabled=true;try{
 if(!editing){
  const f=new FormData(el('editForm'));
  const name=String(f.get('name')||'').trim();
  if(!name){notice('Bitte einen Geschäftsnamen eingeben.');return;}
  await api('create_store',{name});
  el('editor').close();notice('Geschäft angelegt.');await load();return;
 }
 const f=new FormData(el('editForm'));const data=Object.fromEntries(f.entries());data.type=editing.type;data.id=editing.id;if(editing.type!=='prices')data.is_active=f.has('is_active');
 if(editing.type==='stores'&&data.target_id){if(!f.has('confirm_merge')){notice('Bitte die Zusammenführung im Formular ausdrücklich bestätigen.');return;}await api('merge_store',{id:editing.id,target_id:Number(data.target_id)});}else{if(editing.type==='prices'&&data.status==='rejected'&&!f.has('confirm_reject')){notice('Bitte das Ablehnen im Formular bestätigen.');return;}await api('save',data);}
 el('editor').close();notice('Änderung gespeichert.');await load();}finally{submit.disabled=false;}}
el('createStore').addEventListener('click',()=>{
 editing=null;el('dialogTitle').textContent='Neues Geschäft anlegen';
 el('editFields').innerHTML=field('name','Geschäftsname','','text','required maxlength="80" autocomplete="off"');
 el('editor').showModal();
 const nameField=el('editForm').elements.namedItem('name');if(nameField)nameField.focus();
});
el('loginForm').addEventListener('submit',async e=>{e.preventDefault();const submit=el('loginForm').querySelector('button[type=submit]');if(submit.disabled)return;submit.disabled=true;try{if(!csrf){const pre=await api('status');csrf=pre.csrf||'';}const data=await api('login',{username:el('username').value,password:el('password').value,totp:el('totp').value});csrf=data.csrf;el('password').value='';el('totp').value='';showDash();}catch(e){notice(e.message);}finally{submit.disabled=false;}});
el('editForm').addEventListener('submit',e=>{submitEdit(e).catch(err=>notice(err.message));});
el('close').onclick=()=>el('editor').close();el('cancel').onclick=()=>el('editor').close();el('reload').onclick=load;el('search').oninput=render;
el('logout').onclick=async()=>{try{await api('logout',{});csrf='';showLogin();}catch(e){notice(e.message);}};
document.querySelectorAll('[data-tab]').forEach(b=>b.onclick=()=>{tab=b.dataset.tab;document.querySelectorAll('[data-tab]').forEach(x=>x.classList.toggle('active',x===b));el('search').value='';render();});
api('status').then(d=>{csrf=d.csrf||'';if(d.authenticated){showDash();}else showLogin();}).catch(e=>{showLogin();notice(e.message);});
