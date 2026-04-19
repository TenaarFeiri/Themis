// CharacterHUD class encapsulates menu behavior for the character panel
class CharacterHUD {
    constructor(root = document){
        this.root = root;
        // find the menu container and content container inside this root
    this.menuContainer = this.root.querySelector('.themis-profile-menu');
    this.contentContainer = this.root.querySelector('.themis-profile-content');
        if(!this.menuContainer || !this.contentContainer) {
            console.debug('CharacterHUD: missing menu or content container for root', this.root);
            return;
        }
    this.menuItems = Array.from(this.menuContainer.querySelectorAll('.themis-profile-menu-item'));
    this.contentBlocks = Array.from(this.contentContainer.querySelectorAll('.themis-profile-content-block'));
        console.debug('CharacterHUD init:', { menuCount: this.menuItems.length, contentCount: this.contentBlocks.length });
        this.init();
    }

    init(){
        if(!this.menuItems.length || !this.contentBlocks.length) return;
        // Use event delegation on the container — more robust than binding each button
        this.menuContainer.addEventListener('click', (ev) => {
            const btn = ev.target.closest('.themis-character-menu-item');
            console.debug('CharacterHUD click event target:', ev.target, 'closest button:', btn);
            if(!btn || !this.menuContainer.contains(btn)) return;
            this.activateButton(btn);
        });

        // keyboard activation (Enter / Space) for accessibility
        this.menuContainer.addEventListener('keydown', (ev) => {
            if(ev.key === 'Enter' || ev.key === ' ') {
                const btn = ev.target.closest('.themis-character-menu-item');
                if(btn) {
                    ev.preventDefault();
                    this.activateButton(btn);
                }
            }
        });

        // default to first
        const firstKey = this.menuItems[0]?.dataset?.key;
        if(firstKey) this.setActive(firstKey);
    }

    activateButton(btn){
        const key = btn.dataset.key;
    console.debug('CharacterHUD activateButton clicked for key:', key);
        if(!key) return;
        // toggle visual active class
        this.menuItems.forEach(b => b.classList.toggle('active', b === btn));
        this.setActive(key);
    // visual blink for 3s to help diagnose click passing
    btn.classList.add('blink');
    setTimeout(()=> btn.classList.remove('blink'), 3000);
    }

    setActive(key){
        console.debug('CharacterHUD setActive:', key);
        this.contentBlocks.forEach(c => {
            const match = (c.dataset.key === key);
            c.hidden = !match;
            console.debug(' - toggled block', c.dataset.key, 'visible=', match);
        });
    }
}

// Auto-initialize when the page is ready
document.addEventListener('DOMContentLoaded', () => {
    // Limit scope: only initialize inside the themis-character-hud wrapper to avoid collisions
    const wrappers = document.querySelectorAll('.themis-profile-hud');
    wrappers.forEach(wrap => new CharacterHUD(wrap));
});
