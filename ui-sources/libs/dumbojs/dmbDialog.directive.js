import {
    DmbEvents,
    DumboDirective
} from "./dumbo.min.js";

export class DmbDialog extends DumboDirective {
    static selector = 'dmb-dialog';
    static get observedAttributes() { return ['open']; }
    static template = '<dmb-view class="wrapper" transclude></dmb-view>';
    returnValue = null;


    attributeChangedCallback(attr, oldValue, newValue) {
        this.openValue = (newValue !== null);
    }

    close(value) {
        this.returnValue = value;
        this.removeAttribute('open');
        this.dispatchEvent(new Event('close'));
        (this.localName === 'dmb-dialog') && this.remove();
        this.dispatchEvent(new Event('close-dialog'));
        return true;
    }

    open() {
        this.hasAttribute('open') || this.setAttribute('open','');
        this.dispatchEvent(DmbEvents.dialogOpen.event);
    }

    showModal() {
        const buttons = this.querySelectorAll('[type="modal-answer"]');

        [].forEach.call(buttons, button => {
            button.addEventListener('click', e => {
                this.close(e.target.getAttribute('value'));
            });
        });
        this.open();
    }

    init() {
        let delay = 1 * parseInt(this.getAttribute('delay'));
        this.hasAttribute('no-close') || this.setCloseButton();

        setTimeout(() => {
            this.open();
        }, delay);
    }

    setCloseButton() {
        let icon = null;

        if (!this.classList.contains('loader')) {
            icon = this.querySelector('.close-modal-button');
            if(!icon || !icon.length) {
                icon = document.createElement('span');
                icon.setAttribute('icon', 'close');
                icon.classList.add('close-modal-button');
                this.querySelector('.wrapper').prepend(icon);
                icon.addEventListener('click', (e) => {
                    e.target.closest('dmb-dialog').close('cancelled');
                });
            }
        }
    }

    isOpen() {
        return this.openValue;
    }

    setIcon(icon) {
        const iconElement = document.createElement('span');
        const wrapper = this.querySelector('.wrapper');

        iconElement.setAttribute('icon',icon);

        wrapper.prepend(iconElement);
    }

    error(msg) {
        const message = document.createElement('span');
        const wrapper = this.querySelector('.wrapper');

        this.setIcon('warning');
        msg = msg || '';
        this.classList.add('error');
        message.classList.add('message');
        if (typeof msg === 'string') {
            message.innerHTML = msg;
        } else {
            message.append(msg);
        }
        wrapper.append(message);
    }

    info(msg) {
        const message = document.createElement('span');
        const wrapper = this.querySelector('.wrapper');

        this.setIcon('info');
        msg = msg || '';
        this.classList.add('info');
        message.classList.add('message');
        if (typeof msg === 'string') {
            message.innerHTML = msg;
        } else {
            message.append(msg);
        }
        wrapper.append(message);
    }

    prompt(options) {
        const message = document.createElement('span');
        const wrapper = this.querySelector('.wrapper');
        let msg = options.message || '';

        this.classList.add('question');
        message.classList.add('message');
        if (typeof msg === 'string') {
            message.innerHTML = msg;
        } else {
            message.append(msg);
        }
        wrapper.append(message);
    }

    onClose(fn) {
        if (typeof fn === 'function') {
            this.addEventListener('close-dialog', fn);
        }
    }
}
