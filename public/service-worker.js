self.addEventListener('push', (event) => {
    const fallback = {
        title: 'Barbershop CRM',
        body: 'You have a new staff notification.',
        url: '/notifications',
        tag: 'barbershop-crm-update',
    };

    let payload = fallback;
    try {
        const incoming = event.data?.json();
        if (incoming && typeof incoming === 'object') {
            payload = {
                title: typeof incoming.title === 'string' ? incoming.title : fallback.title,
                body: typeof incoming.body === 'string' ? incoming.body : fallback.body,
                url: typeof incoming.url === 'string' ? incoming.url : fallback.url,
                tag: typeof incoming.tag === 'string' ? incoming.tag : fallback.tag,
            };
        }
    } catch {
        payload = fallback;
    }

    event.waitUntil(self.registration.showNotification(payload.title, {
        body: payload.body,
        icon: '/brand/barber-crm-mark.svg',
        badge: '/brand/barber-crm-mark.svg',
        tag: payload.tag,
        data: { url: payload.url },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const requested = event.notification.data?.url;
    let target = '/notifications';
    if (typeof requested === 'string' && requested.startsWith('/') && !requested.startsWith('//')) {
        target = requested;
    }

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        const existing = clients.find((client) => new URL(client.url).origin === self.location.origin);
        if (existing) {
            return existing.navigate(target).then((navigated) => (navigated ?? existing).focus());
        }

        return self.clients.openWindow(target);
    }));
});
