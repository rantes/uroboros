import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

export class DmbQrCode extends DumboDirective {
    static selector = 'dmb-qr-code';
    static template = '<canvas></canvas>';

    #_value = '';
    #_size = 240;

    init() {
        this.#_value = this.getAttribute('value') || '';
        this.#_size = parseInt(this.getAttribute('size')) || 240;
        this.render();
    }

    render(retries = 20) {
        if (!window.QRCode) {
            retries > 0 && setTimeout(() => this.render(retries - 1), 100);
            return;
        }

        const canvas = this.querySelector('canvas');
        window.QRCode.toCanvas(canvas, this.#_value, { width: this.#_size }, (err) => {
            err && console.error(err);
        });
    }
}
