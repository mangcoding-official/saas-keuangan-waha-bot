export function initTopbarAccount() {
    const accountMenus = document.querySelectorAll('[data-topbar-account]');

    accountMenus.forEach((accountMenu) => {
        const trigger = accountMenu.querySelector('[data-topbar-account-trigger]');
        const popover = accountMenu.querySelector('[data-topbar-account-popover]');
        const closeButton = accountMenu.querySelector('[data-topbar-account-close]');

        if (!trigger || !popover) {
            return;
        }

        const closePopover = () => {
            trigger.setAttribute('aria-expanded', 'false');
            popover.hidden = true;
        };

        const openPopover = () => {
            trigger.setAttribute('aria-expanded', 'true');
            popover.hidden = false;
        };

        trigger.addEventListener('click', () => {
            const isExpanded = trigger.getAttribute('aria-expanded') === 'true';

            if (isExpanded) {
                closePopover();
                return;
            }

            accountMenus.forEach((menuNode) => {
                const menuTrigger = menuNode.querySelector('[data-topbar-account-trigger]');
                const menuPopover = menuNode.querySelector('[data-topbar-account-popover]');

                if (menuTrigger && menuPopover) {
                    menuTrigger.setAttribute('aria-expanded', 'false');
                    menuPopover.hidden = true;
                }
            });

            openPopover();
        });

        closeButton?.addEventListener('click', closePopover);

        document.addEventListener('click', (event) => {
            if (!accountMenu.contains(event.target)) {
                closePopover();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePopover();
            }
        });
    });
}
