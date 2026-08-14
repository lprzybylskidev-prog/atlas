import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { chatJson } from './chatAttachments';

export interface ChatRealtimeMessage {
    publicId: string;
    authorPublicId: string;
    body: string | null;
    renderedHtml: string | null;
    createdAt: string;
}

export interface ChatPresence {
    userPublicId: string;
    name: string;
    online: boolean;
    lastSeenAt: string | null;
    manualStatus: 'available' | 'busy' | 'do_not_disturb' | 'out_of_office';
    customText: string | null;
    customEmoji: string | null;
}

export interface ChatCursor {
    userPublicId: string;
    lastDeliveredMessagePublicId: string | null;
    lastReadMessagePublicId: string | null;
}

export interface ChatConversationState {
    lastDeliveredMessagePublicId: string | null;
    lastReadMessagePublicId: string | null;
    firstUnreadMessagePublicId: string | null;
    unreadCount: number;
}

export interface ChatReconciliation {
    messages: ChatRealtimeMessage[];
    state: ChatConversationState;
    participantCursors: ChatCursor[];
    presence: ChatPresence[];
    totalUnread: number;
}

export interface ChatRealtimeCallbacks {
    reconciled: (snapshot: ChatReconciliation) => void;
    message: (message: ChatRealtimeMessage) => void;
    state: (state: Record<string, unknown>) => void;
    presence: (members: ChatPresence[]) => void;
    typing: (userPublicId: string, typing: boolean) => void;
    connected?: () => void;
    disconnected?: () => void;
    error?: (error: unknown) => void;
}

interface MessageEvent {
    conversationPublicId: string;
    message: ChatRealtimeMessage;
}

interface TypingEvent {
    userPublicId: string;
    typing: boolean;
}

interface PresenceMember {
    id: string;
    name: string;
    manualStatus: ChatPresence['manualStatus'];
    customText: string | null;
    customEmoji: string | null;
}

interface ReverbConnection {
    state: string;
    bind(event: string, callback: (value?: unknown) => void): void;
    unbind(event: string, callback: (value?: unknown) => void): void;
}

interface ReverbConnector {
    pusher: { connection: ReverbConnection };
}

const typingExpiryMs = 5_000;
const heartbeatIntervalMs = 30_000;

function realtimeMeta(name: string, fallback: string | undefined): string | undefined {
    return document.querySelector<HTMLMetaElement>(`meta[name="atlas-reverb-${name}"]`)?.content || fallback;
}

export function mergeChatMessages(current: ChatRealtimeMessage[], incoming: ChatRealtimeMessage[]): ChatRealtimeMessage[] {
    const messages = new Map(current.map((message) => [message.publicId, message]));
    for (const message of incoming) messages.set(message.publicId, message);
    return [...messages.values()].sort((left, right) => left.createdAt.localeCompare(right.createdAt));
}

export class ChatRealtimeClient {
    private readonly echo: Echo<'reverb'>;
    private readonly typingTimers = new Map<string, number>();
    private readonly presenceMembers = new Map<string, ChatPresence>();
    private lastMessagePublicId: string | null = null;
    private heartbeatTimer: number | null = null;
    private stopped = false;

