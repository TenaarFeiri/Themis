/*
  Define your menu items here. Each item can have:
    - title: label shown under the icon
    - url: path to load into the content area (PHP/HTML endpoints)
    - img: optional full-button background image (URL)
    - icon: optional small icon shown above the label (URL)

  Edit this array to add/remove menu entries.
*/
const ThemisHUD_ITEMS = [
  // Example items (replace URLs with your real endpoints or file paths):
  { title: 'Bag', url: '/themis/content/inventory.php', img: null, icon: '/themis/assets/icons/inventory.png' },
  { title: 'Profile',   url: '/themis/content/profile.php',   img: null, icon: '/themis/assets/icons/profile.png' },
  { title: 'Map',       url: '/themis/content/map.php',       img: null, icon: '/themis/assets/icons/map.png' },
  { title: 'Skills',    url: '/themis/content/skills.php',    img: null, icon: '/themis/assets/icons/skills.png' },
  { title: 'Quests',    url: '/themis/content/quests.php',    img: null, icon: '/themis/assets/icons/quests.png' },
  { title: 'Options',  url: '/themis/content/settings.php',  img: null, icon: '/themis/assets/icons/settings.png' },
  { title: 'Something',    url: '/themis/content/logout.php',    img: null, icon: '/themis/assets/icons/logout.png' },
  { title: 'Another test', url: '/themis/content/another_test.php', img: null, icon: '/themis/assets/icons/another_test.png' },
  { title: 'Yet another test', url: '/themis/content/yet_another_test.php', img: null, icon: '/themis/assets/icons/yet_another_test.png' },
  { title: 'Final test', url: '/themis/content/final_test.php', img: null, icon: '/themis/assets/icons/final_test.png' },
  // Add more items as needed. The UI paginates automatically every 6 items.
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
// preserve the initial content so "Home" can restore it (the state of the DOM before any menu loads)
const initialContentHTML = contentFrame ? contentFrame.innerHTML : '';
// slide timing (ms) — keep in sync with CSS transition duration
const SLIDE_MS = 800;
// track currently loaded URL so we can skip animation when re-opening the same page
let currentLoadedUrl = null;
// only play slide animations when a user pressed a button to request navigation
let buttonInitiated = false;
// pagination animation timings (ms)
const PAGINATE_MS = 50;        // base duration for buttons to animate out/in
const PAGINATE_STAGGER = 50;    // stagger between buttons
// small startup delay for the slide to begin (fraction of the full slide)
const START_MS = Math.max(80, Math.round(SLIDE_MS * 0.12));

// Compute application root (attempt to find '/themis' in the current path). This gives a stable base to build absolute URLs.
const APP_ROOT = (function(){
  try{
    const p = location.pathname;
    const idx = p.indexOf('/themis');
    if(idx >= 0){
      return location.origin + p.slice(0, idx + '/themis'.length);
    }
    // fallback to origin
    return location.origin;
  }catch(e){ return '' }
})();

function fullPath(p){
  if(!p) return p;
  if(p.startsWith('//') || p.startsWith('http://') || p.startsWith('https://')) return p;
  if(p.startsWith('/')) return location.origin + p; // absolute path on this host
  // relative path -> prefix with APP_ROOT
  return APP_ROOT.replace(/\/$/, '') + '/' + p.replace(/^\//, '');
}

// Resolve configured item paths to absolute URLs so runtime always knows exact locations.
ThemisHUD_ITEMS.forEach(it=>{
  if(it.url) it.url = fullPath(it.url);
  if(it.img) it.img = fullPath(it.img);
  if(it.icon) it.icon = fullPath(it.icon);
});

function renderPage(){
  buttonsGrid.innerHTML='';
  const start = pageIndex * PAGE_SIZE;
  const pageItems = ThemisHUD_ITEMS.slice(start,start+PAGE_SIZE);
  // Ensure grid always shows 6 boxes to maintain symmetry
  for(let i=0;i<PAGE_SIZE;i++){
    const it = pageItems[i];
    const btn = document.createElement('div');
    btn.className='menu-btn';
    if(it){
      if(it.img){ btn.style.backgroundImage = `url(${it.img})`; }
      else { btn.style.backgroundImage = `linear-gradient(135deg, #bdb494, #d6cfae)` }
      // optional icon above the label
      if(it.icon){
        const iconEl = document.createElement('img');
        iconEl.className = 'icon';
        iconEl.src = it.icon;
        iconEl.alt = it.title + ' icon';
        btn.appendChild(iconEl);
      }
      const label = document.createElement('div'); label.className='label'; label.textContent = it.title; btn.appendChild(label);
      // click passes method/postData if present on the item
      btn.addEventListener('click', ()=>{
        // mark that this load was initiated by a user button so animations are allowed
        buttonInitiated = true;
        // update pageIndex to current page where this button lives
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
}

async function loadContent(url,title, opts = {}){
  // opts: { method: 'GET'|'POST', postData: object|string, postDataType: 'form'|'json' }
  try{
  let fetchOpts = { cache: 'no-store' };
    const method = (opts.method || 'GET').toUpperCase();
    if(method === 'POST'){
      fetchOpts.method = 'POST';
      if(opts.postDataType === 'json'){
        fetchOpts.headers = { 'Content-Type':'application/json' };
        fetchOpts.body = typeof opts.postData === 'string' ? opts.postData : JSON.stringify(opts.postData || {});
      } else {
        // form-encoded
        fetchOpts.headers = { 'Content-Type':'application/x-www-form-urlencoded' };
        if(typeof opts.postData === 'string') fetchOpts.body = opts.postData;
        else if(typeof opts.postData === 'object'){
          fetchOpts.body = Object.keys(opts.postData).map(k=>encodeURIComponent(k)+'='+encodeURIComponent(opts.postData[k])).join('&');
        } else fetchOpts.body = '';
      }
    }

  // include credentials so cookies set by the server are sent with requests
  fetchOpts.credentials = 'include';
  // if we're reloading the same URL, skip the out-animation to avoid repeated motion
  if(url === currentLoadedUrl){
    const resp = await fetch(url, fetchOpts);
    if(!resp.ok) throw new Error('Network error ' + resp.status);
    const text = await resp.text();
    contentFrame.innerHTML = text;
    // persist last loaded info
    try{ localStorage.setItem(LS_LAST, JSON.stringify({ url, title, method: fetchOpts.method || 'GET', timestamp: Date.now() })); }catch(e){}
    return;
  }

  // Only animate if a user pressed a button. Read-and-clear the flag so programmatic calls don't animate.
  const willAnimate = !!buttonInitiated; buttonInitiated = false;
  if(willAnimate){
    try{
      contentFrame.classList.add('animating','slide-out');
      // small delay to let the slide-out begin before network (keeps motion responsive)
      // wait the full slide duration so the element is fully off-screen before loading
      await new Promise(r=>setTimeout(r, SLIDE_MS));
    }catch(e){}
  }

  const resp = await fetch(url, fetchOpts);
    if(!resp.ok) throw new Error('Network error ' + resp.status);
    const text = await resp.text();

    // swap in new content and animate back in
    contentFrame.innerHTML = text;
    // force a styles/layout flush before removing classes
    void contentFrame.offsetWidth;
  contentFrame.classList.remove('slide-out');
  // allow re-interaction after animation completes (use SLIDE_MS so this scales)
  setTimeout(()=>{ contentFrame.classList.remove('animating'); }, SLIDE_MS + 40);

  // persist last loaded info so refresh can restore
  try{ localStorage.setItem(LS_LAST, JSON.stringify({ url, title, method: fetchOpts.method || 'GET', timestamp: Date.now() })); }catch(e){}

  // update tracker so re-opening the same page won't animate
  currentLoadedUrl = url;
  }catch(e){
    console.error('ThemisHUD loadContent error:', e);
    // show a friendly error message and make sure the content area is visible again
    try{
      contentFrame.innerHTML = `<div style="padding:2%;color:#900">Failed to load: ${title} (${url})</div>`;
      // If we had animated out, remove the slide-out so the error content can slide back in
      contentFrame.classList.remove('slide-out');
      // force a layout flush in case classes changed
      void contentFrame.offsetWidth;
      // clear the animating flag after the normal slide duration so interaction is restored
      setTimeout(()=>{ contentFrame.classList.remove('animating'); }, SLIDE_MS + 40);
      // clear tracked URL so a follow-up user click will re-run the animation/fetch
      currentLoadedUrl = null;
    }catch(_){
      // final fallback: directly ensure the element is interactable
      try{ contentFrame.classList.remove('animating','slide-out'); }catch(__){}
    }
  }
}

document.getElementById('prevPage').addEventListener('click', ()=>{
  // mark as user-initiated so animations play
  buttonInitiated = true;
  const next = Math.max(0,pageIndex-1);
  animatePageChange(next).catch(()=>{});
});
document.getElementById('nextPage').addEventListener('click', ()=>{
  // mark as user-initiated so animations play
  buttonInitiated = true;
  const maxPage = Math.floor((ThemisHUD_ITEMS.length-1)/PAGE_SIZE);
  const next = Math.min(maxPage,pageIndex+1);
  animatePageChange(next).catch(()=>{});
});

// Animate menu page change: current visible buttons float out, then set pageIndex and render new buttons which float in
async function animatePageChange(newPageIndex){
  if(newPageIndex === pageIndex) return; // nothing to do
  const grid = buttonsGrid;
  const buttons = Array.from(grid.querySelectorAll('.menu-btn'));
  if(!buttons.length) { pageIndex = newPageIndex; renderPage(); return; }

  grid.classList.add('animating');
  // determine direction: if we're going back a page, reverse the stagger order
  const reverse = newPageIndex < pageIndex;

  // Only animate non-empty buttons. Build an ordered list depending on direction so the stagger looks natural.
  const nonEmptyButtons = buttons.filter(b => !b.classList.contains('empty'));
  if(nonEmptyButtons.length){
    const orderedOut = reverse ? nonEmptyButtons.slice().reverse() : nonEmptyButtons;
    orderedOut.forEach((b,i)=> setTimeout(()=> b.classList.add('btn-out'), i * PAGINATE_STAGGER));
  }

  // wait for base duration + actual staggers (use non-empty count for accurate timing)
  await new Promise(r=>setTimeout(r, PAGINATE_MS + nonEmptyButtons.length * PAGINATE_STAGGER));

  // set the new page and render fresh buttons
  pageIndex = newPageIndex; renderPage();

  // apply btn-in to newly created buttons with staggered delays.
  const newButtons = Array.from(grid.querySelectorAll('.menu-btn'));
  // ensure any leftover classes are cleared
  newButtons.forEach(b => b.classList.remove('btn-out'));

  const newNonEmpty = newButtons.filter(b => !b.classList.contains('empty'));
  const orderedIn = reverse ? newNonEmpty.slice().reverse() : newNonEmpty;
  orderedIn.forEach((b,i)=>{
    b.classList.add('btn-in');
    setTimeout(()=>{ b.classList.remove('btn-in'); }, PAGINATE_MS + i * PAGINATE_STAGGER);
  });

  // allow interactions again (use the count of animated buttons for timing)
  setTimeout(()=>{ grid.classList.remove('animating'); }, PAGINATE_MS + newNonEmpty.length * PAGINATE_STAGGER + 40);
}

document.getElementById('backBtn').addEventListener('click', ()=>{
  history.back();
});
document.getElementById('homeBtn').addEventListener('click', ()=>{
  // restore the HUD to the initial content captured on page load
  buttonInitiated = true;
  resetToHomeContent();
});

async function resetToHomeContent(){
  try{
    // animate out
    contentFrame.classList.add('animating','slide-out');
    // wait the full slide duration so the element is fully off-screen before restoring
    await new Promise(r=>setTimeout(r, SLIDE_MS));
  }catch(e){}

  // restore initial HTML snapshot
  contentFrame.innerHTML = initialContentHTML;
  // force reflow then animate back in
  void contentFrame.offsetWidth;
  contentFrame.classList.remove('slide-out');
  // clear animating after the slide completes
  setTimeout(()=>{ contentFrame.classList.remove('animating'); }, SLIDE_MS + 40);

  // mark persisted state as "home" so refresh restores the initial snapshot
  try{ localStorage.setItem(LS_LAST, JSON.stringify({ home: true, timestamp: Date.now() })); }catch(e){}

  // re-bind any optional form handlers if present
  if(typeof attachFormHandlers === 'function'){
    try{ attachFormHandlers(contentFrame); }catch(e){}
  }
  currentLoadedUrl = null;
}

// Expose a tiny API for integration: setCharacterName, setItems
window.ThemisHUD = {
  setCharacterName(name){ charNameEl.textContent = name },
  setItems(list){
    // list: [{title,url,img}]
    ThemisHUD_ITEMS.length=0; list.forEach(it=>ThemisHUD_ITEMS.push(it)); pageIndex=0; renderPage();
  },
  loadPage(index){ pageIndex = index; renderPage(); }
};

// restore saved state if present
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
      // if persisted state marks 'home', restore the initial snapshot
      if(info && info.home){
        try{ resetToHomeContent(); }catch(e){}
      } else {
        // attempt to reload the last URL using GET (POST bodies are not persisted for safety)
        if(info && info.url) loadContent(info.url, info.title, { method: 'GET' });
      }
    } else {
      // no persisted last state — play a full slide-out then slide-in of the initial content
      try{ playInitialSlide(); }catch(e){}
    }
  }catch(e){ renderPage(); }
}

// Play an initial full out-then-in slide of the HUD content
async function playInitialSlide(){
  try{
    // ensure any animation classes are cleared
    contentFrame.classList.remove('slide-in','no-transition');
    // animate fully out (to right)
    contentFrame.classList.add('animating','slide-out');
    await new Promise(r=>setTimeout(r, SLIDE_MS));
  }catch(e){}

  // restore initial content snapshot while off-screen
  contentFrame.innerHTML = initialContentHTML;
  // snap to off-left start without transition
  contentFrame.classList.add('no-transition','slide-in');
  void contentFrame.offsetWidth; // force layout
  // remove no-transition so slide-in animates
  contentFrame.classList.remove('no-transition','slide-out');

  // clear animating after slide completes
  setTimeout(()=>{ contentFrame.classList.remove('animating','slide-in'); }, SLIDE_MS + 40);
}

restoreState();
