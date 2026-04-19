/* Non-scaling HUD JS — copied from the main HUD script but used with the non-scaling CSS.
   Behavior and API are identical to the main HUD so existing integrations should work.
*/

/*
  Define your menu items here. Each item can have:
    - title: label shown under the icon
    - url: path to load into the content area (PHP/HTML endpoints)
    - img: optional full-button background image (URL)
    - icon: optional small icon shown above the label (URL)

  Edit this array to add/remove menu entries.
*/
const ThemisHUD_ITEMS = [
  { title: 'Profile', url: '/themis/content/profile.php', img: null, icon: '/themis/assets/icons/profile.png' },
  { title: 'Chars',   url: '/themis/content/characters.php',   img: null, icon: '/themis/assets/icons/characters.png' },
  { title: 'Combat',  url: '/themis/content/combat.php', img: null, icon: '/themis/assets/icons/skills.png' },
  { title: 'Map',       url: '/themis/content/map.php',       img: null, icon: '/themis/assets/icons/map.png' },
  { title: 'Skills',    url: '/themis/content/skills.php',    img: null, icon: '/themis/assets/icons/skills.png' },
  { title: 'Quests',    url: '/themis/content/quests.php',    img: null, icon: '/themis/assets/icons/quests.png' },
  { title: 'Options',  url: '/themis/content/settings.php',  img: null, icon: '/themis/assets/icons/settings.png' },
  { title: 'Something',    url: '/themis/content/logout.php',    img: null, icon: '/themis/assets/icons/logout.png' },
  { title: 'Another test', url: '/themis/content/another_test.php', img: null, icon: '/themis/assets/icons/another_test.png' },
  { title: 'Yet another test', url: '/themis/content/yet_another_test.php', img: null, icon: '/themis/assets/icons/yet_another_test.png' },
  { title: 'Final test', url: '/themis/content/final_test.php', img: null, icon: '/themis/assets/icons/final_test.png' },
];

const PAGE_SIZE = 6;
let pageIndex = 0;

// persistence keys
const LS_PREFIX = 'themishud.';
const LS_PAGE = LS_PREFIX + 'pageIndex';
const LS_LAST = LS_PREFIX + 'lastLoaded';
const LS_CHAR = LS_PREFIX + 'charName';

const buttonsGrid = document.getElementById('buttonsGrid');
const contentFrame = document.getElementById('contentFrame');
const charNameEl = document.getElementById('charName');
const initialContentHTML = contentFrame ? contentFrame.innerHTML : '';
const SLIDE_MS = 500;
let currentLoadedUrl = null;
let buttonInitiated = false;
const PAGINATE_MS = 50;
const PAGINATE_STAGGER = 50;
const START_MS = Math.max(80, Math.round(SLIDE_MS * 0.12));

// Cache icon existence checks so we don't probe the same URL repeatedly.
const _iconExistsCache = new Map();

// Reveal an element using the CSS fade utility we added. We apply the
// 'themis-fade' class then force a repaint and add 'themis-show' so the
// transition runs. Returns a Promise resolved after the transition ends
// (or immediately if transitions aren't supported).
function revealElementWithFade(el){
  if(!el) return Promise.resolve();
  try{
    el.classList.add('themis-fade');
    // ensure visibility and that the element occupies layout
    el.style.visibility = '';
    // force a reflow then add the show class
    requestAnimationFrame(()=>{ requestAnimationFrame(()=> el.classList.add('themis-show')); });
    return new Promise(res => {
      const to = setTimeout(()=>{ cleanup(); res(); }, 420);
      function cleanup(){ clearTimeout(to); try{ el.classList.remove('themis-fade'); el.classList.remove('themis-show'); }catch(e){} }
      el.addEventListener('transitionend', function te(){ cleanup(); try{ el.removeEventListener('transitionend', te); }catch(e){}; res(); });
    });
  }catch(e){ try{ el.style.visibility = ''; }catch(_){} return Promise.resolve(); }
}

