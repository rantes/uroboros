import { DumboDirective } from "./dumbo.min.js";

export class DmbButton extends DumboDirective {
    static selector = 'dmb-button';

    _submitter(e) {
        const form = e.target.closest('dmb-form');
        const type = e.target.getAttribute('type');

        if (form) {
            switch (type) {
            case 'submit':
                form.submit();
                break;
            case 'reset':
                form.reset();
                break;
            }
        }
    }

    init() {
        this.addEventListener('click', this._submitter);
    }

    /**
     * Attach a method to run when event click is fired.
     * @param {function} method
     */
    click(method) {
        if (typeof method === 'function') {
            this.removeEventListener('click', this._submitter);
            this.addEventListener('click', method);
        }
    }
}
