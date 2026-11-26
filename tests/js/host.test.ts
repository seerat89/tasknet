import { InMemoryTransport } from '@modelcontextprotocol/client';
import { App } from '@modelcontextprotocol/ext-apps';
import { AppBridge } from '@modelcontextprotocol/ext-apps/app-bridge';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { startApp } from '../../resources/mcp-app/host';
import { today } from '../../resources/mcp-app/format';
import { click, control, fakeHost, settle, task, text } from './support';

// jsdom has no ResizeObserver, which the SDK uses to report the view's size.
beforeAll(() => {
    globalThis.ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    };
});

let teardown: Array<() => Promise<void>> = [];

afterEach(async () => {
    await Promise.all(teardown.map((close) => close()));
    teardown = [];
});

/** Connect the real SDK App to the official host-side bridge, as a host would. */
async function connect(config: { displayModes?: Array<'inline' | 'fullscreen'>; tasks?: ReturnType<typeof task>[] } = {}) {
    const server = fakeHost({ tasks: config.tasks });
    const host = new AppBridge(
        null,
        { name: 'test-host', version: '1.0.0' },
        { serverTools: {}, updateModelContext: { text: {} } },
        { hostContext: { theme: 'dark', displayMode: 'inline', availableDisplayModes: config.displayModes ?? ['inline'] } },
    );
    const [viewSide, hostSide] = InMemoryTransport.createLinkedPair();
    const context = vi.fn();
    const displayModes = vi.fn();

    host.oncalltool = async ({ name, arguments: args }) => (await server.bridge.callTool(name, args ?? {})) as never;
    host.onupdatemodelcontext = async (params) => {
        context(params);

        return {};
    };
    host.onrequestdisplaymode = async ({ mode }) => {
        displayModes(mode);

        return { mode };
    };

    const initialized = new Promise<void>((resolve) => {
        host.oninitialized = () => resolve();
    });

    await host.connect(hostSide);
    document.body.innerHTML = '<main></main>';

    const app = new App({ name: 'TaskNet', version: '1.0.0' }, { availableDisplayModes: ['inline', 'fullscreen'] }, { autoResize: false });

    await startApp(app, document.querySelector('main')!, viewSide);
    await initialized;
    teardown.push(async () => {
        await app.close();
        await host.close();
    });

    return { host, server, context, displayModes };
}

describe('MCP Apps bridge', () => {
    it('runs a full round trip: open, load through tools, change a task, update the model context', async () => {
        const { host, server, context } = await connect();

        await host.sendToolInput({ arguments: { project_id: 1 } });
        await host.sendToolResult({ content: [], structuredContent: { view: 'tasks', project_id: 1 } });
        await settle();

        expect(text()).toContain('Write the announcement');

        control<HTMLButtonElement>('Complete Write the announcement').click();
        await settle();

        expect(server.calls('update_task')).toEqual([['update_task', { project_id: 1, task_id: 1, status: 'completed' }]]);
        expect(server.tasks[0].status).toBe('completed');
        expect(context).toHaveBeenCalledWith(
            expect.objectContaining({ content: [expect.objectContaining({ text: expect.stringContaining('Changed task #1') })] }),
        );
    });

    it('delivers validation field names from the host to the form', async () => {
        const { host, server } = await connect();

        server.handlers.create_project = () => ({
            content: [{ type: 'text', text: 'Name taken.' }],
            isError: true,
            _meta: { 'tasknet/validationErrors': { name: ['Name taken.'] } },
        });
        await host.sendToolResult({ content: [], structuredContent: { view: 'project_form' } });
        await settle();
        control('Name').value = 'Website';
        click('Create project');
        await settle();

        expect(document.getElementById('tn-name-error')?.textContent).toBe('Name taken.');
    });

    it('handles a host that rejects the tool call', async () => {
        const { host } = await connect();

        host.oncalltool = async () => {
            throw new Error('Unauthorized');
        };
        await host.sendToolResult({ content: [], structuredContent: { view: 'projects' } });
        await settle();

        expect(text()).toContain('TaskNet did not answer this request.');
    });

    it('follows the host theme, including later changes', async () => {
        const { host } = await connect();

        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');

        await host.sendHostContextChange({
            theme: 'light',
            styles: { variables: { '--color-background-primary': '#fefefe' } as never },
        });
        await settle();

        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
        expect(document.documentElement.style.getPropertyValue('--color-background-primary')).toBe('#fefefe');
    });

    it('requests fullscreen only when the host offers it', async () => {
        const many = Array.from({ length: 7 }, (_, index) => task({ id: index + 1, title: `Task ${index + 1}` }));
        const offered = await connect({ displayModes: ['inline', 'fullscreen'], tasks: many });

        await offered.host.sendToolResult({ content: [], structuredContent: { view: 'tasks', project_id: 1 } });
        await settle();
        click('Show all 7 tasks');
        await settle();

        expect(offered.displayModes).toHaveBeenCalledWith('fullscreen');
    });

    it('reports cancellation', async () => {
        const { host } = await connect();

        await host.sendToolCancelled({ reason: 'user' });
        await settle();

        expect(text()).toContain('cancelled');
    });
});

describe('dates', () => {
    it("uses the viewer's time zone for today", () => {
        const instant = new Date('2026-03-01T23:30:00Z');

        expect(today('Europe/Helsinki', instant)).toBe('2026-03-02');
        expect(today('America/New_York', instant)).toBe('2026-03-01');
        expect(today('Not/AZone', instant)).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    });
});
