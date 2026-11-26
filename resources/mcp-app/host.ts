import type { Transport } from '@modelcontextprotocol/client';
import { type App, applyDocumentTheme, applyHostFonts, applyHostStyleVariables, type McpUiHostContext } from '@modelcontextprotocol/ext-apps';
import { createController, type Controller } from './controller';
import type { HostBridge } from './types';

/** The only file that talks to the MCP Apps SDK at runtime; everything else depends on HostBridge. */
export function createHostBridge(app: App): HostBridge {
    return {
        callTool: (name, args) => app.callServerTool({ name, arguments: args }),

        updateContext(text) {
            if (app.getHostCapabilities()?.updateModelContext === undefined) {
                return;
            }

            // Context sharing is a convenience: a host that refuses it must not break the view.
            app.updateModelContext({ content: [{ type: 'text', text }] }).catch(() => {});
        },

        canFullscreen: () => app.getHostContext()?.availableDisplayModes?.includes('fullscreen') ?? false,

        async requestFullscreen() {
            try {
                return (await app.requestDisplayMode({ mode: 'fullscreen' })).mode === 'fullscreen';
            } catch {
                return false;
            }
        },
    };
}

export function applyHostContext(context: McpUiHostContext): void {
    if (context.theme) {
        applyDocumentTheme(context.theme);
    }

    if (context.styles?.variables) {
        applyHostStyleVariables(context.styles.variables);
    }

    if (context.styles?.css?.fonts) {
        applyHostFonts(context.styles.css.fonts);
    }

    if (context.safeAreaInsets) {
        const { top, right, bottom, left } = context.safeAreaInsets;

        document.body.style.padding = `${top}px ${right}px ${bottom}px ${left}px`;
    }
}

export async function startApp(app: App, root: HTMLElement, transport?: Transport): Promise<Controller> {
    const controller = createController(root, createHostBridge(app));
    const syncContext = (): void => {
        const context = app.getHostContext() ?? {};

        applyHostContext(context);
        controller.setHostContext(context);
    };

    // Listeners must exist before connect(), or the host's first notifications can be missed.
    app.addEventListener('toolresult', (result) => controller.handleToolResult(result));
    app.addEventListener('toolcancelled', () => controller.handleCancelled());
    app.addEventListener('hostcontextchanged', syncContext);
    app.onteardown = async () => ({});

    try {
        await app.connect(transport);
        syncContext();
    } catch {
        controller.handleConnectionFailure();
    }

    return controller;
}
