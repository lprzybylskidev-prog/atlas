import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

import translations from '../../../lang/pl.json';
import ChatAttachmentHarness from './ChatAttachmentHarness.vue';

export async function mountChatAttachmentComposer(conversationPublicId: string): Promise<void> {
    const initialPage = {
        component: 'ChatAttachmentHarness',
        url: window.location.pathname,
        version: null,
        props: { locale: 'pl', translations, conversationPublicId, errors: {} },
        clearHistory: false,
        encryptHistory: false,
        rescuedProps: [],
        flash: {},
        rememberedState: {},
    };
    const host = document.createElement('div');
    host.id = 'chat-attachment-e2e-host';
    document.body.replaceChildren(host);

    await createInertiaApp({
        id: host.id,
        page: initialPage,
        resolve: () => ChatAttachmentHarness as unknown as DefineComponent,
        setup({ el, App, props, plugin }) {
            createApp({ render: () => h(App, props) })
                .use(plugin)
                .mount(el);
        },
        progress: false,
    });
}
