export function initLoginForm() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const field = button.closest('.login-input-wrap')?.querySelector('[data-password-input]');

        if (!(field instanceof HTMLInputElement)) {
            return;
        }

        button.addEventListener('click', () => {
            field.type = field.type === 'password' ? 'text' : 'password';
            button.classList.toggle('is-active', field.type === 'text');
        });
    });
}
