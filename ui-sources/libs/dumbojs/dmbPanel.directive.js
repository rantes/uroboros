import { DmbEvents, DumboDirective } from "./dumbo.min.js";

export class DmbPanel extends DumboDirective {
    static selector = 'dmb-panel';
    static get observedAttributes() { return ['source']; }
    static template = '<dmb-view class="wrapper" transclude></dmb-view>';
    returnValue = null;

    attributeChangedCallback(attr, oldValue, newValue) {
        attr === 'source' && !!newValue && newValue.length && this.loadExternalSource();
    }

    loadExternalSource() {
        const url = this.getAttribute('source');
        let wrapper = null;
        let sourceRequest = null;

        if (url && url.length) {
            sourceRequest = new Request(url);
            fetch(sourceRequest)
                .then(res => res.text())
                .then(data => {
                    wrapper = this.querySelector('.wrapper');
                    wrapper.innerHTML = data;
                });
        }

        return true;
    }

    close(value) {
        this.returnValue = value;
        this.removeAttribute('open');
        this.dispatchEvent(DmbEvents.panelClose.event);
        (this.localName === 'dmb-dialog') && this.remove();
        this.dispatchEvent(DmbEvents.dialogClose.event);
        this.dispatchEvent(DmbEvents.panelClosed.event);
    }

    open() {
        this.setAttribute('open','');
        this.dispatchEvent(DmbEvents.panelOpened.event);
        this.addEventListener('click', (e) => {
            if (this.openValue && e.target === this) {
                this.close('cancelled');
            }
        },true);
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

    setCloseButton() {
        let icon = null;

        if (!this.classList.contains('loader')) {
            icon = document.createElement('i');
            icon.classList.add('icon');
            icon.classList.add('icon-cancel');
            icon.classList.add('close-modal-button');
            icon.addEventListener('click', (e) => {
                e.target.closest('dmb-dialog').close('cancelled');
            });
            this.querySelector('.wrapper').prepend(icon);
        }
    }

    isOpen() {
        return this.openValue;
    }

    setIcon(icon) {
        const iconElement = document.createElement('i');
        const wrapper = this.querySelector('.wrapper');

        iconElement.classList.add('icon');
        iconElement.classList.add(`icon-${icon}`);

        wrapper.prepend(iconElement);
    }

    error(msg) {
        const message = document.createElement('span');
        const wrapper = this.querySelector('.wrapper');

        this.setCloseButton();
        this.setIcon('alert');
        msg = msg || '';
        this.classList.add('error');
        message.classList.add('message');
        message.textContent = msg;
        wrapper.append(message);
    }

    prompt(options) {
        const message = document.createElement('span');
        const wrapper = this.querySelector('.wrapper');
        let msg = options.message || '';

        this.classList.add('question');
        message.classList.add('message');
        message.textContent = msg;
        wrapper.append(message);
    }

    onClose(fn) {
        if (typeof fn === 'function') {
            this.addEventListener(DmbEvents.panelClose.listener, fn);
        }
    }

    init() {
        !this.classList.contains('right') && !this.classList.contains('left') && this.classList.add('right');
        !this.classList.contains('small') && !this.classList.contains('large') && this.classList.add('small');

        this.addEventListener('click', (e) => {
            e.target === this && this.close('cancelled');
        }, true);
    }
}
