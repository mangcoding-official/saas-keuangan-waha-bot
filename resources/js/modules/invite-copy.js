export function initInviteCopy() {
    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const copyText = button.dataset.copyText || '';
            const defaultLabel = button.dataset.copyLabel || button.textContent?.trim() || 'Copy';
            const successLabel = button.dataset.copySuccessLabel || 'Copied';

            if (!copyText) {
                return;
            }

            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(copyText);
                } else {
                    const helper = document.createElement('textarea');
                    helper.value = copyText;
                    helper.setAttribute('readonly', 'readonly');
                    helper.style.position = 'absolute';
                    helper.style.left = '-9999px';
                    document.body.appendChild(helper);
                    helper.select();
                    document.execCommand('copy');
                    helper.remove();
                }

                button.textContent = successLabel;

                window.setTimeout(() => {
                    button.textContent = defaultLabel;
                }, 1600);
            } catch (error) {
                button.textContent = defaultLabel;
            }
        });
    });
}
