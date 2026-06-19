import './bootstrap';
import { initFlash } from './modules/flash';
import { initModals } from './modules/modal';
import { initSidebar } from './modules/sidebar';

document.addEventListener('DOMContentLoaded', () => {
    initFlash();
    initModals();
    initSidebar();
});
