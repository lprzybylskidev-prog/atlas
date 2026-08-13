import type { AtlasPageProps } from '../Types/inertia';

export type InertiaFlashMessage = NonNullable<AtlasPageProps['flash']['messages']>[number];

const consumedIds = new Set<string>();
const consumedOrder: string[] = [];
const maximumRememberedMessages = 200;

export function consumeFlashMessages(messages: InertiaFlashMessage[] = []): InertiaFlashMessage[] {
    return messages.filter((message) => {
        const id = message.id;

        if (id === undefined) {
            return true;
        }

        if (consumedIds.has(id)) {
            return false;
        }

        consumedIds.add(id);
        consumedOrder.push(id);

        if (consumedOrder.length > maximumRememberedMessages) {
            const expiredId = consumedOrder.shift();

            if (expiredId !== undefined) {
                consumedIds.delete(expiredId);
            }
        }

        return true;
    });
}

export function resetConsumedFlashMessagesForTests(): void {
    consumedIds.clear();
    consumedOrder.splice(0);
}
