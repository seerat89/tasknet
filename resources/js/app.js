import './menu';
import './realtime';
import './project-search';
import './project-directory';

const showSaveStatus = (message, isError = false) => {
    const status = document.getElementById('save-status');

    if (!status) {
        return;
    }

    status.classList.toggle('text-red-700', isError);
    status.classList.toggle('text-teal-800', !isError);

    const mark = document.createElement('span');
    mark.setAttribute('aria-hidden', 'true');
    mark.textContent = isError ? '! ' : '✓ ';

    status.replaceChildren(mark, message);
};

document.addEventListener('click', async (event) => {
    const copyButton = event.target.closest('[data-copy-text]');

    if (copyButton) {
        try {
            await navigator.clipboard.writeText(copyButton.dataset.copyText);
            showSaveStatus('Credentials copied');
        } catch {
            showSaveStatus('Copy failed. Select the credentials and copy them manually, or download them.', true);
        }

        return;
    }

    if (event.target.closest('[data-download-credentials]')) {
        showSaveStatus('Login details downloaded');
    }
});

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]');

    if (opener) {
        document.getElementById(opener.dataset.dialogOpen)?.showModal();

        return;
    }

    if (event.target.closest('[data-dialog-close]')) {
        event.target.closest('dialog')?.close();
    }
});
