import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
import { BaseModelClass } from '../models/base-model.factory.js';

/**
 * KMD-WEBPUSH — Gestiona la suscripción Web Push del residente.
 *
 * Atributos:
 *   vapid-key-url   — GET que devuelve { d: { public_key } } (clave VAPID pública)
 *   subscribe-url   — POST endpoint/p256dh/auth/user_agent
 *   unsubscribe-url — POST endpoint
 *
 * No solicita permiso automáticamente: el usuario debe pulsar el botón (buena UX
 * y requisito de los navegadores). CSP-safe: el DOM y los listeners se crean en
 * JS, sin handlers inline. Autocontenido (no usa spinalCord).
 */
export class DmbPushSubscribe extends DumboDirective {
    static selector = 'dmb-push-subscribe';

    #_status = null;
    #_button = null;
    #_subscription = null;

    init() {
        this.#_render();

        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            this.#_setState('unsupported');
            return;
        }

        navigator.serviceWorker.ready
            .then(reg => reg.pushManager.getSubscription())
            .then(sub => {
                this.#_subscription = sub;
                this.#_setState(sub ? 'subscribed' : 'unsubscribed');
            })
            .catch(() => this.#_setState('unsubscribed'));
    }

    #_render() {
        this.#_status = document.createElement('p');
        this.#_status.className = 'push-status';

        this.#_button = document.createElement('button');
        this.#_button.type = 'button';
        this.#_button.className = 'push-toggle button';
        this.#_button.addEventListener('click', () => this.#_onClick());

        this.appendChild(this.#_status);
        this.appendChild(this.#_button);
    }

    #_setState(state) {
        this.dataset.state = state;
        const map = {
            unsupported:  ['Tu navegador no soporta notificaciones push.', '', true],
            unsubscribed: ['Las notificaciones push están desactivadas.', 'Activar notificaciones', false],
            subscribed:   ['Recibirás notificaciones push.', 'Desactivar notificaciones', false],
            working:      ['Procesando…', '', true],
        };
        const [text, label, hideButton] = map[state] || map.unsubscribed;
        this.#_status.textContent = text;
        this.#_button.textContent = label;
        this.#_button.hidden = hideButton || label === '';
    }

    #_onClick() {
        (this.dataset.state === 'subscribed' ? this.#_unsubscribe() : this.#_subscribe());
    }

    #_subscribe() {
        this.#_setState('working');

        const model = new BaseModelClass();
        model.useCache = false;
        model.url(this.getAttribute('vapid-key-url'));

        model.getFromServer()
            .then(res => {
                const key = res && res.d ? res.d.public_key : '';
                if (!key) throw new Error('Clave VAPID no configurada.');
                return navigator.serviceWorker.ready.then(reg => reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.#_urlB64ToUint8(key),
                }));
            })
            .then(sub => {
                this.#_subscription = sub;
                return this.#_postSubscription(sub);
            })
            .then(() => this.#_setState('subscribed'))
            .catch(() => this.#_setState('unsubscribed'));
    }

    #_unsubscribe() {
        this.#_setState('working');
        const sub = this.#_subscription;

        if (!sub) {
            this.#_setState('unsubscribed');
            return;
        }

        const body = new FormData();
        body.append('endpoint', sub.endpoint);

        const model = new BaseModelClass();
        model.useCache = false;
        model.url(this.getAttribute('unsubscribe-url'));

        sub.unsubscribe()
            .then(() => model.postToServer(body))
            .then(() => {
                this.#_subscription = null;
                this.#_setState('unsubscribed');
            })
            .catch(() => this.#_setState('subscribed'));
    }

    #_postSubscription(sub) {
        const body = new FormData();
        body.append('endpoint', sub.endpoint);
        body.append('p256dh', this.#_bufToB64Url(sub.getKey('p256dh')));
        body.append('auth', this.#_bufToB64Url(sub.getKey('auth')));
        body.append('user_agent', navigator.userAgent);

        const model = new BaseModelClass();
        model.useCache = false;
        model.url(this.getAttribute('subscribe-url'));
        return model.postToServer(body);
    }

    #_urlB64ToUint8(base64) {
        const padding = '='.repeat((4 - (base64.length % 4)) % 4);
        const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        const out = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    #_bufToB64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let bin = '';
        for (let i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
}
