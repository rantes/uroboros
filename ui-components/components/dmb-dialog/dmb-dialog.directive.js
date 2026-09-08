import {
    DmbEvents,
    DumboDirective
} from "../../libs/dumbojs/dumbo.min.js";

export class DmbDialog extends DumboDirective {
    static selector = 'dmb-dialog';
    // El archivo vivía como dmbDialog.html (camelCase, sin guiones) —
    // no coincidía con la convención kebab-case del proyecto ni con
    // ningún templateUrl declarado aquí (no existía ninguno). Sin
    // esto, el elemento nunca recibía su plantilla (.wrapper nunca se
    // creaba), y cualquier uso de DmbDialogService (loader/error/
    // info/drawer, usados por appModel.login/createData/updateData)
    // fallaba con "Cannot set properties of null (setting
    // 'innerHTML')" — confirmado con DumboChromeDriver mientras se
    // corregía el formulario de login.
    static templateUrl = 'dmb-dialog.html';
    static get observedAttributes() { return ['open']; }
    returnValue = null;


    attributeChangedCallback(attr, oldValue, newValue) {
        this.openValue = (newValue !== null);
    }

    close(value, remove = false) {
        this.returnValue = value;
        // Carrera real corregida — DmbDialogService.loader()/error()/
        // info() crean el elemento y le piden abrirse (showModal())
        // solo DESPUÉS de que su propio templateUrl termine de
        // cargarse (#afterRendered, async). Si el caller (ej.
        // appModel.updateData()) llama close() sobre el loader ANTES
        // de que ese fetch async del propio dmb-dialog haya
        // terminado, close() no tenía nada que hacer (el atributo
        // 'open' ni existía todavía) y el open() diferido, que llega
        // más tarde, volvía a abrir un diálogo que ya se había
        // "cerrado" — quedaba visible indefinidamente por encima del
        // diálogo de error real, tapando su mensaje (confirmado real:
        // un dmb-dialog.loader y un dmb-dialog.error con open="" los
        // dos a la vez tras un 403 real de project_credentials).
        // dismissed hace que open() sea un no-op para siempre después
        // de este close() — correcto para el patrón de este proyecto,
        // donde cada diálogo es un elemento de un solo uso (nunca se
        // reabre el mismo <dmb-dialog> tras cerrarlo).
        this.dismissed = true;
        this.removeAttribute('open');
        this.dispatchEvent(new Event('close'));
        this.dispatchEvent(new Event('close-dialog'));
        if (remove) {
            this.remove();
        }
        return true;
    }

    open() {
        if (this.dismissed) {
            return;
        }
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
        let delay = 1000 * this.getAttribute('delay');
        this.hasAttribute('no-close') || this.setCloseButton();

        if (!this.hasAttribute('no-auto-open')) {
            setTimeout(() => {
                this.open();
            }, delay);
        }
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

        this.setCloseButton();
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
