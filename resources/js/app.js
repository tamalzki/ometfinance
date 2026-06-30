import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const refreshLucideIcons = () => {
    if (typeof window.lucide?.createIcons === 'function') {
        window.lucide.createIcons();
    }
};

document.addEventListener('alpine:initialized', () => {
    refreshLucideIcons();

    let iconTimer;
    const main = document.querySelector('main');
    if (main && typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(() => {
            clearTimeout(iconTimer);
            iconTimer = setTimeout(refreshLucideIcons, 80);
        });
        observer.observe(main, { childList: true, subtree: true });
    }
});

Alpine.start();