const APP_ROOT = (function(){
  try{
    const p = location.pathname;
    const idx = p.indexOf('/themis');
    if(idx >= 0){
      return location.origin + p.slice(0, idx + '/themis'.length);
    }
    return location.origin;
  }catch(e){ return '' }
})();

const HUD_TEST_SUFFIX = (function(){
  try{
    const params = new URLSearchParams(window.location.search || '');
    if((params.get('test_mode') || '') !== '1') return '';
    const actor = (params.get('test_actor_uuid') || '').trim();
    const q = new URLSearchParams();
    q.set('test_mode', '1');
    if(actor) q.set('test_actor_uuid', actor);
    return q.toString();
  }catch(e){ return ''; }
})();

function fullPath(p){
  if(!p) return p;
  if(p.startsWith('//') || p.startsWith('http://') || p.startsWith('https://')) return p;
  if(p.startsWith('/')) return location.origin + p;
  return APP_ROOT.replace(/\/$/, '') + '/' + p.replace(/^\//, '');
}

ThemisHUD_ITEMS.forEach(it=>{
  if(it.url) it.url = fullPath(it.url);
  if(it.url && HUD_TEST_SUFFIX && it.url.indexOf('/themis/content/') >= 0){
    it.url += (it.url.indexOf('?') >= 0 ? '&' : '?') + HUD_TEST_SUFFIX;
  }
  if(it.img) it.img = fullPath(it.img);
  if(it.icon) it.icon = fullPath(it.icon);
});

function renderPage(){
  // Temporarily hide the grid while we populate it. Reveal it after a short delay
  // to not hide it indefinitely.
  try{ buttonsGrid.style.visibility = 'hidden'; }catch(e){}
  buttonsGrid.innerHTML='';
  const start = pageIndex * PAGE_SIZE;
  const pageItems = ThemisHUD_ITEMS.slice(start,start+PAGE_SIZE);
  const iconPromises = [];
  for(let i=0;i<PAGE_SIZE;i++){
    const it = pageItems[i];
    const btn = document.createElement('div');
    btn.className='menu-btn';
    if(it){
      if(it.icon){
        // Probe the icon URL off-DOM using an Image object. Append or skip real img.
        const probeIcon = (url, timeoutMs = 350) => {
          if(_iconExistsCache.has(url)) return Promise.resolve(!!_iconExistsCache.get(url));
          return new Promise(res => {
            let done = false;
            const img = new Image();
            const to = setTimeout(()=>{ if(done) return; done = true; _iconExistsCache.set(url, false); res(false); }, timeoutMs);
            img.onload = function(){ if(done) return; done = true; clearTimeout(to); _iconExistsCache.set(url, true); res(true); };
            img.onerror = function(){ if(done) return; done = true; clearTimeout(to); _iconExistsCache.set(url, false); res(false); };
            // Use async decoding
            try{ img.decoding = 'async'; }catch(e){}
            img.src = url;
          });
        };

        const p = probeIcon(it.icon, 350).then(ok => {
          if(!ok) return false;
          const real = document.createElement('img');
          real.className = 'icon';
          real.width = 28; real.height = 28;
          real.alt = it.title + ' icon';
          real.src = it.icon;
          // Make sure it's visible immediately
          real.style.visibility = '';
          btn.appendChild(real);
          return true;
        }).catch(()=>false);
        iconPromises.push(p);
      }
      const label = document.createElement('div'); label.className='label'; label.textContent = it.title; btn.appendChild(label);
      btn.addEventListener('click', ()=>{
        buttonInitiated = true;
        try{ localStorage.setItem(LS_PAGE, pageIndex); }catch(e){}
        const method = it.method || 'GET';
        const postData = it.postData || null;
        const postDataType = it.postDataType || 'form';
        loadContent(it.url, it.title, { method, postData, postDataType });
      });
    } else {
      btn.classList.add('empty');
    }
    buttonsGrid.appendChild(btn);
  }
  // After appending all buttons, wait for icon loads (or a short timeout)
  // before showing the grid to avoid initial collapsing and the broken img
  // placeholder flash. Use Promise.allSettled with a fallback timeout.
  if(iconPromises.length){
    const settled = Promise.allSettled(iconPromises);
    const timeout = new Promise(r => setTimeout(r, 350));
    Promise.race([settled, timeout]).then(()=>{ try{ revealElementWithFade(buttonsGrid); }catch(e){} });
  } else {
    try{ revealElementWithFade(buttonsGrid); }catch(e){}
  }
  // Update arrow disabled state based on pagination
  try{
    const maxPage = Math.floor((ThemisHUD_ITEMS.length-1)/PAGE_SIZE);
    const prev = document.getElementById('prevPage');
    const next = document.getElementById('nextPage');
    if(prev){ if(pageIndex <= 0) prev.classList.add('disabled'); else prev.classList.remove('disabled'); }
    if(next){ if(pageIndex >= maxPage) next.classList.add('disabled'); else next.classList.remove('disabled'); }
  }catch(e){}
}

async function loadContent(url,title, opts = {}){
  try{
  let fetchOpts = { cache: 'no-store' };
    const method = (opts.method || 'GET').toUpperCase();
    // If this load is the initial restore after a full page reload, callers
    // may pass opts.initial = true. In that case we hide the content frame
    // while fetching and injecting to avoid a brief flash/flicker as the
    // placeholder content is replaced by the restored fragment.
    const isInitialRestore = !!opts.initial;
    if(isInitialRestore && contentFrame){
      try{ contentFrame.style.visibility = 'hidden'; }catch(e){}
    }
    if(method === 'POST'){
      fetchOpts.method = 'POST';
      if(opts.postDataType === 'json'){
        fetchOpts.headers = { 'Content-Type':'application/json' };
        fetchOpts.body = typeof opts.postData === 'string' ? opts.postData : JSON.stringify(opts.postData || {});
      } else {
        fetchOpts.headers = { 'Content-Type':'application/x-www-form-urlencoded' };
        if(typeof opts.postData === 'string') fetchOpts.body = opts.postData;
        else if(typeof opts.postData === 'object'){
          fetchOpts.body = Object.keys(opts.postData).map(k=>encodeURIComponent(k)+'='+encodeURIComponent(opts.postData[k])).join('&');
        } else fetchOpts.body = '';
      }
    }
  fetchOpts.credentials = 'include';
  // safely execute scripts found in fetched HTML and inject the rest
  function executeAndInjectHTML(container, html){
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const scripts = Array.from(tmp.querySelectorAll('script'));
    // Remove script elements from the tmp DOM and keep info in an array
    const scriptInfos = scripts.map(s => {
      const info = { src: s.src || null, text: s.textContent || null, async: s.async || false, defer: s.defer || false };
      s.remove();
      return info;
    });

    // Inject HTML first so scripts can find the DOM nodes they expect
    container.innerHTML = tmp.innerHTML;

    // Then execute scripts in order. Inline scripts are appended into the container
    // so they execute in place; external scripts are appended to head to begin loading.
    scriptInfos.forEach(info => {
      const ns = document.createElement('script');
      if (info.src) {
        ns.src = info.src;
        if (info.defer) ns.defer = true;
        if (info.async) ns.async = true;
        document.head.appendChild(ns);
      } else if (info.text) {
        ns.textContent = info.text;
        // append to container so it executes in the right context and order
        container.appendChild(ns);
      }
    });
  // Allow content fragments to wire up forms for AJAX submission
  try{ if(typeof attachFormHandlers === 'function'){ attachFormHandlers(container); } }catch(e){}
  }

  if(url === currentLoadedUrl){
    const resp = await fetch(url, fetchOpts);
    if(!resp.ok) throw new Error('Network error ' + resp.status);
    const text = await resp.text();
    executeAndInjectHTML(contentFrame, text);
    // If this was an initial restore hide, make sure the frame is visible
    // again after injection so the user sees the restored content without
    // the placeholder flash.
  if(isInitialRestore && contentFrame){ try{ revealElementWithFade(contentFrame); }catch(e){} }
    try{ localStorage.setItem(LS_LAST, JSON.stringify({ url, title, method: fetchOpts.method || 'GET', timestamp: Date.now() })); }catch(e){}
    return;
  }
  const willAnimate = !!buttonInitiated; buttonInitiated = false;
  if(willAnimate){
    try{
      contentFrame.classList.add('animating','slide-out');
      await new Promise(r=>setTimeout(r, SLIDE_MS));
    }catch(e){}
  }
  const resp = await fetch(url, fetchOpts);
  if(!resp.ok) throw new Error('Network error ' + resp.status);
  const text = await resp.text();
  // execute any scripts included in the fetched content and inject HTML
  executeAndInjectHTML(contentFrame, text);
  // Show the frame again if we hid it for an initial restore. Do this
  // immediately after injection to avoid the flicker of the placeholder
  // content being visible for a moment.
  if(isInitialRestore && contentFrame){ try{ revealElementWithFade(contentFrame); }catch(e){} }
  void contentFrame.offsetWidth;
  contentFrame.classList.remove('slide-out');
  setTimeout(()=>{ contentFrame.classList.remove('animating'); }, SLIDE_MS + 40);
  try{ localStorage.setItem(LS_LAST, JSON.stringify({ url, title, method: fetchOpts.method || 'GET', timestamp: Date.now() })); }catch(e){}
  currentLoadedUrl = url;
  }catch(e){
    console.error('ThemisHUD loadContent error:', e);
    try{
      contentFrame.innerHTML = `<div style="padding:2%;color:#900">Failed to load: ${title} (${url})</div>`;
      contentFrame.classList.remove('slide-out');
      void contentFrame.offsetWidth;
      setTimeout(()=>{ contentFrame.classList.remove('animating'); }, SLIDE_MS + 40);
  currentLoadedUrl = null;
    }catch(_){
      try{ contentFrame.classList.remove('animating','slide-out'); }catch(__){}
    }
  }
}

document.getElementById('prevPage').addEventListener('click', ()=>{ buttonInitiated = true; const next = Math.max(0,pageIndex-1); animatePageChange(next).catch(()=>{}); });
document.getElementById('nextPage').addEventListener('click', ()=>{ buttonInitiated = true; const maxPage = Math.floor((ThemisHUD_ITEMS.length-1)/PAGE_SIZE); const next = Math.min(maxPage,pageIndex+1); animatePageChange(next).catch(()=>{}); });

async function animatePageChange(newPageIndex){
  if(newPageIndex === pageIndex) return;
  const grid = buttonsGrid;
  const buttons = Array.from(grid.querySelectorAll('.menu-btn'));
  if(!buttons.length) { pageIndex = newPageIndex; renderPage(); return; }
  grid.classList.add('animating');
  const reverse = newPageIndex < pageIndex;
  const nonEmptyButtons = buttons.filter(b => !b.classList.contains('empty'));
  if(nonEmptyButtons.length){
    const orderedOut = reverse ? nonEmptyButtons.slice().reverse() : nonEmptyButtons;
    orderedOut.forEach((b,i)=> setTimeout(()=> b.classList.add('btn-out'), i * PAGINATE_STAGGER));
  }
  await new Promise(r=>setTimeout(r, PAGINATE_MS + nonEmptyButtons.length * PAGINATE_STAGGER));
  pageIndex = newPageIndex; renderPage();
  const newButtons = Array.from(grid.querySelectorAll('.menu-btn'));
  newButtons.forEach(b => b.classList.remove('btn-out'));
  const newNonEmpty = newButtons.filter(b => !b.classList.contains('empty'));
  const orderedIn = reverse ? newNonEmpty.slice().reverse() : newNonEmpty;
  orderedIn.forEach((b,i)=>{ b.classList.add('btn-in'); setTimeout(()=>{ b.classList.remove('btn-in'); }, PAGINATE_MS + i * PAGINATE_STAGGER); });
  setTimeout(()=>{ grid.classList.remove('animating'); }, PAGINATE_MS + newNonEmpty.length * PAGINATE_STAGGER + 40);
}

const backBtnEl = document.getElementById('backBtn');
if(backBtnEl) backBtnEl.addEventListener('click', ()=>{ history.back(); });
const homeBtnEl = document.getElementById('homeBtn');
if(homeBtnEl) homeBtnEl.addEventListener('click', ()=>{ buttonInitiated = true; resetToHomeContent(); });

async function resetToHomeContent(){
  try{ contentFrame.classList.add('animating','slide-out'); await new Promise(r=>setTimeout(r, SLIDE_MS)); }catch(e){}
  contentFrame.innerHTML = initialContentHTML;
  void contentFrame.offsetWidth;
  contentFrame.classList.remove('slide-out');
  setTimeout(()=>{ contentFrame.classList.remove('animating'); }, SLIDE_MS + 40);
  try{ localStorage.setItem(LS_LAST, JSON.stringify({ home: true, timestamp: Date.now() })); }catch(e){}
  if(typeof attachFormHandlers === 'function'){ try{ attachFormHandlers(contentFrame); }catch(e){} }
  currentLoadedUrl = null;
}

window.ThemisHUD = {
  setCharacterName(name){ charNameEl.textContent = name },
  setItems(list){ ThemisHUD_ITEMS.length=0; list.forEach(it=>ThemisHUD_ITEMS.push(it)); pageIndex=0; renderPage(); },
  loadPage(index){ pageIndex = index; renderPage(); },
  // Expose the internal loader so other modules can request content via the HUD API
  loadContent(url, title, opts){ return loadContent(url, title, opts); }
};

// Public API: toggle visibility of an element by its class and data-key.
// Usage: ThemisHUD.toggleVisibility('themis-character-content-block', 'bio')
// Returns true if an element was found and toggled, false otherwise.
window.ThemisHUD.toggleVisibility = function(className, dataKey){
  if(!className || typeof dataKey === 'undefined' || dataKey === null) return false;
  // ensure className doesn't include leading dot
  const cls = className.replace(/^\./, '');
  const rawKey = String(dataKey);
  const escKey = (typeof window.CSS === 'object' && typeof CSS.escape === 'function') ? CSS.escape(rawKey) : rawKey.replace(/"/g, '\\"');

  // Hide all elements that match the provided class first
  const allSelector = `.${cls}`;
  const allEls = Array.from(document.querySelectorAll(allSelector));
  if(!allEls.length) return false;
  allEls.forEach(el => {
    el.hidden = true;
    el.classList.add('hidden');
    try{ el.setAttribute('aria-hidden', 'true'); }catch(e){}
  });

  // Then show the requested element (if present)
  const targetSelector = `.${cls}[data-key="${escKey}"]`;
  const target = document.querySelector(targetSelector);
  if(!target) return false;
  target.hidden = false;
  target.classList.remove('hidden');
  try{ target.setAttribute('aria-hidden', 'false'); }catch(e){}
  return true;
};

// Attach form handlers inside injected fragments so forms can POST via AJAX and refresh the HUD
function attachFormHandlers(container = document){
  if(!container || !(container.querySelectorAll)) return;
  // Only wire forms explicitly opting in via data-ajax="true"
  const forms = Array.from(container.querySelectorAll('form[data-ajax="true"]'));
  forms.forEach(form => {
    // idempotent: skip if we've already wired this form
    if(form.__themis_attached) return;
    form.__themis_attached = true;
    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      // gather form data into a simple object
      const fd = new FormData(form);
      const obj = {};
      fd.forEach((v,k) => {
        if(obj[k] !== undefined){
          if(Array.isArray(obj[k])) obj[k].push(v); else obj[k] = [obj[k], v];
        } else obj[k] = v;
      });
  // choose a target URL in order of preference:
  // 1) explicit form.action
  // 2) the currently loaded HUD fragment (`currentLoadedUrl`)
  // 3) the item URL at the current pageIndex
  // 4) fallback to window.location.href
  let targetUrl = null;
  const action = form.getAttribute('action');
  if(action && action.trim() !== '') targetUrl = action;
  else if(typeof currentLoadedUrl === 'string' && currentLoadedUrl) targetUrl = currentLoadedUrl;
  else if(ThemisHUD_ITEMS[pageIndex] && ThemisHUD_ITEMS[pageIndex].url) targetUrl = ThemisHUD_ITEMS[pageIndex].url;
  else targetUrl = window.location.href;

  // derive a friendly title from the items list when possible
  const item = ThemisHUD_ITEMS.find(it => it.url === targetUrl);
  const title = (item && item.title) ? item.title : (document.title || 'Content');

  // call existing loader with POST and form data as 'form' type
  ThemisHUD.loadContent(targetUrl, title, { method: 'POST', postData: obj, postDataType: 'form' });
    }, { passive:false });
  });
}

