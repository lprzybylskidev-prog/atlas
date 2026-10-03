<script setup lang="ts">
import {
    IconBookmark,
    IconChevronDown,
    IconDownload,
    IconEdit,
    IconHistory,
    IconMessageCircle,
    IconPinned,
    IconSearch,
    IconSend,
    IconStar,
    IconStarFilled,
    IconTrash,
    IconUsersPlus,
    IconX,
} from '@tabler/icons-vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

import FormButton from '../Form/FormButton.vue';
import FormDateInput from '../Form/FormDateInput.vue';
import FormInput from '../Form/FormInput.vue';
import FormSelect from '../Form/FormSelect.vue';
import FormTextarea from '../Form/FormTextarea.vue';
import CheckboxList from '../CheckboxList.vue';
import IconButton from '../IconButton.vue';
import UiState from '../UiState.vue';
import { useTranslator } from '../../Localization/translator';
import { chatJson } from '../../Services/chatAttachments';
import { browserNotificationsEnabled, setBrowserNotificationsEnabled } from '../../Services/browserNotificationPreferences';
import { ChatRealtimeClient, mergeChatMessages } from '../../Services/chatRealtime';
import type { ChatRealtimeMessage } from '../../Services/chatRealtime';
import type { AtlasPageProps } from '../../Types/inertia';

type ConversationType = 'direct' | 'group' | 'team' | 'meeting';
interface ConversationSummary {
    publicId: string;
    type: ConversationType;
    name: string;
    favorite: boolean;
    unreadCount: number;
}
interface SearchItem {
    type: 'user' | 'conversation' | 'message' | 'file' | 'link' | 'transcript';
    publicId: string;
    title: string;
    snippet: string;
    conversationPublicId: string | null;
    messagePublicId: string | null;
    transcriptionPublicId: string | null;
    occurredAt: string | null;
    authorName: string | null;
}
interface UserOption {
    publicId: string;
    name: string;
}
interface GroupDetails {
    publicId: string;
    name: string;
    isOwner: boolean;
    members: (UserOption & { role: 'owner' | 'member' })[];
}
interface MessageRevision {
    version: number;
    body: string;
    createdAt: string;
}

const page = usePage<AtlasPageProps>();
const { t } = useTranslator();
const conversations = ref<ConversationSummary[]>([]);
const messages = ref<ChatRealtimeMessage[]>([]);
const open = ref(false);
const unreadOpen = ref(false);
const activeId = ref<string | null>(null);
const filter = ref<'all' | 'unread' | ConversationType>('all');
const draft = ref('');
const loading = ref(false);
const sending = ref(false);
const loadingOlder = ref(false);
const hasOlder = ref(false);
const error = ref(false);
const searchOpen = ref(false);
const searchTerm = ref('');
const searchAuthor = ref('');
const searchConversation = ref('');
const searchDateFrom = ref('');
const searchDateTo = ref('');
const searchType = ref('');
const searchResults = ref<SearchItem[]>([]);
const searchLoading = ref(false);
const searchError = ref(false);
const exportFormat = ref<'csv' | 'json' | 'pdf'>('pdf');
const nativeEnabled = ref(false);
const groupComposerOpen = ref(false);
const groupName = ref('');
const groupMemberIds = ref<string[]>([]);
const groupCandidates = ref<UserOption[]>([]);
const groupDetails = ref<GroupDetails | null>(null);
const groupMemberSelection = ref('');
const replyTarget = ref<ChatRealtimeMessage | null>(null);
const editingMessage = ref<ChatRealtimeMessage | null>(null);
const revisions = ref<Record<string, MessageRevision[]>>({});
const forwardDestinations = ref<Record<string, string>>({});
const shell = ref<HTMLElement | null>(null);
const launcher = ref<HTMLElement | null>(null);
let client: ChatRealtimeClient | null = null;
let refreshTimer: number | null = null;
let draftTimer: number | null = null;
let stopInertiaStartListener: (() => void) | null = null;
let stopInertiaFinishListener: (() => void) | null = null;
let restoringDraft = false;
let disposed = false;
let inertiaVisitInProgress = false;

