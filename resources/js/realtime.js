import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const channelName = document.querySelector('meta[name="workspace-channel"]')?.content;

// The server describes where Reverb is only when broadcasting is on, so with realtime off the page never tries to connect.
// It is read at render time rather than from VITE_* build variables, which are fixed when the assets are built.
let connection = null;

try {
    connection = JSON.parse(document.querySelector('meta[name="realtime-connection"]')?.content ?? 'null');
} catch {
    connection = null;
}

// Pages opt in with a workspace channel; forms and other pages never reload under the user.
if (channelName && connection?.key) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: connection.key,
        wsHost: connection.host,
        wsPort: connection.port,
        wssPort: connection.port,
        forceTLS: connection.scheme === 'https',
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