function restoreState(){
  try{
    const savedPage = localStorage.getItem(LS_PAGE);
    if(savedPage !== null) pageIndex = parseInt(savedPage,10)||0;
    const last = localStorage.getItem(LS_LAST);
    const savedChar = localStorage.getItem(LS_CHAR);
    if(savedChar) charNameEl.textContent = savedChar;
    renderPage();
    if(last){
      const info = JSON.parse(last);
      if(info && info.home){ try{ resetToHomeContent(); }catch(e){} } else {
        if(info && info.url){
          // Only treat this as an "initial" restore once per tab/session so
          // users who manually reload won't repeatedly trigger the hidden
          // content-frame behavior. We persist the marker in sessionStorage
          // which survives reloads but is scoped to the tab (good for the
          // redirect-from-gate-once behavior).
          let doInitial = false;
          try{
            const k = LS_PREFIX + 'initialRestoreDone';
            if(typeof sessionStorage !== 'undefined' && !sessionStorage.getItem(k)){
              sessionStorage.setItem(k, '1');
              doInitial = true;
            }
          }catch(e){ /* ignore storage errors and fall back to non-initial */ }
          if(doInitial) loadContent(info.url, info.title, { method: 'GET', initial: true });
          else loadContent(info.url, info.title, { method: 'GET' });
        }
      }
    } else { try{ playInitialSlide(); }catch(e){} }
  }catch(e){ renderPage(); }
}

