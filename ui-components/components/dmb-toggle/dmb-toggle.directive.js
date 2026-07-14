import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";
export class DmbToggle extends DumboDirective {
    static selector = 'dmb-toggle';
    static get observedAttributes() { return ['value']; }
    static template = '<span class="switch"></span>';
    val = 0;

    set value(val = 0) {
        this.val = val;
        this.setStatus();
    }

    get value() {
        return this.val;
    }

    attributeChangedCallback(attr, oldValue, newValue) {
        switch(attr) {
        case 'value':
            this.val = parseInt(newValue);
            this.setStatus();
            break;
        }
    }

    init() {
        this.val = parseInt(this.getAttribute('value') || '0');
        this.setStatus();

        this.addEventListener('click', () => {
            this.val = 1 * !this.val;
            this.setStatus();
        });
    }

    setStatus() {
        if (this.val === 1) {
            this.classList.add('on');
        } else {
            this.classList.remove('on');
        }
    }

    /**
     * Attach a method to run when event click is fired.
     * @param {function} method
     */
    click(method) {
        if (typeof method === 'function') {
            this.addEventListener('click', method);
        }
    }
}
