import './bootstrap';
import { initAccountForm } from './modules/account-form';
import { initActivationSuccess } from './modules/activation-success';
import { initFlash } from './modules/flash';
import { initLoginForm } from './modules/login-form';
import { initModals } from './modules/modal';
import { initSidebar } from './modules/sidebar';

document.addEventListener('DOMContentLoaded', () => {
    initAccountForm();
    initActivationSuccess();
    initFlash();
    initLoginForm();
    initModals();
    initSidebar();
});
