// Feedback for ordinary page requests: a thin progress bar as soon as a navigation or form submit starts,
// a branded loader if it takes noticeably long, and a busy state on the submitted button. TaskNet is a
// traditional server-rendered app, so the new document replaces this one; nothing here intercepts navigation.
const progress = document.querySelector('[data-progress]');
const loader = document.querySelector('[data-loader]');
const status = document.querySelector('[data-loading-status]');

const loaderDelayMilliseconds = 500;
// A slow request keeps its loading state; after this long it only says so, instead of quietly looking idle.
const stillLoadingMilliseconds = 20000;

const submitting = new Map();
let loaderTimer = null;
let stillLoadingTimer = null;
let active = false;

const show = (element) => {
    element.hidden = false;
    element.showPopover?.();
};

const hide = (element) => {
    if (element.matches(':popover-open')) {
        element.hidePopover();
    }

    element.hidden = true;
};

const restoreForms = () => {
    submitting.forEach((restore) => restore());
    submitting.clear();
};

const stop = () => {
    clearTimeout(loaderTimer);
    clearTimeout(stillLoadingTimer);
    active = false;

    if (progress && loader && status) {
        hide(progress);
        hide(loader);
        status.textContent = '';
    }

    restoreForms();
};

const start = () => {
    if (active || !progress || !loader || !status) {
        return;
    }

    active = true;
    show(progress);

    // Quick requests finish before this fires, so the loader never flashes for them.
    loaderTimer = setTimeout(() => {
        show(loader);
        status.textContent = 'Loading…';
    }, loaderDelayMilliseconds);

    stillLoadingTimer = setTimeout(() => {
        show(loader);
        status.textContent = 'Still loading…';
    }, stillLoadingMilliseconds);
};

const isInternalNavigation = (link, event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    if (link.hasAttribute('download') || link.hasAttribute('data-download-credentials') || link.hasAttribute('data-no-loading')) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (!['http:', 'https:'].includes(link.protocol) || link.origin !== window.location.origin) {
        return false;
    }

    // A link to a spot on the current page does not load a new document.
    return !(link.hash && link.pathname === window.location.pathname && link.search === window.location.search);
};

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]');

    if (link && isInternalNavigation(link, event)) {
        start();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
        return;
    }

    // Searches (GET) are handled by their own scripts, and dialog or new-tab forms do not replace this page.
    if (form.method !== 'post' || (form.target && form.target !== '_self') || form.hasAttribute('data-no-loading')) {
        return;
    }

    // The browser only fires submit once validation has passed, so a second submit here is a double click.
    if (submitting.has(form)) {
        event.preventDefault();

        return;
    }

    const button = event.submitter ?? form.querySelector('[type="submit"]');

    if (button instanceof HTMLButtonElement && button.dataset.loadingText) {
        const original = button.innerHTML;

        // Keep the button's width so the label change does not move the layout.
        button.style.minWidth = `${button.getBoundingClientRect().width}px`;
        button.textContent = button.dataset.loadingText;
        button.dataset.loading = '';
        button.setAttribute('aria-disabled', 'true');

        submitting.set(form, () => {
            button.innerHTML = original;
            button.style.minWidth = '';
            delete button.dataset.loading;
            button.removeAttribute('aria-disabled');
        });
    } else {
        submitting.set(form, () => {});
    }

    start();
});

// Coming back through the browser's back/forward cache restores the page as it was left, mid-request.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        stop();
    }
});

// Escape is the browser's own "stop loading" key. A cancelled request fires no event on the page, so this is how
// a stopped navigation or submit gets its buttons back; a request that is merely slow is never reset by a timer.
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && active) {
        stop();
    }
});
