import { router } from '@inertiajs/vue3';

export interface BulkRecordAction {
    method?: 'post' | 'patch' | 'delete';
    href: (rowId: string) => string;
}

export async function runBulkRecordAction(action: BulkRecordAction, rowIds: string[]): Promise<void> {
    for (const rowId of rowIds) {
        const response = await window.fetch(action.href(rowId), {
            method: (action.method ?? 'post').toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html, application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
        });

        if (!response.ok) {
            throw new Error(`Bulk action failed for ${rowId}.`);
        }
    }

    router.reload();
}

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return cookie === undefined ? '' : decodeURIComponent(cookie);
}
