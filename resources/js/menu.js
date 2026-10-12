const toggle = document.querySelector('[data-menu-toggle]');
const menu = document.getElementById('mobile-menu');

if (toggle && menu) {
    const setOpen = (open) => {
        menu.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.querySelector('[data-menu-icon="open"]')?.classList.toggle('hidden', open);
        toggle.querySelector('[data-menu-icon="close"]')?.classList.toggle('hidden', !open);
    };

    toggle.addEventListener('click', () => setOpen(menu.hidden));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches) {
            setOpen(false);
        }
    });
}