const active = computed(() => conversations.value.find((item) => item.publicId === activeId.value) ?? null);
const canSearch = computed(() => page.props.auth.availableApplicationRoutes.includes('chat.search.index'));
const canStartDirect = computed(() => page.props.auth.availableApplicationRoutes.includes('chat.direct-conversations.store'));
const canCreateGroup = computed(() => page.props.auth.availableApplicationRoutes.includes('chat.groups.store'));
const canExport = computed(() => page.props.auth.availableApplicationRoutes.includes('chat.exports.store'));
const totalUnread = computed(() => conversations.value.reduce((total, item) => total + item.unreadCount, 0));
const unreadConversations = computed(() => conversations.value.filter((item) => item.unreadCount > 0));
const filtered = computed(() => {
    const items =
        filter.value === 'all'
            ? conversations.value
            : filter.value === 'unread'
              ? unreadConversations.value
              : conversations.value.filter((item) => item.type === filter.value);
    return [...items].sort((left, right) => Number(right.favorite) - Number(left.favorite));
});
const filters: { value: typeof filter.value; label: string }[] = [
    { value: 'all', label: t('chat.shell.filters.all') },
    { value: 'unread', label: t('chat.shell.filters.unread') },
    { value: 'direct', label: t('chat.shell.filters.direct') },
    { value: 'group', label: t('chat.shell.filters.groups') },
    { value: 'team', label: t('chat.shell.filters.team') },
    { value: 'meeting', label: t('chat.shell.filters.meeting') },
];
const searchTypes = computed(() => [
    { value: '', label: t('chat.search.types.all') },
    ...(['user', 'conversation', 'message', 'file', 'link', 'transcript'] as const).map((value) => ({
        value,
        label: t(`chat.search.types.${value}`),
    })),
]);
const searchConversations = computed(() => [
    { value: '', label: t('chat.search.conversations_all') },
    ...conversations.value.map((conversation) => ({ value: conversation.publicId, label: conversation.name })),
]);
const exportFormats = [
    { value: 'pdf', label: 'PDF' },
    { value: 'csv', label: 'CSV' },
    { value: 'json', label: 'JSON' },
];
const groupCandidateOptions = computed(() => [
    { value: '', label: t('chat.groups.choose_member') },
    ...groupCandidates.value.map((user) => ({ value: user.publicId, label: user.name })),
]);
const groupCandidateCheckboxOptions = computed(() => groupCandidates.value.map((user) => ({ value: user.publicId, label: user.name })));
const forwardingOptions = computed(() => [
    { value: '', label: t('chat.messages.forward_choose') },
    ...conversations.value.map((conversation) => ({ value: conversation.publicId, label: conversation.name })),
]);

async function loadConversations(): Promise<void> {
    loading.value = true;
    error.value = false;
    try {
        const response = await chatJson<{ conversations: ConversationSummary[] }>('/chat/conversations');
        if (disposed) return;
        const previous = totalUnread.value;
        conversations.value = response.conversations;
        if (
            page.props.chat.browserNotificationsEnabled &&
            nativeEnabled.value &&
            totalUnread.value > previous &&
            Notification.permission === 'granted'
        ) {
            new Notification(t('chat.shell.new_message'), { body: t('chat.shell.new_message_body'), tag: 'atlas-chat' });
        }
    } catch {
        if (!disposed) error.value = true;
    } finally {
        if (!disposed) loading.value = false;
    }
}

async function runSearch(): Promise<void> {
    if (searchTerm.value.trim().length < 2 || searchLoading.value) return;
    searchLoading.value = true;
    searchError.value = false;
    const params = new URLSearchParams({ q: searchTerm.value.trim() });
    if (searchAuthor.value) params.set('author', searchAuthor.value);
    if (searchConversation.value) params.set('conversation', searchConversation.value);
    if (searchDateFrom.value) params.set('date_from', searchDateFrom.value);
    if (searchDateTo.value) params.set('date_to', searchDateTo.value);
    if (searchType.value) params.set('type', searchType.value);
    try {
        const response = await chatJson<{ items: SearchItem[] }>(`/chat/search?${params.toString()}`);
        searchResults.value = response.items;
    } catch {
        searchError.value = true;
        searchResults.value = [];
    } finally {
        searchLoading.value = false;
    }
}

async function openSearchResult(item: SearchItem): Promise<void> {
    if (item.type === 'user') {
        if (!canStartDirect.value) return;
        const conversation = await chatJson<{ publicId: string; type: ConversationType }>('/chat/direct-conversations', 'POST', {
            target_user_public_id: item.publicId,
        });
        await loadConversations();
        const summary = conversations.value.find((candidate) => candidate.publicId === conversation.publicId);
        if (summary) await show(summary);
        return;
    }
    if (item.conversationPublicId === null) return;
    const summary = conversations.value.find((candidate) => candidate.publicId === item.conversationPublicId);
    if (summary) await show(summary);
}

function searchResultActionable(item: SearchItem): boolean {
    return item.type === 'user' ? canStartDirect.value : item.conversationPublicId !== null;
}

async function enableNativeNotifications(): Promise<void> {
    if (!('Notification' in window)) return;
    const permission = await Notification.requestPermission();
    nativeEnabled.value = permission === 'granted';
    setBrowserNotificationsEnabled('chat', page.props.auth.user?.publicId, nativeEnabled.value);
}

function disableNativeNotifications(): void {
    nativeEnabled.value = false;
    setBrowserNotificationsEnabled('chat', page.props.auth.user?.publicId, false);
}

