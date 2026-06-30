import './bootstrap';

import Alpine from 'alpinejs';
import { disburseListSearchMixin } from './disburse-list-search';

window.Alpine = Alpine;
window.disburseListSearchMixin = disburseListSearchMixin;

const refreshLucideIcons = () => {
    if (typeof window.lucide?.createIcons === 'function') {
        window.lucide.createIcons();
    }
};

document.addEventListener('alpine:init', () => {
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
