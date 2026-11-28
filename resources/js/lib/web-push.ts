export function supportsBrowserPush(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.isSecureContext &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

export async function getBrowserPushSubscription(): Promise<PushSubscription | null> {
    if (!supportsBrowserPush()) {
        return null;
    }

    const registration = await navigator.serviceWorker.register(
        '/service-worker.js',
        { scope: '/' },
    );
    return registration.pushManager.getSubscription();
}

export async function enableBrowserPush(publicKey: string): Promise<void> {
    if (!supportsBrowserPush()) {
        throw new Error(
            'This browser cannot receive push notifications on this connection.',
        );
    }

    const permission =
        Notification.permission === 'default'
            ? await Notification.requestPermission()
            : Notification.permission;
    if (permission !== 'granted') {
        throw new Error(
            'Allow notifications in your browser settings, then try again.',
        );
    }

    const registration = await navigator.serviceWorker.register(
        '/service-worker.js',
        { scope: '/' },
    );
    const subscription =
        (await registration.pushManager.getSubscription()) ??
        (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: decodeVapidKey(publicKey),
        }));
    const response = await fetch('/settings/security/web-push-subscriptions', {
        method: 'POST',
        credentials: 'same-origin',
        headers: requestHeaders(),
        body: JSON.stringify(subscription.toJSON()),
    });
    if (!response.ok) {
        throw await responseError(response);
    }
}

export async function disableBrowserPush(): Promise<void> {
    const subscription = await getBrowserPushSubscription();
    if (!subscription) {
        return;
    }

    const response = await fetch('/settings/security/web-push-subscriptions', {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: requestHeaders(),
        body: JSON.stringify({ endpoint: subscription.endpoint }),
    });
    if (!response.ok) {
        throw await responseError(response);
    }

    await subscription.unsubscribe();
}

function decodeVapidKey(value: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = `${value.replace(/-/g, '+').replace(/_/g, '/')}${padding}`;
    const decoded = window.atob(base64);
    const bytes = new Uint8Array(decoded.length);
    for (let index = 0; index < decoded.length; index += 1) {
        bytes[index] = decoded.charCodeAt(index);
    }

    return bytes;
}

function requestHeaders(): HeadersInit {
    const token = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token ?? '',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

async function responseError(response: Response): Promise<Error> {
    const payload: unknown = await response.json().catch(() => null);
    if (
        typeof payload === 'object' &&
        payload !== null &&
        'message' in payload &&
        typeof payload.message === 'string'
    ) {
        return new Error(payload.message);
    }

    return new Error(
        'Could not update browser notification settings. Please try again.',
    );
}
