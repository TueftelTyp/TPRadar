(()=>{'use strict';
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const state={products:[],prices:[],pending:[],moderationEnabled:true,confirmationsRequired:2};let mode='search',stream=null,timer=null,busy=false,active='compare';
let dialogResolve=null,dialogPreviousFocus=null,deferredInstall=null;
function openAppDialog({title='Hinweis',message='',confirmText='OK',cancelText='Abbrechen',symbol='🧻',cancelable=false}={}){
 return new Promise(resolve=>{
  const layer=$('#app-dialog-backdrop');
  if(dialogResolve){dialogResolve(false);dialogResolve=null;}
  dialogResolve=resolve;dialogPreviousFocus=document.activeElement;
  $('#app-dialog-title').textContent=title;
  $('#app-dialog-message').textContent=message;
  $('#app-dialog-symbol').textContent=symbol;
  $('#app-dialog-accept').textContent=confirmText;
  $('#app-dialog-cancel').textContent=cancelText;
  $('#app-dialog-cancel').hidden=!cancelable;
  layer.classList.add('open');layer.setAttribute('aria-hidden','false');
  $('#app-dialog-accept').focus();
 });
}
function finishAppDialog(accepted){
 if(!dialogResolve)return;
 const cb=dialogResolve;dialogResolve=null;
 const layer=$('#app-dialog-backdrop');layer.classList.remove('open');layer.setAttribute('aria-hidden','true');
 if(dialogPreviousFocus?.isConnected)dialogPreviousFocus.focus();
 cb(accepted);
}
$('#app-dialog-accept').addEventListener('click',()=>finishAppDialog(true));
$('#app-dialog-cancel').addEventListener('click',()=>finishAppDialog(false));
$('#app-dialog-backdrop').addEventListener('click',e=>{if(e.target.id==='app-dialog-backdrop'&&!$('#app-dialog-cancel').hidden)finishAppDialog(false)});
document.addEventListener('keydown',e=>{
 if(!dialogResolve)return;
 if(e.key==='Escape'&&!$('#app-dialog-cancel').hidden){e.stopImmediatePropagation();e.preventDefault();finishAppDialog(false)}
 if(e.key==='Tab'){
  const els=[$('#app-dialog-cancel'),$('#app-dialog-accept')].filter(e=>!e.hidden);
  const at=els.indexOf(document.activeElement);
  if(e.shiftKey&&at<=0){e.preventDefault();els.at(-1).focus()}
  if(!e.shiftKey&&at===els.length-1){e.preventDefault();els[0].focus()}
 }
},true);
const ask=(title,message,confirmText='Bestätigen',symbol='⚠️')=>openAppDialog({title,message,confirmText,cancelable:true,symbol});
const tell=(title,message,symbol='✅')=>openAppDialog({title,message,symbol});
const installed=()=>window.matchMedia('(display-mode: standalone)').matches||Boolean(window.navigator.standalone);
const isIOS=()=>/iPad|iPhone|iPod/.test(navigator.userAgent)||navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1;
const isMobile=()=>/Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
function refreshInstallUI(){
 $('#install-app').hidden=installed();
}
window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredInstall=e;refreshInstallUI()});
window.addEventListener('appinstalled',()=>{deferredInstall=null;refreshInstallUI()});
const installHelp=async()=>{
 if(installed())return tell('Bereits installiert','TP Radar läuft bereits als App auf deinem Gerät.','✅');
 if(deferredInstall){
  const ev=deferredInstall;
  deferredInstall=null;
  ev.prompt();
  await ev.userChoice;
  refreshInstallUI();
  return;
 }
 if(isIOS()){
  await tell('TP Radar auf dem iPhone installieren','1. Öffne TP Radar direkt in Safari.\n2. Tippe unten auf das Teilen-Symbol (Quadrat mit Pfeil).\n3. Wähle „Zum Home-Bildschirm“.\n4. Tippe auf „Hinzufügen“.\n\nDie Installation erfolgt bei iOS über das Browsermenü, nicht über einen automatischen Dialog.','📲');
 }else if(/Android/i.test(navigator.userAgent)){
  await tell('TP Radar auf Android installieren','1. Öffne TP Radar in Chrome oder Edge.\n2. Tippe auf das Browsermenü (⋮).\n3. Wähle „App installieren“ bzw. „Zum Startbildschirm hinzufügen“.\n4. Bestätige die Installation.\n\nWenn die Option fehlt, lade die Seite erneut und prüfe die HTTPS-Verbindung.','📲');
 }else{
  await tell('TP Radar installieren','Öffne das Browsermenü und wähle „App installieren“ (falls angeboten). In Chrome oder Edge findest du die Funktion eventuell auch über ein Installationssymbol in der Adressleiste.','💻');
 }
};
$('#install-app').addEventListener('click',installHelp);
refreshInstallUI();
const infoContent={
 ranking:{title:'So entsteht das Preisranking',symbol:'🏆',message:'Wir vergleichen den neuesten bestätigten Preis pro Produkt und Geschäft. Angebote zählen maximal 30 Tage lang.\n\nPreis pro Blatt = Packungspreis ÷ (Rollen × Blätter pro Rolle).\nPreis pro Komfort-Einheit = Preis pro Blatt ÷ Lagen.\n\nMehr Lagen bedeuten nicht automatisch höhere Qualität. Weichheit, Blattgröße und Reißfestigkeit werden nicht berücksichtigt.'},
 history:{title:'Was zeigt der Preisverlauf?',symbol:'📈',message:'Hier siehst du die bestätigten, öffentlich gemeldeten Packungspreise des ausgewählten Produkts. Preise aus verschiedenen Geschäften können sich unterscheiden.\n\nFür den Verlauf werden nur bestätigte Einträge angezeigt; ausstehende Preise erscheinen erst nach Freigabe.'},
 service:{title:'Über TP Radar',symbol:'🌐',message:'TP Radar ist eine öffentliche Community-Preisdatenbank ohne Benutzerkonten. Im Ranking erscheinen bestätigte Angebote, die höchstens 30 Tage alt sind. Die Preise stammen von Nutzern und können fehlerhaft sein.\n\nPreisangaben werden auf Plausibilität geprüft, soweit die Prüfung aktiviert ist. Weitere Details findest du bei „Community-Bestätigungen“.'},
 community:{title:'Warum muss ich Preise bestätigen?',symbol:'🛡️',message:'Stark abweichende Meldungen können zurückgehalten werden. Andere Nutzer können einen Preis bestätigen, wenn sie ihn selbst vor Ort gesehen haben.\n\nWir verwenden anonyme Browserkennungen und technische Limits, aber ohne Benutzerkonten lässt sich die Unabhängigkeit nicht vollständig garantieren. Ein erster Preis ohne Vergleichswert wird derzeit sofort veröffentlicht.'},
 scanner:{title:'Barcode scannen',symbol:'📷',message:'Halte die Kamera ruhig über den Barcode. Falls dein Browser den Kamerascan nicht unterstützt, kannst du die EAN auch eintippen.\n\nFür den Kamerazugriff ist HTTPS erforderlich. Ein Barcode allein bestätigt noch keine Produktdaten.'}
};
document.addEventListener('click',e=>{const b=e.target.closest('[data-info]');if(b&&infoContent[b.dataset.info])openAppDialog(infoContent[b.dataset.info])});
if('serviceWorker' in navigator&&location.protocol==='https:')window.addEventListener('load',()=>navigator.serviceWorker.register('./sw.js').catch(()=>{}));
const today=()=>{const f=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());const d=Object.fromEntries(f.map(x=>[x.type,x.value]));return `${d.year}-${d.month}-${d.day}`};
const money=v=>new Intl.NumberFormat('de-DE',{style:'currency',currency:'EUR'}).format(Number(v));
const unit=v=>new Intl.NumberFormat('de-DE',{minimumFractionDigits:v<.001?6:v<.01?5:4,maximumFractionDigits:6}).format(v)+' €';
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const dateMs=s=>new Date(s+'T12:00:00Z').getTime();
const setStatus=(msg,error=false)=>{const el=$('#server-status');el.hidden=!error;el.textContent=error?msg:'';el.style.borderColor=error?'#e0a05c':'var(--line)'};
async function api(action,data){const res=await fetch('api.php?action='+encodeURIComponent(action),{method:data?'POST':'GET',headers:data?{'Content-Type':'application/json'}:{},body:data?JSON.stringify(data):undefined,cache:'no-store',credentials:'same-origin'});let json;try{json=await res.json()}catch{throw Error('Die API antwortet nicht mit gültigem JSON. Prüfe die PHP-Installation.')}if(!res.ok||!json.ok)throw Error(json.error||'Serverfehler');return json}
async function load(){try{const r=await api('list');state.products=r.products.map(p=>({...p,id:Number(p.id),rolls:Number(p.rolls),sheets:Number(p.sheets),layers:Number(p.layers)}));state.prices=r.prices.map(e=>({...e,id:Number(e.id),productId:Number(e.productId),price:Number(e.price)}));state.pending=r.pending.map(e=>({...e,id:Number(e.id),productId:Number(e.productId),price:Number(e.price),confirmations:Number(e.confirmations)}));state.moderationEnabled=r.moderationEnabled!==false;state.confirmationsRequired=Number(r.confirmationsRequired)||2;setStatus(state.moderationEnabled?'🌐 Öffentliche Community-Datenbank · nur bestätigte Preise im Ranking · Angebote maximal 30 Tage aktuell':'🌐 Öffentliche Community-Datenbank · Preisprüfung deaktiviert · Eingabeschutz aktiv');render()}catch(e){setStatus('⚠ '+e.message,true)}}
function latest(store=''){const maxAge=dateMs(today())-30*86400000,entries=new Map();for(const e of state.prices){if(store&&e.store!==store||dateMs(e.date)<maxAge)continue;const key=e.productId+'|'+e.store,old=entries.get(key);if(!old||e.date>old.date||(e.date===old.date&&e.id>old.id))entries.set(key,e)}return [...entries.values()].map(e=>{const p=state.products.find(p=>p.id===e.productId);return p?{...e,p,sheet:e.price/(p.rolls*p.sheets),comfort:e.price/(p.rolls*p.sheets*p.layers),roll:e.price/p.rolls}:null}).filter(Boolean)}
function refreshSelectors(){const stores=[...new Set([...state.prices,...state.pending].map(e=>e.store))].sort((a,b)=>a.localeCompare(b,'de'));const selected=$('#store-filter').value;$('#store-filter').innerHTML='<option value="">Alle Geschäfte</option>'+stores.map(s=>`<option value="${esc(s)}">${esc(s)}</option>`).join('');if(stores.includes(selected))$('#store-filter').value=selected;$('#store-list').innerHTML=stores.map(s=>`<option value="${esc(s)}"></option>`).join('');const hist=$('#history-product').value;$('#history-product').innerHTML=state.products.map(p=>`<option value="${p.id}">${esc(p.name)}</option>`).join('');if(state.products.some(p=>String(p.id)===hist))$('#history-product').value=hist;const ex=$('#existing-product').value;$('#existing-product').innerHTML=state.products.map(p=>`<option value="${p.id}">${esc(p.name)} (${p.rolls} × ${p.sheets}, ${p.layers}-lagig)</option>`).join('');if(state.products.some(p=>String(p.id)===ex))$('#existing-product').value=ex}
function renderCompare(){const metric=$('#rank-filter').value;const entries=latest($('#store-filter').value).sort((a,b)=>a[metric]-b[metric]);for(const metric of ['sheet','comfort']){const e=[...entries].sort((a,b)=>a[metric]-b[metric])[0];$('#winner-'+metric+'-value').textContent=e?unit(e[metric]):'–';$('#winner-'+metric+'-name').textContent=e?e.p.name:'Noch kein bestätigter Preis';$('#winner-'+metric+'-store').textContent=e?e.store+' · '+money(e.price)+' / Packung':'Keine aktuellen Angebote'}$('#result-count').textContent=entries.length+' bestätigte aktuelle Angebote';$('#ranking').innerHTML=entries.length?entries.map((e,i)=>`<div class="rank-row"><div class="rank ${i===0?'first':''}">${i+1}</div><div><div class="product-title">${esc(e.p.name)}</div><div class="meta">${e.p.rolls} Rollen · ${e.p.sheets} Blatt/Rolle · ${e.p.layers}-lagig</div><span class="store">${esc(e.store)} · ${esc(e.date)}</span></div><div class="price"><strong>${money(e.price)}</strong><small>${unit(e[metric])} / ${metric==='roll'?'Rolle':metric==='sheet'?'Blatt':'Lagenblatt'}</small></div></div>`).join(''):'<div class="empty">Noch keine bestätigten aktuellen Angebote.</div>'}
function renderProducts(){$('#product-count').textContent=state.products.length+' Community-Produkte';$('#products-list').innerHTML=state.products.length?state.products.map(p=>`<article class="product-card"><span class="badge">${p.layers}-lagig</span><strong>${esc(p.name)}</strong><div class="muted" style="font-size:13px">${p.rolls} Rollen · ${p.sheets} Blatt pro Rolle<br>${p.ean?'EAN: '+esc(p.ean):'Keine EAN hinterlegt'}</div><button class="btn btn-secondary" data-price-for="${p.id}">＋ Preis erfassen</button></article>`).join(''):'<div class="empty">Noch keine Produkte vorhanden.</div>'}
function renderHistory(){const id=Number($('#history-product').value),rows=state.prices.filter(e=>e.productId===id).sort((a,b)=>a.date.localeCompare(b.date)||a.id-b.id);$('#history-list').innerHTML=rows.length?rows.slice().reverse().map(e=>`<div class="history-entry"><div><strong>${esc(e.store)}</strong><div class="mini">${esc(e.date)}</div></div><strong>${money(e.price)}</strong></div>`).join(''):'<p class="muted">Noch keine bestätigten Preise.</p>';const svg=$('#history-chart');if(!rows.length){svg.innerHTML='<text x="25" y="135" class="chart-label">Noch keine Daten vorhanden.</text>';return}const vals=rows.map(e=>e.price),minimum=Math.min(...vals),maximum=Math.max(...vals),pad=Math.max(.3,(maximum-minimum)*.3),lo=Math.max(0,minimum-pad),hi=maximum+pad;const x=i=>rows.length===1?325:55+i/(rows.length-1)*555,y=v=>220-(v-lo)/(hi-lo)*175;let grid='';for(let j=0;j<5;j++){const val=lo+(hi-lo)*(4-j)/4,yy=45+j*43.75;grid+=`<line x1="55" x2="610" y1="${yy}" y2="${yy}" stroke="var(--line)"/><text x="5" y="${yy+4}" class="chart-label">${val.toFixed(2)} €</text>`}svg.innerHTML=grid+`<polyline points="${rows.map((e,i)=>x(i)+','+y(e.price)).join(' ')}" fill="none" stroke="var(--primary)" stroke-width="3.5"/>`+rows.map((e,i)=>`<circle cx="${x(i)}" cy="${y(e.price)}" r="5" fill="var(--primary)"><title>${esc(e.date+' '+e.store+' '+money(e.price))}</title></circle>`).join('')+`<text x="55" y="253" class="chart-label">${esc(rows[0].date)}</text><text x="610" y="253" text-anchor="end" class="chart-label">${esc(rows.at(-1).date)}</text>`}
function renderPending(){$('#pending-list').innerHTML=state.pending.length?state.pending.map(e=>`<div class="pending-item"><div><strong>${esc(e.productName)}</strong> · ${esc(e.store)}</div><div class="mini">${money(e.price)} · ${esc(e.date)} · ${esc(e.reason||'Prüfung offen')}</div><div class="mini">${e.confirmations} von ${state.confirmationsRequired} Bestätigungen</div><button class="btn btn-secondary" type="button" data-confirm="${e.id}">✓ Preis ebenfalls vor Ort gesehen</button></div>`).join(''):'<p class="muted">Aktuell keine unbestätigten Meldungen.</p>'}
function renderQuickProducts(){const ids=[...new Set([...state.prices].reverse().map(e=>e.productId).concat(state.products.map(p=>p.id)))].slice(0,3);$('#quick-products').innerHTML=ids.length?'<div class="quick-label">Schnellauswahl</div>'+ids.map(id=>{const p=state.products.find(x=>x.id===id);return p?`<button type="button" class="quick-chip" data-quick-product="${id}">${esc(p.name)}</button>`:''}).join(''):''}
function render(){refreshSelectors();renderCompare();renderProducts();renderHistory();renderPending();renderQuickProducts()}
function view(name){active=name;$$('[data-view]').forEach(b=>{const yes=b.dataset.view===name;b.classList.toggle('active',yes);if(b.closest('.mobile-nav')){if(yes)b.setAttribute('aria-current','page');else b.removeAttribute('aria-current')}});$$('.view').forEach(x=>x.classList.toggle('active',x.id===name));scrollTo({top:0,behavior:'smooth'})}
function updateSelected(){const p=state.products.find(p=>String(p.id)===$('#existing-product').value),el=$('#selected-product');if(mode==='search'&&p){el.textContent=`✓ ${p.name} · ${p.rolls} Rollen × ${p.sheets} Blatt · ${p.layers}-lagig`;el.classList.remove('hidden')}else el.classList.add('hidden')}
function switchMode(m){mode=m;$$('[data-mode]').forEach(b=>b.classList.toggle('active',b.dataset.mode===m));$('#search-fields').classList.toggle('hidden',m!=='search');$('#scan-fields').classList.toggle('hidden',m!=='scan');$('#manual-fields').classList.toggle('hidden',m!=='manual');$('#form-hint').textContent=m==='scan'?'EAN erfassen – bei bekannten Produkten direkt zum Preis.':m==='manual'?'Neues Produkt mit erstem Preis melden.':'Produkt auswählen und Preis melden.';if(m!=='scan')stopScanner();updateSelected()}
function openModal(m='search',id=null){$('#entry-form').reset();$('#entry-date').value=today();$('#new-rolls').value=8;$('#new-sheets').value=150;$('#new-layers').value=3;$('#form-feedback').textContent='';switchMode(m);if(id!==null)$('#existing-product').value=String(id);updateSelected();$('#modal').classList.add('open');document.body.style.overflow='hidden';if(m==='scan')setTimeout(startScanner,100);else if(m==='search'&&id!==null)$('#entry-price').focus()}
function closeModal(){$('#modal').classList.remove('open');document.body.style.overflow='';stopScanner()}
function stopScanner(){if(timer)clearInterval(timer);timer=null;if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;$('#scanner-video').srcObject=null;$('#scanner-box').classList.add('hidden');busy=false}
function resolveEAN(){const ean=$('#scan-ean').value.trim();if(!/^\d{8,14}$/.test(ean)){$('#scanner-status').textContent='Bitte 8 bis 14 Ziffern eingeben.';return}const p=state.products.find(p=>p.ean===ean);stopScanner();if(p){switchMode('search');$('#existing-product').value=String(p.id);updateSelected();$('#entry-price').focus()}else{switchMode('manual');$('#new-ean').value=ean;$('#new-name').focus()}}
async function startScanner(){if(mode!=='scan'||!$('#modal').classList.contains('open')||stream)return;if(!('BarcodeDetector'in window)||!navigator.mediaDevices?.getUserMedia){$('#scanner-status').textContent='Kamerascan nicht verfügbar. EAN manuell eintippen.';return}try{const detector=new BarcodeDetector({formats:['ean_13','ean_8','upc_a','upc_e']});stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}}});if(mode!=='scan'||!$('#modal').classList.contains('open')){stopScanner();return}const v=$('#scanner-video');v.srcObject=stream;await v.play();$('#scanner-box').classList.remove('hidden');timer=setInterval(async()=>{if(busy||v.readyState<2)return;busy=true;try{const codes=await detector.detect(v);if(codes.length){$('#scan-ean').value=codes[0].rawValue;resolveEAN()}}catch{}finally{busy=false}},500)}catch{stopScanner();$('#scanner-status').textContent='Kamera nicht verfügbar. Bitte EAN eingeben.'}}
async function submit(e){e.preventDefault();const submitButton=$('#entry-form button[type="submit"]');submitButton.disabled=true;try{const raw=$('#entry-price').value.trim().replace(',','.');if(!/^\d+(?:\.\d{1,2})?$/.test(raw)||!(Number(raw)>0))throw Error('Bitte gültigen Preis eingeben.');const store=$('#entry-store').value.trim(),date=$('#entry-date').value;if(!store)throw Error('Geschäft fehlt.');let data={price:raw,store,date};if(mode==='scan'){resolveEAN();return}if(mode==='search'){data.productId=Number($('#existing-product').value);if(!data.productId)throw Error('Produkt auswählen.')}else{data.product={name:$('#new-name').value.trim(),ean:$('#new-ean').value.trim(),rolls:Number($('#new-rolls').value),sheets:Number($('#new-sheets').value),layers:Number($('#new-layers').value)}}
const p=state.prices.filter(x=>x.productId===data.productId&&x.store.toLowerCase()===store.toLowerCase()).sort((a,b)=>b.date.localeCompare(a.date)||b.id-a.id)[0];if(state.moderationEnabled&&p&&Math.abs(Number(raw)/p.price-1)>0.1){if(!await ask('Preisabweichung prüfen',`Der Preis weicht mehr als 10 % vom letzten bestätigten Preis (${money(p.price)}) ab.\nHast du den neuen Preis vor Ort überprüft?`,'Ja, Preis melden'))return;data.ackDeviation=true}
let result;try{result=await api('add',data)}catch(err){if(err.message.includes('mehr als 10 %')){if(!await ask('Ungewöhnlicher Preis',err.message+'\nHast du diesen Preis vor Ort gesehen?','Trotzdem melden'))return;data.ackDeviation=true;result=await api('add',data)}else throw err}
closeModal();await load();view('compare');await tell(result.status==='pending'?'Meldung wird geprüft':'Preis gespeichert',result.message,result.status==='pending'?'🕓':'✅')
}catch(err){$('#form-feedback').textContent='⚠ '+err.message}finally{submitButton.disabled=false}}
$$('[data-view]').forEach(b=>b.addEventListener('click',()=>view(b.dataset.view)));
$$('[data-open]').forEach(b=>b.addEventListener('click',()=>{b.blur();openModal(b.dataset.mode||'search')}));
$('#quick-scan').addEventListener('click',e=>{e.currentTarget.blur();openModal('scan')});
$$('[data-mode]').forEach(b=>b.addEventListener('click',()=>switchMode(b.dataset.mode)));
$('#entry-form').addEventListener('submit',submit);$('#close-modal').addEventListener('click',closeModal);$('#cancel-modal').addEventListener('click',closeModal);$('#modal').addEventListener('click',e=>{if(e.target.id==='modal')closeModal()});document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal()});$('#existing-product').addEventListener('change',updateSelected);$('#accept-ean').addEventListener('click',resolveEAN);$('#start-scan').addEventListener('click',startScanner);$('#scan-ean').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();resolveEAN()}});$('#store-filter').addEventListener('change',renderCompare);$('#rank-filter').addEventListener('change',renderCompare);$('#history-product').addEventListener('change',renderHistory);$('#products-list').addEventListener('click',e=>{const b=e.target.closest('[data-price-for]');if(b)openModal('search',Number(b.dataset.priceFor))});$('#quick-products').addEventListener('click',e=>{const b=e.target.closest('[data-quick-product]');if(b){$('#existing-product').value=b.dataset.quickProduct;updateSelected();$('#entry-price').focus()}});$('#pending-list').addEventListener('click',async e=>{const b=e.target.closest('[data-confirm]');if(!b)return;if(!await ask('Preis vor Ort bestätigen','Hast du dieses Angebot selbst im angegebenen Geschäft zu genau diesem Preis gesehen?','Ja, selbst gesehen','🛒'))return;b.disabled=true;try{const result=await api('confirm',{id:Number(b.dataset.confirm)});await tell('Danke für deine Bestätigung',result.message);await load()}catch(err){await tell('Bestätigung nicht möglich',err.message,'⚠️')}finally{b.disabled=false}});
load();
})();