async function show(conversation: ConversationSummary): Promise<void> {
    open.value = true;
    unreadOpen.value = false;
    activeId.value = conversation.publicId;
    messages.value = [];
    hasOlder.value = false;
    replyTarget.value = null;
    editingMessage.value = null;
    groupDetails.value = null;
    restoringDraft = true;
    const saved = await chatJson<{ draft: { body: string; replyToMessagePublicId: string | null } | null }>(
        `/chat/conversations/${conversation.publicId}/draft`,
    );
    draft.value = saved.draft?.body ?? '';
    restoringDraft = false;
    if (conversation.type === 'group') {
        const response = await chatJson<{ group: GroupDetails }>(`/chat/conversations/${conversation.publicId}/group`);
        groupDetails.value = response.group;
        if (response.group.isOwner && groupCandidates.value.length === 0) {
            const candidates = await chatJson<{ users: UserOption[] }>('/chat/group-candidates');
            groupCandidates.value = candidates.users;
        }
    }
    await nextTick();
}

async function openGroupComposer(): Promise<void> {
    groupComposerOpen.value = true;
    if (groupCandidates.value.length === 0) {
        const response = await chatJson<{ users: UserOption[] }>('/chat/group-candidates');
        groupCandidates.value = response.users;
    }
}

async function createGroup(): Promise<void> {
    const created = await chatJson<{ publicId: string }>('/chat/groups', 'POST', {
        name: groupName.value,
        member_public_ids: groupMemberIds.value,
    });
    groupName.value = '';
    groupMemberIds.value = [];
    groupComposerOpen.value = false;
    await loadConversations();
    const conversation = conversations.value.find((item) => item.publicId === created.publicId);
    if (conversation) await show(conversation);
}

async function updateGroup(action: string, values: Record<string, unknown> = {}): Promise<void> {
    if (active.value === null) return;
    await chatJson(`/chat/conversations/${active.value.publicId}/group`, 'PATCH', { action, ...values });
    await loadConversations();
    if (action === 'leave') {
        activeId.value = null;
        return;
    }
    const response = await chatJson<{ group: GroupDetails }>(`/chat/conversations/${active.value.publicId}/group`);
    groupDetails.value = response.group;
}

async function loadOlder(): Promise<void> {
    const oldest = messages.value[0];
    if (client === null || oldest === undefined || loadingOlder.value || !hasOlder.value) return;
    loadingOlder.value = true;
    try {
        const snapshot = await client.loadOlder(oldest.publicId);
        messages.value = mergeChatMessages(messages.value, snapshot.messages);
        hasOlder.value = snapshot.hasOlder;
    } finally {
        loadingOlder.value = false;
    }
}

function close(): void {
    open.value = false;
    activeId.value = null;
    client?.stop();
    client = null;
    nextTick(() => launcher.value?.focus());
}

function toggleSearch(): void {
    searchOpen.value = !searchOpen.value;
    if (searchOpen.value) activeId.value = null;
}

async function toggleFavorite(conversation: ConversationSummary): Promise<void> {
    const favorite = !conversation.favorite;
    await chatJson(`/chat/conversations/${conversation.publicId}/favorite`, 'PATCH', { favorite });
    conversation.favorite = favorite;
}

function exportConversation(): void {
    if (!active.value || !canExport.value) return;
    router.post(`/chat/conversations/${active.value.publicId}/exports`, { format: exportFormat.value }, { preserveScroll: true });
}

async function send(): Promise<void> {
    if (active.value === null || draft.value.trim() === '' || sending.value) return;
    sending.value = true;
    try {
        const message = editingMessage.value
            ? await chatJson<ChatRealtimeMessage>(
                  `/chat/conversations/${active.value.publicId}/messages/${editingMessage.value.publicId}`,
                  'PATCH',
                  { action: 'edit', body: draft.value, expected_version: editingMessage.value.version },
              )
            : await chatJson<ChatRealtimeMessage>(`/chat/conversations/${active.value.publicId}/messages`, 'POST', {
                  body: draft.value,
                  client_message_key: crypto.randomUUID(),
                  reply_to_message_public_id: replyTarget.value?.publicId ?? null,
              });
        messages.value = mergeChatMessages(messages.value, [message]);
        draft.value = '';
        replyTarget.value = null;
        editingMessage.value = null;
        await markRead(message);
    } finally {
        sending.value = false;
    }
}

async function messageAction(message: ChatRealtimeMessage, action: string, values: Record<string, unknown> = {}): Promise<void> {
    if (active.value === null) return;
    const updated = await chatJson<ChatRealtimeMessage>(
        `/chat/conversations/${active.value.publicId}/messages/${message.publicId}`,
        'PATCH',
        { action, ...values },
    );
    if (action === 'forward') return;
    messages.value = mergeChatMessages(messages.value, [updated]);
}

