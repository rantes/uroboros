export class DmbDialogService {

    setMessage(dialog, msg) {
        const message = document.createElement('span');

        message.classList.add('message');
        message.textContent = msg;
        dialog.append(message);

        return true;
    }

    open() {
        const dialog = document.createElement('dmb-dialog', {is: 'dmb-dialog'});

        document.body.append(dialog);
        dialog.showModal();

        return dialog;
    }

    error(msg) {
        const dialog = document.createElement('dmb-dialog');

        document.body.append(dialog);
        dialog.error(msg);
        dialog.showModal();

        return dialog;
    }

    info(msg) {
        const dialog = document.createElement('dmb-dialog');

        document.body.append(dialog);
        dialog.info(msg);
        dialog.showModal();

        return dialog;
    }

    loader() {
        const dialog = document.createElement('dmb-dialog', {is: 'dmb-dialog'});

        dialog.classList.add('loader');
        document.body.append(dialog);
        dialog.showModal();

        return dialog;
    }

    drawer(content, size = 'small', setCloseButton = true) {
        const dialog = document.createElement('dmb-dialog', {is: 'dmb-dialog'});

        dialog.classList.add('drawer');
        dialog.classList.add(size);
        document.body.append(dialog);

        if (typeof content === 'string') {
            dialog.querySelector('.wrapper').innerHTML = content;
        } else {
            dialog.querySelector('.wrapper').append(content);
        }
        setCloseButton && dialog.setCloseButton();

        dialog.showModal();

        return dialog;
    }

    closeAll() {
        const dialogs = document.querySelectorAll('dmb-dialog');
        const items = dialogs.length;

        if (items) {
            for (let i = 0; i < items; i++) {
                dialogs[i].close('cancelled');
            }
        }

        return true;
    }
}

export const dmbDialogService = new DmbDialogService();