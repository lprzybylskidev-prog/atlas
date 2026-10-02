export type BrowserNotificationChannel = 'notifications' | 'chat';

function key(channel: BrowserNotificationChannel, userPublicId: string): string {
    return `atlas.${channel}.native.${userPublicId}`;
}

export function browserNotificationsEnabled(channel: BrowserNotificationChannel, userPublicId: string | null | undefined): boolean {
    return typeof userPublicId === 'string' && localStorage.getItem(key(channel, userPublicId)) === 'true';
}

export function setBrowserNotificationsEnabled(
    channel: BrowserNotificationChannel,
    userPublicId: string | null | undefined,
    enabled: boolean,
): void {
    if (typeof userPublicId === 'string') localStorage.setItem(key(channel, userPublicId), String(enabled));
}
