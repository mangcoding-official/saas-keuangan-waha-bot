export function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const trigger = document.querySelector('[data-sidebar-toggle]');

    if (!sidebar || !trigger) {
        return;
    }

    trigger.addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
    });
}
