import { createPainter, h, type Painter } from '../dom';
import { alert, button, header, loading } from '../ui';
import { messageOf, type View, type ViewContext } from './view';

/**
 * A view that fetches data before it can be drawn, with loading and failure states and retry.
 *
 * `render` draws the loaded data and is called again after every local change.
 */
export function loadingView<T>(
    context: ViewContext,
    config: {
        load: () => Promise<T>;
        render: (data: T, paint: Painter) => void;
        /** Where to go when loading fails. */
        fallback?: { label: string; go: () => void };
        loaded?: (data: T) => void;
    },
): View {
    const element = h('section', { class: 'tn-view' });
    const paint = createPainter(element);
    let data: { value: T } | undefined;

    const draw = (): void => {
        if (data) {
            config.render(data.value, paint);
        }
    };

    const load = async (): Promise<void> => {
        paint(loading());

        try {
            const value = await config.load();

            if (context.isCurrent()) {
                data = { value };
                draw();
                config.loaded?.(value);
            }
        } catch (error) {
            if (context.isCurrent()) {
                paint(
                    config.fallback && header('TaskNet', { back: config.fallback }),
                    alert(messageOf(error), button('Try again', () => void load())),
                );
                context.announce(messageOf(error), true);
                context.publish('TaskNet view: this view could not be loaded.');
            }
        }
    };

    void load();

    return { element, rerender: draw };
}
