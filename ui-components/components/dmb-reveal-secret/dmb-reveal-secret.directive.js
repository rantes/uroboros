import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
import { DmbDialogService } from '../dmb-dialog/dmb-dialog.factory.js';

export class DmbRevealSecret extends DumboDirective {
    static selector = 'dmb-reveal-secret';
    static template = '<span transclude></span>';

    #_dialog = null;

    constructor() {
        super();
        this.#_dialog = new DmbDialogService();
    }

    init() {
        this.classList.add('button');
        this.addEventListener('click', () => {
            this.#_reveal();
        });
    }

    #_reveal() {
        const url = this.getAttribute('url');

        fetch(new Request(url, { method: 'GET' }))
            .then(response => response.json())
            .then(data => {
                this.#_dialog.info(data.d.content);
            })
            .catch(() => {
                this.#_dialog.error('No se pudo obtener el contenido.');
            });
    }
}
