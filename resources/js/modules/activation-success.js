export function initActivationSuccess() {
    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.getAttribute('data-copy-text') || '';

            if (text === '') {
                return;
            }

            try {
                await navigator.clipboard.writeText(text);
                button.classList.add('is-copied');

                window.setTimeout(() => {
                    button.classList.remove('is-copied');
                }, 1600);
            } catch {
                window.prompt('Salin kode aktivasi ini:', text);
            }
        });
    });

    document.querySelectorAll('[data-countdown]').forEach((element) => {
        const expiresAt = element.getAttribute('data-expires-at');
        const label = element.querySelector('[data-countdown-label]');

        if (!expiresAt || !label) {
            return;
        }

        const update = () => {
            const remainingMs = new Date(expiresAt).getTime() - Date.now();

            if (remainingMs <= 0) {
                label.textContent = 'Kode sudah kedaluwarsa';
                element.classList.add('is-expired');
                return false;
            }

            const totalSeconds = Math.floor(remainingMs / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;

            label.textContent = `Berakhir dalam ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            return true;
        };

        if (!update()) {
            return;
        }

        const intervalId = window.setInterval(() => {
            if (!update()) {
                window.clearInterval(intervalId);
            }
        }, 1000);
    });
}
