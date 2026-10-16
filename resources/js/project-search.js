const card = document.querySelector('[data-project-chart]');

// Client-side search over the project data the dashboard already contains (a workspace holds few projects).
if (card) {
    const input = card.querySelector('[data-project-search]');
    const clear = card.querySelector('[data-project-search-clear]');
    const list = card.querySelector('[data-project-rows]');
    const empty = card.querySelector('[data-project-empty]');
    const template = card.querySelector('template[data-project-row]');
    const projects = JSON.parse(card.querySelector('[data-project-data]').textContent);
    const storageKey = 'tasknet.dashboard.project-search';
    const limit = 5;
    const debounceMilliseconds = 150;
    let timer = null;

    const store = (value) => {
        try {
            value ? sessionStorage.setItem(storageKey, value) : sessionStorage.removeItem(storageKey);
        } catch {
            // Storage can be unavailable; the search simply is not remembered across refreshes.
        }
    };

    const render = (query) => {
        const needle = query.trim().toLowerCase();
        const matches = projects
            .filter((project) => !needle || `${project.name}\n${project.description}`.toLowerCase().includes(needle))
            .slice(0, limit);
        const largest = Math.max(0, ...matches.map((project) => project.tasks));

        const rows = matches.map((project) => {
            const row = template.content.firstElementChild.cloneNode(true);
            const name = row.querySelector('[data-name]');

            name.textContent = project.name;
            name.title = project.name;
            row.querySelector('[data-bar]').style.width = `${largest > 0 ? Math.round((project.tasks / largest) * 100) : 0}%`;
            row.querySelector('[data-count]').textContent = project.tasks;

            return row;
        });

        list.replaceChildren(...rows);
        list.hidden = rows.length === 0;
        empty.hidden = rows.length > 0;
        clear.hidden = input.value === '';
    };

    const apply = () => {
        store(input.value.trim());
        render(input.value);
    };

    input.addEventListener('input', () => {
        clear.hidden = input.value === '';
        clearTimeout(timer);
        timer = setTimeout(apply, debounceMilliseconds);
    });

    clear.addEventListener('click', () => {
        input.value = '';
        clearTimeout(timer);
        apply();
        input.focus();
    });

    // A realtime refresh reloads the page, so restore the search and apply it to the fresh data.
    try {
        input.value = sessionStorage.getItem(storageKey) ?? '';
    } catch {
        input.value = '';
    }

    if (input.value !== '') {
        render(input.value);
    }
}