async function playInitialSlide(){
  try{ contentFrame.classList.remove('slide-in','no-transition'); contentFrame.classList.add('animating','slide-out'); await new Promise(r=>setTimeout(r, SLIDE_MS)); }catch(e){}
  contentFrame.innerHTML = initialContentHTML;
  contentFrame.classList.add('no-transition','slide-in');
  void contentFrame.offsetWidth;
  contentFrame.classList.remove('no-transition','slide-out');
  setTimeout(()=>{ contentFrame.classList.remove('animating','slide-in'); }, SLIDE_MS + 40);
}

restoreState();

// Idle overlay logic: show overlay after 15s of no input over the HUD
(() => {
  const IDLE_MS = 15000; // 15 seconds
  let idleTimer = null;
  const hud = document.getElementById('hud');
  const overlay = document.getElementById('hudIdleOverlay');
  let isIdle = false;

  function setIdle(state){
    isIdle = !!state;
    if(!overlay) return;
    if(isIdle){ overlay.classList.add('show'); overlay.setAttribute('aria-hidden','false'); }
    else { overlay.classList.remove('show'); overlay.setAttribute('aria-hidden','true'); }
  }

  function resetTimer(){
    if(idleTimer) clearTimeout(idleTimer);
    if(isIdle){ setIdle(false); }
    idleTimer = setTimeout(()=> setIdle(true), IDLE_MS);
  }

  // events that count as activity
  ['mousemove','mousedown','keydown','touchstart'].forEach(ev => {
    window.addEventListener(ev, resetTimer, { passive:true });
  });

  // clicking the overlay dismisses it; some SL viewers need double-activation so allow immediate re-show only after next inactivity
  if(overlay){
    overlay.addEventListener('click', (e)=>{ e.stopPropagation(); setIdle(false); try{ overlay.blur(); }catch(_){}; resetTimer(); });
  }

  // start the timer
  resetTimer();
})();
