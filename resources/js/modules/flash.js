export function initFlash() {
    document.querySelectorAll('[data-flash]').forEach((element) => {
        const timeout = Number(element.dataset.flashTimeout || 4000);

        window.setTimeout(() => {
            element.classList.add('is-dismissed');
        }, timeout);
    });
}
