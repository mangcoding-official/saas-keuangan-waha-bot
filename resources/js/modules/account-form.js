export function initAccountForm() {
    const forms = document.querySelectorAll('[data-account-form]');

    forms.forEach((form) => {
        const popover = form.querySelector('[data-account-type-popover]');
        const trigger = form.querySelector('[data-account-type-trigger]');
        const menu = form.querySelector('[data-account-type-menu]');
        const hiddenInput = form.querySelector('[data-account-type-input]');
        const label = form.querySelector('[data-account-type-label]');
        const options = form.querySelectorAll('[data-account-type-option]');

        if (popover && trigger && menu && hiddenInput && label) {
            trigger.addEventListener('click', () => {
                const expanded = trigger.getAttribute('aria-expanded') === 'true';
                trigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                menu.hidden = expanded;
            });

            options.forEach((option) => {
                option.addEventListener('click', () => {
                    hiddenInput.value = option.dataset.value ?? '';
                    label.textContent = option.dataset.label ?? 'Pilih tipe akun';
                    trigger.setAttribute('aria-expanded', 'false');
                    menu.hidden = true;

                    options.forEach((node) => node.classList.remove('is-selected'));
                    option.classList.add('is-selected');
                });
            });

            document.addEventListener('click', (event) => {
                if (!popover.contains(event.target)) {
                    trigger.setAttribute('aria-expanded', 'false');
                    menu.hidden = true;
                }
            });
        }

        const defaultToggle = form.querySelector('[data-default-toggle]');
        const defaultInput = form.querySelector('[data-default-toggle-input]');

        if (!defaultToggle || !defaultInput) {
            return;
        }

        defaultToggle.addEventListener('click', () => {
            const isOn = defaultInput.value === '1';
            const nextValue = isOn ? '0' : '1';

            defaultInput.value = nextValue;
            defaultToggle.classList.toggle('is-on', nextValue === '1');
        });
    });
}
