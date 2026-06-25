import './bootstrap';
import { initActivationSuccess } from './modules/activation-success';
import { initFlash } from './modules/flash';
import { initModals } from './modules/modal';
import { initSidebar } from './modules/sidebar';

document.addEventListener('DOMContentLoaded', () => {
    initActivationSuccess();
    initFlash();
    initModals();
    initSidebar();
});