function beginEdit(message: ChatRealtimeMessage): void {
    editingMessage.value = message;
    replyTarget.value = null;
    draft.value = message.body ?? '';
}

async function showHistory(message: ChatRealtimeMessage): Promise<void> {
    if (active.value === null) return;
    const response = await chatJson<{ revisions: MessageRevision[] }>(
        `/chat/conversations/${active.value.publicId}/messages/${message.publicId}/history`,
    );
    revisions.value = { ...revisions.value, [message.publicId]: response.revisions };
}

async function forwardMessage(message: ChatRealtimeMessage): Promise<void> {
    const destination = forwardDestinations.value[message.publicId];
    if (!destination) return;
    await messageAction(message, 'forward', {
        destination_conversation_public_id: destination,
        client_message_key: crypto.randomUUID(),
    });
}

async function markRead(message: ChatRealtimeMessage): Promise<void> {
    if (active.value === null) return;
    await client?.markRead(message.publicId);
    active.value.unreadCount = 0;
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        if (unreadOpen.value) unreadOpen.value = false;
        else if (open.value) close();
        return;
    }
    if (!open.value || event.key !== 'Tab' || shell.value === null) return;
    const controls = shell.value.querySelectorAll<HTMLElement>(
        'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])',
    );
    if (controls.length === 0) return;
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    }
    if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(activeId, (id) => {
    client?.stop();
    client = null;
    if (disposed || id === null || page.props.auth.user === null) return;
    client = new ChatRealtimeClient(id, page.props.auth.user.publicId, {
        reconciled: (snapshot) => {
            if (disposed) return;
            messages.value = mergeChatMessages(messages.value, snapshot.messages);
            if (messages.value.length === snapshot.messages.length || snapshot.hasOlder) hasOlder.value = snapshot.hasOlder;
            if (snapshot.messages.length > 0) void markRead(snapshot.messages.at(-1)!);
        },
        message: (message) => {
            if (disposed) return;
            messages.value = mergeChatMessages(messages.value, [message]);
            if (open.value) void markRead(message);
        },
        state: (state) => {
            if (disposed) return;
            const total = state.unreadCount;
            if (typeof total === 'number' && active.value !== null) active.value.unreadCount = total;
        },
        presence: () => undefined,
        typing: () => undefined,
    });
    client.start();
});

watch(draft, (body) => {
    if (restoringDraft || active.value === null || editingMessage.value !== null) return;
    if (draftTimer !== null) window.clearTimeout(draftTimer);
    draftTimer = window.setTimeout(() => {
        if (!disposed && active.value !== null) {
            void chatJson(`/chat/conversations/${active.value.publicId}/draft`, 'PUT', {
                body,
                reply_to_message_public_id: replyTarget.value?.publicId ?? null,
            });
        }
    }, 500);
});

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
    stopInertiaStartListener = router.on('start', () => {
        inertiaVisitInProgress = true;
    });
    stopInertiaFinishListener = router.on('finish', () => {
        inertiaVisitInProgress = false;
    });
    nativeEnabled.value = browserNotificationsEnabled('chat', page.props.auth.user?.publicId);
    void loadConversations();
    refreshTimer = window.setInterval(() => {
        if (!inertiaVisitInProgress && document.visibilityState === 'visible') void loadConversations();
    }, 30_000);
});
onBeforeUnmount(() => {
    disposed = true;
    document.removeEventListener('keydown', handleKeydown);
    client?.stop();
    stopInertiaStartListener?.();
    stopInertiaFinishListener?.();
    if (refreshTimer !== null) window.clearInterval(refreshTimer);
    if (draftTimer !== null) window.clearTimeout(draftTimer);
});
</script>

