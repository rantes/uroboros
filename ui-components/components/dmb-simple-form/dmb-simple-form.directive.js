import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
import { appModel } from '../models/app-model.factory.js';
import { DmbDialogService } from '../dmb-dialog/dmb-dialog.factory.js';
/**
 *
 */
export class DmbSimpleForm extends DumboDirective {
    static selector = 'dmb-simple-form';

    #_dialog = null;

    init() {
        const form = this.querySelector('dmb-form');
        const target = form.getAttribute('action');
        const redirect = this.getAttribute('redirect');
        const isUpdate = this.hasAttribute('update');
        const closePanel = this.getAttribute('close-panel');
        const confirmMessage = this.getAttribute('confirm');
        let panel = null;

        this.#_dialog = new DmbDialogService();

        const send = (formSubmitted) => {
            appModel.url(target);
            if (isUpdate) {
                appModel.updateData(formSubmitted.getFormData(), redirect);
            } else {
                appModel.createData(formSubmitted.getFormData(), redirect);
            }
            if (closePanel) {
                panel = document.querySelector(closePanel);
                if (panel) {
                    panel.close();
                }
            }
        };

        form.callback = (formSubmitted) => {
            if (confirmMessage) {
                this.#confirm(confirmMessage, () => send(formSubmitted));
            } else {
                send(formSubmitted);
            }
        };
    }

    /**
     * Muestra un diálogo de confirmación antes de enviar el formulario.
     * @param {string} message
     * @param {function} onConfirm
     */
    #confirm(message, onConfirm) {
        const wrapper = document.createElement('div');
        wrapper.classList.add('confirm-dialog');

        const text = document.createElement('p');
        text.classList.add('confirm-dialog-message');
        text.textContent = message;

        const actions = document.createElement('div');
        actions.classList.add('confirm-dialog-actions');

        const cancel = document.createElement('dmb-button');
        cancel.textContent = 'Cancelar';

        const confirm = document.createElement('dmb-button');
        confirm.classList.add('primary');
        confirm.textContent = 'Confirmar';

        actions.append(cancel, confirm);
        wrapper.append(text, actions);

        const dialog = this.#_dialog.drawer(wrapper, 'small', false);

        cancel.addEventListener('click', () => dialog.close('cancelled'));
        confirm.addEventListener('click', () => {
            dialog.close('confirmed');
            onConfirm();
        });
    }
}