    constructor(
        private readonly conversationPublicId: string,
        private readonly currentUserPublicId: string,
        private readonly callbacks: ChatRealtimeCallbacks,
    ) {
        window.Pusher = Pusher;
        const scheme = realtimeMeta('scheme', import.meta.env.VITE_REVERB_SCHEME) ?? 'https';
        this.echo = new Echo({
            broadcaster: 'reverb',
            key: realtimeMeta('key', import.meta.env.VITE_REVERB_APP_KEY) ?? '',
            wsHost: realtimeMeta('host', import.meta.env.VITE_REVERB_HOST) ?? window.location.hostname,
            wsPort: Number(realtimeMeta('port', import.meta.env.VITE_REVERB_PORT) ?? 80),
            wssPort: Number(realtimeMeta('port', import.meta.env.VITE_REVERB_PORT) ?? 443),
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    start(): void {
        const connection = (this.echo.connector as unknown as ReverbConnector).pusher.connection;
        connection.bind('connected', this.handleConnected);
        connection.bind('disconnected', this.handleDisconnected);
        connection.bind('error', this.handleError);
        if (connection.state === 'connected') this.handleConnected();
        this.echo
            .private(`chat.user.${this.currentUserPublicId}`)
            .listen('.chat.unread.updated', (event: Record<string, unknown>) => this.callbacks.state(event))
            .listen('.chat.presence.updated', () => this.run(this.reconcile()));
        this.echo
            .join(`chat.conversation.${this.conversationPublicId}`)
            .here((members: PresenceMember[]) => this.membersHere(members))
            .joining((member: PresenceMember) => this.memberJoined(member))
            .leaving((member: PresenceMember) => this.memberLeft(member))
            .listen('.chat.message.created', (event: MessageEvent) => {
                this.lastMessagePublicId = event.message.publicId;
                this.callbacks.message(event.message);
                this.run(this.markDelivered(event.message.publicId));
            })
            .listen('.chat.state.updated', (event: Record<string, unknown>) => this.callbacks.state(event))
            .listen('.chat.presence.updated', (presence: ChatPresence) => this.updatePresence(presence))
            .listenForWhisper('typing', (event: TypingEvent) => this.handleTyping(event));
        this.run(this.heartbeat());
        this.heartbeatTimer = window.setInterval(() => this.run(this.heartbeat()), heartbeatIntervalMs);
    }

    stop(): void {
        this.stopped = true;
        const connection = (this.echo.connector as unknown as ReverbConnector).pusher.connection;
        connection.unbind('connected', this.handleConnected);
        connection.unbind('disconnected', this.handleDisconnected);
        connection.unbind('error', this.handleError);
        this.echo.leave(`chat.conversation.${this.conversationPublicId}`);
        this.echo.leave(`chat.user.${this.currentUserPublicId}`);
        this.echo.disconnect();
        if (this.heartbeatTimer !== null) window.clearInterval(this.heartbeatTimer);
        this.heartbeatTimer = null;
        for (const timer of this.typingTimers.values()) window.clearTimeout(timer);
        this.typingTimers.clear();
    }

    whisperTyping(typing: boolean): void {
        this.echo.join(`chat.conversation.${this.conversationPublicId}`).whisper('typing', {
            userPublicId: this.currentUserPublicId,
            typing,
        });
    }

    async reconcile(): Promise<void> {
        if (this.stopped) return;
        const query = this.lastMessagePublicId === null ? '' : `?after_message_public_id=${encodeURIComponent(this.lastMessagePublicId)}`;
        const snapshot = await chatJson<ChatReconciliation>(`/chat/conversations/${this.conversationPublicId}/realtime${query}`);
        const last = snapshot.messages.at(-1);
        if (last !== undefined) {
            this.lastMessagePublicId = last.publicId;
            this.run(this.markDelivered(last.publicId));
        }
        this.presenceMembers.clear();
        for (const presence of snapshot.presence) this.presenceMembers.set(presence.userPublicId, presence);
        this.callbacks.reconciled(snapshot);
        this.publishPresence();
    }

    heartbeat(): Promise<ChatPresence> {
        return chatJson<ChatPresence>('/chat/realtime/heartbeat', 'POST');
    }

    markDelivered(messagePublicId: string): Promise<{ ok: true }> {
        return chatJson(`/chat/conversations/${this.conversationPublicId}/messages/${messagePublicId}/delivered`, 'POST');
    }

    markRead(messagePublicId: string): Promise<{ ok: true }> {
        return chatJson(`/chat/conversations/${this.conversationPublicId}/messages/${messagePublicId}/read`, 'POST');
    }

    markUnread(messagePublicId: string): Promise<{ ok: true }> {
        return chatJson(`/chat/conversations/${this.conversationPublicId}/messages/${messagePublicId}/unread`, 'POST');
    }

    updateStatus(
        status: ChatPresence['manualStatus'],
        customText: string | null = null,
        customEmoji: string | null = null,
    ): Promise<ChatPresence> {
        return chatJson<ChatPresence>('/chat/realtime/status', 'PATCH', {
            status,
            custom_text: customText,
            custom_emoji: customEmoji,
        });
    }

    connectionState(): string {
        return (this.echo.connector as unknown as ReverbConnector).pusher.connection.state;
    }

    private readonly handleConnected = (): void => {
        this.callbacks.connected?.();
        this.run(this.reconcile());
        this.run(this.heartbeat());
    };

    private readonly handleDisconnected = (): void => this.callbacks.disconnected?.();

    private readonly handleError = (error?: unknown): void => this.callbacks.error?.(error);

    private membersHere(members: PresenceMember[]): void {
        for (const member of members) this.memberJoined(member, false);
        this.publishPresence();
        this.run(this.reconcile());
    }

    private memberJoined(member: PresenceMember, publish = true): void {
        const existing = this.presenceMembers.get(member.id);
        this.presenceMembers.set(member.id, {
            userPublicId: member.id,
            name: member.name,
            online: true,
            lastSeenAt: existing?.lastSeenAt ?? null,
            manualStatus: member.manualStatus,
            customText: member.customText,
            customEmoji: member.customEmoji,
        });
        if (publish) this.publishPresence();
    }

    private memberLeft(member: PresenceMember): void {
        const existing = this.presenceMembers.get(member.id);
        if (existing !== undefined) {
            this.presenceMembers.set(member.id, { ...existing, online: false, lastSeenAt: new Date().toISOString() });
            this.publishPresence();
        }
    }

    private updatePresence(presence: ChatPresence): void {
        this.presenceMembers.set(presence.userPublicId, presence);
        this.publishPresence();
    }

    private publishPresence(): void {
        this.callbacks.presence([...this.presenceMembers.values()]);
    }

    private run(operation: Promise<unknown>): void {
        void operation.catch((error: unknown) => this.callbacks.error?.(error));
    }

    private handleTyping(event: TypingEvent): void {
        if (event.userPublicId === this.currentUserPublicId) return;
        const existing = this.typingTimers.get(event.userPublicId);
        if (existing !== undefined) window.clearTimeout(existing);
        this.callbacks.typing(event.userPublicId, event.typing);
        if (!event.typing) return;
        this.typingTimers.set(
            event.userPublicId,
            window.setTimeout(() => {
                this.typingTimers.delete(event.userPublicId);
                this.callbacks.typing(event.userPublicId, false);
            }, typingExpiryMs),
        );
    }
}

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}
