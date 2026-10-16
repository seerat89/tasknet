const form = document.querySelector('[data-project-filter]');
const results = document.querySelector('[data-project-results]');

// Search and sort apply as you type or choose, by reloading just the results from the server.
if (form && results) {
    const debounceMilliseconds = 250;
    let timer = null;
    let controller = null;

    const update = async () => {
        const params = new URLSearchParams(new FormData(form));

        for (const [key, value] of [...params]) {
            if (value === '') {
                params.delete(key);
            }
        }

        const url = `${form.action}${params.size ? `?${params}` : ''}`;

        controller?.abort();
        controller = new AbortController();

        try {
            const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'text/html' } });

            if (!response.ok) {
                form.submit();

                return;
            }

            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = page.querySelector('[data-project-results]');

            if (fresh) {
                results.replaceChildren(...fresh.childNodes);
                history.replaceState(null, '', url);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                form.submit();
            }
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(timer);
        update();
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('input[type="search"]')) {
            clearTimeout(timer);
            timer = setTimeout(update, debounceMilliseconds);
        }
    });

    form.addEventListener('change', (event) => {
        if (event.target.matches('select')) {
            clearTimeout(timer);
            update();
        }
    });
}
