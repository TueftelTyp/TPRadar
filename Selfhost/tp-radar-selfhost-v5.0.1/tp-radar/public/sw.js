/* TP Radar v5.0: app shell only; database/API always network */
const CACHE='tp-radar-shell-v5.0.1';
const SHELL=['./','./index.html','./app.js?v=5.0.1','./manifest.webmanifest','./icons/icon-192.png','./icons/icon-512.png','./favicon.ico','./favicon-16x16.png','./favicon-32x32.png','./apple-touch-icon.png'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL)).then(()=>self.skipWaiting()))});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()))});
self.addEventListener('fetch',event=>{
 const request=event.request;
 if(request.method!=='GET'||new URL(request.url).origin!==self.location.origin)return;
 if(new URL(request.url).pathname.includes('/adm/'))return;
 if(new URL(request.url).pathname.endsWith('/api.php'))return;
 if(request.mode==='navigate'){
  event.respondWith(fetch(request).then(response=>{if(response.ok){const copy=response.clone();caches.open(CACHE).then(c=>c.put('./index.html',copy))}return response}).catch(()=>caches.match('./index.html')));
  return;
 }
 event.respondWith(caches.match(request).then(hit=>hit||fetch(request)));
});
