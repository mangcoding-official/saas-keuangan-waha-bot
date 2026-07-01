import './bootstrap';
import { initAccountForm } from './modules/account-form';
import { initActivationSuccess } from './modules/activation-success';
import { initFlash } from './modules/flash';
import { initInviteCopy } from './modules/invite-copy';
import { initLoginForm } from './modules/login-form';
import { initModals } from './modules/modal';
import { initSidebar } from './modules/sidebar';
import { initTopbarAccount } from './modules/topbar-account';

document.addEventListener('DOMContentLoaded', () => {
    initAccountForm();
    initActivationSuccess();
    initFlash();
    initInviteCopy();
    initLoginForm();
    initModals();
    initSidebar();
    initTopbarAccount();
});
