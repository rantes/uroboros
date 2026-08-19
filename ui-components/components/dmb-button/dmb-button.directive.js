import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbButton extends DumboDirective {
    static selector = 'dmb-button';

    _submitter(e) {
        // Bloque de definiciones
        const form = e.target.closest('dmb-form');
        const panel = e.target.closest('dmb-panel');
        const type = e.target.getAttribute('type');

        // Lógica
        switch (type) {
            case 'submit':
                form && form.submit();
                break;
            case 'reset':
                form && form.reset();
                break;
            case 'cancel':
                form && form.reset();
                panel && panel.close('cancelled');
                break;
        }
    }

    init() {
        this.addEventListener('click', this._submitter);
    }

    /**
     * Attach a method to run when event click is fired.
     * @param {function} method
     */
    onClick(method) {
        if (typeof method === 'function') {
            this.removeEventListener('click', this._submitter);
            this.addEventListener('click', method);
        }
    }
}
