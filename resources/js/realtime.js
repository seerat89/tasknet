import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const channelName = document.querySelector('meta[name="workspace-channel"]')?.content;
const appKey = import.meta.env.VITE_REVERB_APP_KEY;

// Pages opt in with a workspace channel; forms and other pages never reload under the user.
if (channelName && appKey) {
    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: appKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    const debounceMilliseconds = 400;
    let timer = null;

    const refreshPage = () => {
        // Wait while the tab is in the background or a confirmation dialog is open.
        if (document.hidden || document.querySelector('dialog[open]')) {
            schedule(1000);

            return;
        }

        window.location.reload();
    };

    const schedule = (delay) => {
        clearTimeout(timer);
        timer = setTimeout(refreshPage, delay);
    };

    window.Echo.private(channelName)
        .listen('.project.changed', () => schedule(debounceMilliseconds))
        .listen('.task.changed', () => schedule(debounceMilliseconds));
}