<template>
    <div class="relative flex items-center">
        <button
            ref="launcher"
            type="button"
            class="relative inline-flex h-10 items-center gap-2 rounded-l-lg border border-zinc-200 px-3 text-sm font-medium text-zinc-700 hover:bg-teal-50 hover:text-teal-800 focus-visible:outline-2 focus-visible:outline-amber-500 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-teal-950"
            :aria-label="t('chat.shell.open')"
            @click="open = true"
        >
            <IconMessageCircle v-once aria-hidden="true" class="h-5 w-5" />
            <span class="hidden xl:inline">{{ t('chat.shell.title') }}</span>
            <span v-if="totalUnread" class="min-w-5 rounded-full bg-rose-600 px-1.5 text-center text-xs leading-5 text-white">{{
                Math.min(totalUnread, 99)
            }}</span>
        </button>
        <button
            type="button"
            class="inline-flex h-10 w-9 items-center justify-center rounded-r-lg border border-l-0 border-zinc-200 text-zinc-600 hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
            :aria-label="t('chat.shell.unread_menu')"
            :aria-expanded="unreadOpen"
            aria-haspopup="menu"
            @click="unreadOpen = !unreadOpen"
        >
            <IconChevronDown v-once aria-hidden="true" class="h-4 w-4" />
        </button>
        <div
            v-if="unreadOpen"
            class="absolute right-0 top-12 z-60 w-80 rounded-lg border border-zinc-200 bg-white p-2 shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
            role="menu"
        >
            <p class="px-2 py-1 text-xs font-semibold uppercase text-zinc-500">{{ t('chat.shell.unread') }}</p>
            <button
                v-for="conversation in unreadConversations"
                :key="conversation.publicId"
                type="button"
                class="flex min-h-11 w-full items-center justify-between rounded-md px-2 text-left hover:bg-teal-50 dark:hover:bg-teal-950"
                role="menuitem"
                @click="show(conversation)"
            >
                <span class="truncate">{{ conversation.name }}</span>
                <span class="ml-2 rounded-full bg-rose-600 px-2 text-xs text-white">{{ conversation.unreadCount }}</span>
            </button>
            <p v-if="unreadConversations.length === 0" class="px-2 py-3 text-sm text-zinc-500">{{ t('chat.shell.unread_empty') }}</p>
            <button
                v-if="page.props.chat.browserNotificationsEnabled && !nativeEnabled"
                type="button"
                class="mt-2 min-h-10 w-full rounded-md border border-zinc-200 px-2 text-sm text-teal-700 dark:border-zinc-700 dark:text-teal-300"
                @click="enableNativeNotifications"
            >
                {{ t('chat.shell.enable_browser') }}
            </button>
            <button
                v-if="page.props.chat.browserNotificationsEnabled && nativeEnabled"
                type="button"
                class="mt-2 min-h-10 w-full rounded-md border border-zinc-200 px-2 text-sm text-zinc-700 dark:border-zinc-700 dark:text-zinc-200"
                @click="disableNativeNotifications"
            >
                {{ t('chat.shell.disable_browser') }}
            </button>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-80 flex bg-zinc-950/60 md:items-center md:justify-center md:p-4">
            <section
                ref="shell"
                role="dialog"
                aria-modal="true"
                :aria-label="t('chat.shell.title')"
                tabindex="-1"
                class="flex h-full w-full overflow-hidden bg-white outline-none dark:bg-zinc-950 md:h-[min(48rem,calc(100vh-2rem))] md:max-w-6xl md:rounded-xl md:border md:border-zinc-200 md:shadow-2xl dark:md:border-zinc-800"
            >
                <aside
                    class="flex w-full flex-col border-r border-zinc-200 dark:border-zinc-800 md:w-80"
                    :class="active ? 'hidden md:flex' : 'flex'"
                >
                    <div class="flex items-center justify-between border-b border-zinc-200 p-3 dark:border-zinc-800">
                        <h2 class="font-semibold">{{ t('chat.shell.title') }}</h2>
                        <div class="flex items-center gap-1">
                            <IconButton
                                v-if="canCreateGroup"
                                :label="t('chat.groups.create')"
                                :icon="IconUsersPlus"
                                @click="openGroupComposer"
                            />
                            <IconButton v-if="canSearch" :label="t('chat.search.open')" :icon="IconSearch" @click="toggleSearch" />
                            <IconButton :label="t('actions.close')" :icon="IconX" @click="close" />
                        </div>
                    </div>
                    <form
                        v-if="groupComposerOpen"
                        class="space-y-3 border-b border-zinc-200 p-3 dark:border-zinc-800"
                        @submit.prevent="createGroup"
                    >
                        <FormInput v-model="groupName" :label="t('chat.groups.name')" />
                        <CheckboxList
                            v-model="groupMemberIds"
                            :label="t('chat.groups.members')"
                            :options="groupCandidateCheckboxOptions"
                            max-height="max-h-40"
                            :item-monospace="false"
                        />
                        <div class="flex gap-2">
                            <FormButton type="submit" :disabled="!groupName.trim()">{{ t('chat.groups.create') }}</FormButton>
                            <FormButton tone="neutral" @click="groupComposerOpen = false">{{ t('actions.cancel') }}</FormButton>
                        </div>
                    </form>
                    <form v-if="searchOpen" class="space-y-2 border-b border-zinc-200 p-3 dark:border-zinc-800" @submit.prevent="runSearch">
                        <FormInput
                            v-model="searchTerm"
                            :label="t('chat.search.query')"
                            :placeholder="t('chat.search.placeholder')"
                            :leading-icon="IconSearch"
                            inputmode="search"
                        />
                        <details>
                            <summary class="cursor-pointer text-sm font-medium text-teal-700 dark:text-teal-300">
                                {{ t('chat.search.filters') }}
                            </summary>
                            <div class="mt-2 space-y-2">
                                <FormInput v-model="searchAuthor" :label="t('chat.search.author')" />
                                <FormSelect
                                    v-model="searchConversation"
                                    :label="t('chat.search.conversation')"
                                    :options="searchConversations"
                                />
                                <div class="grid grid-cols-2 gap-2">
                                    <FormDateInput v-model="searchDateFrom" :label="t('chat.search.date_from')" />
                                    <FormDateInput v-model="searchDateTo" :label="t('chat.search.date_to')" />
                                </div>
                                <FormSelect v-model="searchType" :label="t('chat.search.type')" :options="searchTypes" />
                            </div>
                        </details>
                        <FormButton type="submit" :icon="IconSearch" :loading="searchLoading" :disabled="searchTerm.trim().length < 2">
                            {{ t('chat.search.submit') }}
                        </FormButton>
                    </form>
                    <div v-else class="flex gap-1 overflow-x-auto border-b border-zinc-200 p-2 dark:border-zinc-800" role="tablist">
                        <button
                            v-for="item in filters"
                            :key="item.value"
                            type="button"
                            class="min-h-9 shrink-0 rounded-md px-2 text-xs font-medium"
                            :class="
                                filter === item.value
                                    ? 'bg-teal-700 text-white'
                                    : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'
                            "
                            role="tab"
                            :aria-selected="filter === item.value"
                            @click="filter = item.value"
                        >
                            {{ item.label }}
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-2">
                        <template v-if="searchOpen">
                            <button
                                v-for="item in searchResults"
                                :key="`${item.type}-${item.publicId}`"
                                type="button"
                                class="mb-1 block min-h-14 w-full rounded-lg px-3 py-2 text-left enabled:hover:bg-teal-50 enabled:focus-visible:outline-2 enabled:focus-visible:outline-amber-500 disabled:cursor-default dark:enabled:hover:bg-teal-950"
                                :disabled="!searchResultActionable(item)"
                                @click="openSearchResult(item)"
                            >
                                <span class="block text-xs font-semibold uppercase text-teal-700 dark:text-teal-300">{{
                                    t(`chat.search.types.${item.type}`)
                                }}</span>
                                <strong class="block truncate text-sm">{{ item.title || t('chat.search.shared_transcript') }}</strong>
                                <span class="line-clamp-2 text-xs text-zinc-500">{{ item.snippet }}</span>
                            </button>
                            <UiState v-if="searchError" variant="error" :icon="IconSearch" :title="t('chat.search.unavailable')" />
                            <UiState
                                v-else-if="!searchLoading && searchTerm.trim().length >= 2 && searchResults.length === 0"
                                variant="empty"
                                :icon="IconSearch"
                                :title="t('chat.search.empty')"
                            />
                        </template>
                        <template v-else>
                            <button
                                v-for="conversation in filtered"
                                :key="conversation.publicId"
                                type="button"
                                class="mb-1 flex min-h-14 w-full items-center gap-2 rounded-lg px-3 text-left hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-amber-500 dark:hover:bg-teal-950"
                                @click="show(conversation)"
                            >
                                <span class="min-w-0 flex-1">
                                    <strong class="block truncate text-sm">{{ conversation.name }}</strong>
                                    <span class="block truncate text-xs text-zinc-500">{{
                                        t(`chat.shell.types.${conversation.type}`)
                                    }}</span>
                                </span>
                                <IconStarFilled v-if="conversation.favorite" aria-hidden="true" class="h-4 w-4 text-amber-500" />
                                <span v-if="conversation.unreadCount" class="rounded-full bg-rose-600 px-2 text-xs text-white">{{
                                    conversation.unreadCount
                                }}</span>
                            </button>
                            <UiState
                                v-if="!loading && filtered.length === 0"
                                variant="empty"
                                :icon="IconMessageCircle"
                                :title="t('chat.shell.empty')"
                            />
                            <UiState v-if="error" variant="error" :icon="IconMessageCircle" :title="t('chat.shell.error')" />
                        </template>
                    </div>
                </aside>
                <div v-if="active" class="flex min-w-0 flex-1 flex-col">
                    <div class="flex min-h-16 items-center gap-2 border-b border-zinc-200 px-3 dark:border-zinc-800">
                        <button type="button" class="min-h-11 rounded-md px-2 md:hidden" @click="activeId = null">
                            {{ t('chat.shell.back') }}
                        </button>
                        <h2 class="min-w-0 flex-1 truncate font-semibold">{{ active.name }}</h2>
                        <details v-if="groupDetails" class="relative">
                            <summary class="min-h-10 cursor-pointer rounded-md px-2 py-2 text-sm text-teal-700 dark:text-teal-300">
                                {{ t('chat.groups.manage') }}
                            </summary>
                            <div
                                class="absolute right-0 top-11 z-20 w-80 space-y-3 rounded-lg border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <p class="text-sm font-semibold">{{ t('chat.groups.members') }}</p>
                                <div v-for="member in groupDetails.members" :key="member.publicId" class="flex items-center gap-2 text-sm">
                                    <span class="min-w-0 flex-1 truncate">{{ member.name }}</span>
                                    <span v-if="member.role === 'owner'" class="text-xs text-zinc-500">{{ t('chat.groups.owner') }}</span>
                                    <button
                                        v-if="groupDetails.isOwner && member.role !== 'owner'"
                                        type="button"
                                        class="text-xs text-rose-700 dark:text-rose-300"
                                        @click="updateGroup('remove_member', { member_public_id: member.publicId })"
                                    >
                                        {{ t('actions.remove') }}
                                    </button>
                                    <button
                                        v-if="groupDetails.isOwner && member.role !== 'owner'"
                                        type="button"
                                        class="text-xs text-teal-700 dark:text-teal-300"
                                        @click="updateGroup('transfer_owner', { member_public_id: member.publicId })"
                                    >
                                        {{ t('chat.groups.transfer') }}
                                    </button>
                                </div>
                                <template v-if="groupDetails.isOwner">
                                    <FormInput v-model="groupDetails.name" :label="t('chat.groups.name')" />
                                    <FormButton tone="neutral" @click="updateGroup('rename', { name: groupDetails.name })">
                                        {{ t('actions.save') }}
                                    </FormButton>
                                    <FormSelect
                                        v-model="groupMemberSelection"
                                        :label="t('chat.groups.add_member')"
                                        :options="groupCandidateOptions"
                                    />
                                    <FormButton
                                        tone="neutral"
                                        :disabled="!groupMemberSelection"
                                        @click="updateGroup('add_member', { member_public_id: groupMemberSelection })"
                                    >
                                        {{ t('chat.groups.add_member') }}
                                    </FormButton>
                                </template>
                                <FormButton tone="danger" @click="updateGroup('leave')">{{ t('chat.groups.leave') }}</FormButton>
                            </div>
                        </details>
                        <div v-if="canExport" class="flex items-center gap-1">
                            <FormSelect
                                v-model="exportFormat"
                                :aria-label="t('chat.exports.format')"
                                :options="exportFormats"
                                button-class="min-h-9 w-24 py-1"
                            />
                            <IconButton :label="t('chat.exports.action')" :icon="IconDownload" @click="exportConversation" />
                        </div>
                        <IconButton
                            :label="active.favorite ? t('chat.shell.unfavorite') : t('chat.shell.favorite')"
                            :icon="active.favorite ? IconStarFilled : IconStar"
                            @click="toggleFavorite(active)"
                        />
                        <IconButton :label="t('actions.close')" :icon="IconX" @click="close" />
                    </div>
                    <div class="flex-1 space-y-3 overflow-y-auto p-4" aria-live="polite">
                        <div v-if="hasOlder" class="text-center">
                            <FormButton tone="neutral" :loading="loadingOlder" @click="loadOlder">
                                {{ t('chat.shell.load_older') }}
                            </FormButton>
                        </div>
                        <article
                            v-for="message in messages"
                            :key="message.publicId"
                            class="max-w-3xl rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-900"
                            :class="message.authorPublicId === page.props.auth.user?.publicId ? 'ml-auto bg-teal-50 dark:bg-teal-950' : ''"
                        >
                            <p v-if="message.forwarded" class="mb-1 text-xs text-zinc-500">{{ t('chat.messages.forwarded') }}</p>
                            <p v-if="message.replyToMessagePublicId" class="mb-1 text-xs text-zinc-500">{{ t('chat.messages.reply') }}</p>
                            <p v-if="message.deletedForViewer" class="italic text-zinc-500">{{ t('chat.messages.deleted_for_me') }}</p>
                            <!-- eslint-disable-next-line vue/no-v-html -- Chat Markdown is sanitized by the authoritative backend renderer. -->
                            <div v-else class="prose prose-sm max-w-none dark:prose-invert" v-html="message.renderedHtml"></div>
                            <div v-for="attachment in message.attachments" :key="attachment.publicId" class="mt-2 text-xs">
                                <audio
                                    v-if="attachment.kind === 'voice' && attachment.available && active"
                                    controls
                                    preload="metadata"
                                    :aria-label="t('chat.messages.voice_playback')"
                                    :src="`/chat/conversations/${active.publicId}/attachments/${attachment.publicId}/download`"
                                ></audio>
                                <span v-else>{{ attachment.name }} · {{ attachment.scanState }}</span>
                            </div>
                            <div v-if="!message.deletedForViewer" class="mt-2 flex flex-wrap items-center gap-1 text-xs">
                                <span v-if="message.edited" class="text-zinc-500">{{ t('chat.messages.edited') }}</span>
                                <span v-if="message.pinned" class="text-amber-700 dark:text-amber-300">{{
                                    t('chat.messages.pinned')
                                }}</span>
                                <span v-if="message.bookmarked" class="text-teal-700 dark:text-teal-300">{{
                                    t('chat.messages.bookmarked')
                                }}</span>
                                <span v-for="reaction in message.reactions" :key="`${reaction.userPublicId}-${reaction.emoji}`">{{
                                    reaction.emoji
                                }}</span>
                                <button
                                    type="button"
                                    class="rounded px-1 hover:bg-zinc-200 dark:hover:bg-zinc-800"
                                    @click="replyTarget = message"
                                >
                                    {{ t('chat.messages.reply') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded px-1 hover:bg-zinc-200 dark:hover:bg-zinc-800"
                                    @click="
                                        messageAction(
                                            message,
                                            message.reactions.some(
                                                (item) => item.userPublicId === page.props.auth.user?.publicId && item.emoji === '👍',
                                            )
                                                ? 'remove_reaction'
                                                : 'react',
                                            { emoji: '👍' },
                                        )
                                    "
                                >
                                    👍
                                </button>
                                <IconButton
                                    :label="message.pinned ? t('chat.messages.unpin') : t('chat.messages.pin')"
                                    :icon="IconPinned"
                                    @click="messageAction(message, message.pinned ? 'unpin' : 'pin')"
                                />
                                <IconButton
                                    :label="message.bookmarked ? t('chat.messages.unbookmark') : t('chat.messages.bookmark')"
                                    :icon="IconBookmark"
                                    @click="messageAction(message, message.bookmarked ? 'remove_bookmark' : 'bookmark')"
                                />
                                <IconButton
                                    v-if="message.authorPublicId === page.props.auth.user?.publicId"
                                    :label="t('chat.messages.edit')"
                                    :icon="IconEdit"
                                    @click="beginEdit(message)"
                                />
                                <IconButton :label="t('chat.messages.history')" :icon="IconHistory" @click="showHistory(message)" />
                                <IconButton
                                    :label="t('chat.messages.delete_for_me')"
                                    :icon="IconTrash"
                                    @click="messageAction(message, 'delete_for_me')"
                                />
                            </div>
                            <div v-if="!message.deletedForViewer" class="mt-2 flex items-end gap-1">
                                <FormSelect
                                    v-model="forwardDestinations[message.publicId]"
                                    :aria-label="t('chat.messages.forward_choose')"
                                    :options="forwardingOptions"
                                    button-class="min-h-8 py-1"
                                />
                                <button
                                    type="button"
                                    class="min-h-8 rounded px-2 text-xs text-teal-700 hover:bg-zinc-200 dark:text-teal-300 dark:hover:bg-zinc-800"
                                    :disabled="!forwardDestinations[message.publicId]"
                                    @click="forwardMessage(message)"
                                >
                                    {{ t('chat.messages.forward') }}
                                </button>
                            </div>
                            <ol v-if="revisions[message.publicId]" class="mt-2 border-t border-zinc-200 pt-2 text-xs dark:border-zinc-700">
                                <li v-for="revision in revisions[message.publicId]" :key="revision.version">
                                    {{ t('chat.messages.version', { version: revision.version }) }}: {{ revision.body }}
                                </li>
                            </ol>
                        </article>
                        <p v-if="messages.length === 0" class="text-center text-sm text-zinc-500">{{ t('chat.shell.messages_empty') }}</p>
                    </div>
                    <form class="border-t border-zinc-200 p-3 dark:border-zinc-800" @submit.prevent="send">
                        <div
                            v-if="replyTarget || editingMessage"
                            class="mb-2 flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-300"
                        >
                            <span>{{ editingMessage ? t('chat.messages.editing') : t('chat.messages.replying') }}</span>
                            <button
                                type="button"
                                class="text-teal-700 dark:text-teal-300"
                                @click="
                                    replyTarget = null;
                                    editingMessage = null;
                                    draft = '';
                                "
                            >
                                {{ t('actions.cancel') }}
                            </button>
                        </div>
                        <div class="flex items-end gap-2">
                            <FormTextarea
                                v-model="draft"
                                :rows="2"
                                :aria-label="t('chat.shell.message')"
                                class="min-w-0 flex-1"
                            /><FormButton type="submit" :icon="IconSend" :loading="sending" :disabled="!draft.trim()">
                                {{ t('chat.shell.send') }}
                            </FormButton>
                        </div>
                    </form>
                </div>
                <div v-else class="hidden flex-1 items-center justify-center md:flex">
                    <p class="text-sm text-zinc-500">{{ t('chat.shell.choose') }}</p>
                </div>
            </section>
        </div>
    </Teleport>
</template>
