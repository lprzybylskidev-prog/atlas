export type ChatAttachmentState = 'uploading' | 'pending' | 'scanning' | 'clean' | 'infected' | 'failed' | 'unsupported';

export interface ChatAttachmentPayload {
    publicId: string;
    kind: 'file' | 'voice';
    name: string;
    mimeType: string;
    sizeBytes: number;
    durationSeconds: number | null;
    scanState: Exclude<ChatAttachmentState, 'uploading'>;
    available: boolean;
    previewable: boolean;
    attached: boolean;
}

function csrfToken(): string {
    const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));
    if (cookie) return decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function uploadChatAttachment(
    url: string,
    file: File,
    durationSeconds: number | null,
    progress: (percent: number) => void,
): Promise<ChatAttachmentPayload> {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('POST', url);
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-XSRF-TOKEN', csrfToken());
        request.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) progress(Math.round((event.loaded / event.total) * 100));
        });
        request.addEventListener('load', () => {
            if (request.status < 200 || request.status >= 300) {
                reject(new Error(`Upload failed with status ${request.status}.`));
                return;
            }

            try {
                resolve(JSON.parse(request.responseText) as ChatAttachmentPayload);
            } catch {
                reject(new Error('Upload returned an invalid response.'));
            }
        });
        request.addEventListener('error', () => reject(new Error('Upload failed.')));
        const data = new FormData();
        data.append('file', file);
        if (durationSeconds !== null) data.append('duration_seconds', String(durationSeconds));
        request.send(data);
    });
}

export async function chatJson<T>(url: string, method = 'GET', body?: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) throw new Error(`Chat request failed with status ${response.status}.`);
    return (await response.json()) as T;
}
