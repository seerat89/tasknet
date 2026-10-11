import './realtime';

let credentialsSaved = false;

const acknowledgement = () => document.querySelector('[data-acknowledge]');

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

const syncContinue = () => {
    const checkbox = acknowledgement();

    if (!checkbox) {
        return;
    }

    checkbox.disabled = !credentialsSaved;

    if (!credentialsSaved) {
        checkbox.checked = false;
    }

    const hint = document.getElementById('acknowledge-hint');

    if (hint) {
        hint.hidden = credentialsSaved;
    }

    const continueButton = document.getElementById(checkbox.dataset.acknowledge);

    if (continueButton) {
        continueButton.disabled = !(credentialsSaved && checkbox.checked);
    }
};

const markCredentialsSaved = (message) => {
    credentialsSaved = true;
    showSaveStatus(message);
    syncContinue();
};

document.addEventListener('click', async (event) => {
    const copyButton = event.target.closest('[data-copy-text]');

    if (copyButton) {
        try {
            await navigator.clipboard.writeText(copyButton.dataset.copyText);
            markCredentialsSaved('Credentials copied');
        } catch {
            showSaveStatus('Copy failed. Select the credentials and copy them manually, or download them.', true);
        }

        return;
    }

    if (event.target.closest('[data-download-credentials]')) {
        markCredentialsSaved('Login details downloaded');
    }
});

document.addEventListener('change', (event) => {
    if (event.target.matches?.('[data-acknowledge]')) {
        syncContinue();
    }
});

window.addEventListener('pageshow', syncContinue);

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
