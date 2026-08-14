import { ChatRealtimeClient, mergeChatMessages, type ChatPresence, type ChatRealtimeMessage } from '../Services/chatRealtime';

interface HarnessState {
    connected: boolean;
    messages: ChatRealtimeMessage[];
    presence: ChatPresence[];
    typingUserPublicIds: string[];
    stateEvents: Record<string, unknown>[];
    reconciliations: number;
    connectionState: string;
    connectionError: string | null;
}

interface HarnessWindow extends Window {
    __chatRealtime?: {
        state: HarnessState;
        client: ChatRealtimeClient;
    };
}

export function mountChatRealtime(conversationPublicId: string, currentUserPublicId: string): void {
    const state: HarnessState = {
        connected: false,
        messages: [],
        presence: [],
        typingUserPublicIds: [],
        stateEvents: [],
        reconciliations: 0,
        connectionState: 'initializing',
        connectionError: null,
    };
    const client = new ChatRealtimeClient(conversationPublicId, currentUserPublicId, {
        reconciled(snapshot) {
            state.messages = mergeChatMessages(state.messages, snapshot.messages);
            state.reconciliations += 1;
        },
        message(message) {
            state.messages = mergeChatMessages(state.messages, [message]);
        },
        state(event) {
            state.stateEvents.push(event);
        },
        presence(members) {
            state.presence = members;
        },
        typing(userPublicId, typing) {
            state.typingUserPublicIds = typing
                ? [...new Set([...state.typingUserPublicIds, userPublicId])]
                : state.typingUserPublicIds.filter((id) => id !== userPublicId);
        },
        connected() {
            state.connected = true;
            state.connectionState = 'connected';
        },
        disconnected() {
            state.connected = false;
            state.connectionState = client.connectionState();
        },
        error(error) {
            state.connectionError = JSON.stringify(error);
        },
    });
    (window as HarnessWindow).__chatRealtime = { state, client };
    client.start();
    state.connectionState = client.connectionState();
}
