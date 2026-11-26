import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import { viteSingleFile } from 'vite-plugin-singlefile';

const path = (relative: string): string => fileURLToPath(new URL(relative, import.meta.url));

/**
 * The MCP App served to AI clients as a ui:// resource: one HTML file with its script and styles inlined,
 * because the host's sandbox loads nothing from TaskNet. It is built outside public/ on purpose: the page is
 * only served through MCP, never as a web page on TaskNet's origin.
 */
export default defineConfig({
    root: path('./resources/mcp-app'),
    publicDir: false,
    plugins: [tailwindcss(), viteSingleFile()],
    build: {
        outDir: path('./resources/dist/mcp-app'),
        emptyOutDir: true,
        // The page has no separate modules to preload, and the polyfill would add a fetch() call.
        modulePreload: { polyfill: false },
        rolldownOptions: {
            input: path('./resources/mcp-app/app.html'),
        },
    },
});
