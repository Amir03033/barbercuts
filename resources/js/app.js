// Is de zaak alleen-lezen (abonnement verlopen), dan weigert de server wijzigingen met een 403.
// Toon dan een toast in plaats van de technische foutmelding van Livewire (docs/BOUWOPDRACHT.md §7.7).
document.addEventListener('livewire:init', () => {
    window.Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            if (response?.status === 403 && response.headers.get('X-Shop-Read-Only')) {
                preventDefault();
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: 'Je abonnement is verlopen. Je kunt alles bekijken, maar niets wijzigen.', type: 'error' },
                }));
            }
        });
    });
});

// Web push (docs/BOUWOPDRACHT.md §7.6): <x-push-toggle> gebruikt deze Alpine-component. De toestemming wordt alleen
// gevraagd na een tik van de gebruiker, nooit bij het eerste bezoek.
document.addEventListener('alpine:init', () => {
    const toKey = (base64) => {
        const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

        return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
    };

    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
    });

    window.Alpine.data('bcPush', (publicKey, subscribeUrl, unsubscribeUrl) => ({
        state: 'checking', // checking | unsupported | denied | off | on
        busy: false,

        async init() {
            if (!publicKey || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                this.state = 'unsupported';

                return;
            }

            if (Notification.permission === 'denied') {
                this.state = 'denied';

                return;
            }

            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                this.state = subscription && Notification.permission === 'granted' ? 'on' : 'off';
            } catch (error) {
                this.state = 'unsupported';
            }
        },

        async enable() {
            this.busy = true;

            try {
                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    this.state = permission === 'denied' ? 'denied' : 'off';

                    return;
                }

                const registration = await navigator.serviceWorker.ready;
                const subscription = (await registration.pushManager.getSubscription())
                    ?? await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toKey(publicKey) });
                const json = subscription.toJSON();
                const response = await post(subscribeUrl, { endpoint: json.endpoint, keys: json.keys, contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0] });

                this.state = response.ok ? 'on' : 'off';
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: response.ok ? 'Meldingen staan aan op dit apparaat' : 'Aanzetten is niet gelukt', type: response.ok ? 'success' : 'error' } }));
            } catch (error) {
                this.state = 'off';
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Aanzetten is niet gelukt', type: 'error' } }));
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;

            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    await post(unsubscribeUrl, { endpoint: subscription.endpoint });
                    await subscription.unsubscribe();
                }

                this.state = 'off';
            } finally {
                this.busy = false;
            }
        },
    }));
});

// Landingspagina: onderdelen met data-reveal schuiven in beeld zodra ze zichtbaar worden. Zonder dit script
// (of met "minder beweging" aan) staat alles er gewoon; de css verbergt alleen als html.reveal-on gezet is.
const startReveal = () => {
    const items = document.querySelectorAll('[data-reveal]:not(.is-visible)');

    if (!items.length) {
        return;
    }

    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        items.forEach((item) => item.classList.add('is-visible'));

        return;
    }

    document.documentElement.classList.add('reveal-on');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    items.forEach((item) => observer.observe(item));
};

document.addEventListener('DOMContentLoaded', startReveal);
document.addEventListener('livewire:navigated', startReveal);

document.addEventListener('alpine:init', () => {
    // Een getal dat optelt zodra het in beeld komt (bedragen op de landingspagina).
    window.Alpine.data('bcCount', (target, duration = 1400) => ({
        value: 0,

        init() {
            if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.value = target;

                return;
            }

            const observer = new IntersectionObserver(([entry]) => {
                if (!entry.isIntersecting) {
                    return;
                }

                observer.disconnect();
                const start = performance.now();
                const tick = (now) => {
                    const progress = Math.min(1, (now - start) / duration);
                    this.value = Math.round(target * (1 - Math.pow(1 - progress, 3)));

                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    }
                };

                requestAnimationFrame(tick);
            }, { threshold: 0.4 });

            observer.observe(this.$el);
        },

        get label() {
            return this.value.toLocaleString('nl-NL');
        },
    }));
});
