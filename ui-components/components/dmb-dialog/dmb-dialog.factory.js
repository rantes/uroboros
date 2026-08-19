import { DmbEvents } from "../../libs/dumbojs/dumbo.min.js";

const LOADER_SVG = `<svg width="120" height="120" viewBox="0 0 120 120" role="img" xmlns="http://www.w3.org/2000/svg" aria-label="Cargando">
  <circle class="loader-ring-track" cx="60" cy="60" r="48"/>
  <circle class="loader-ring-spin"  cx="60" cy="60" r="48"/>
  <circle class="loader-ring-accent" cx="60" cy="60" r="48"/>
  <g transform="translate(38,108)">
    <circle class="loader-dot" cx="0"  cy="0" r="3"/>
    <circle class="loader-dot" cx="11" cy="0" r="3"/>
    <circle class="loader-dot" cx="22" cy="0" r="3"/>
  </g>
</svg>`;

export class DmbDialogService {

    /**
     * connectedCallback() de dmb-dialog es async (templateUrl se
     * carga por fetch) — document.body.append(dialog) no espera a
     * que .wrapper exista. Sin esto, cualquier llamada síncrona
     * inmediatamente después de append() (loader/error/info/drawer)
     * falla con "Cannot set properties of null (setting
     * 'innerHTML')" — el mismo patrón de carrera ya documentado en
     * .claude/rules/dumbochromedriver.md, hallazgo 2. Se detectó real
     * al guardar un formulario (dmb-simple-form -> appModel.createData
     * -> DmbDialogService.loader()), no solo en teoría.
     */
    #afterRendered(dialog, fn) {
        if (dialog.hasAttribute('rendered')) {
            fn();
        } else {
            dialog.addEventListener(DmbEvents.afterRendered.listener, fn, {once: true});
        }
    }

    setMessage(dialog, msg) {
        const message = document.createElement('span');

        message.classList.add('message');
        message.textContent = msg;
        dialog.append(message);

        return true;
    }

    open() {
        const dialog = document.createElement('dmb-dialog');

        document.body.append(dialog);
        this.#afterRendered(dialog, () => dialog.showModal());

        return dialog;
    }

    error(msg) {
        const dialog = document.createElement('dmb-dialog');

        document.body.append(dialog);
        this.#afterRendered(dialog, () => {
            dialog.error(msg);
            dialog.showModal();
        });

        return dialog;
    }

    info(msg) {
        const dialog = document.createElement('dmb-dialog');

        document.body.append(dialog);
        this.#afterRendered(dialog, () => {
            dialog.info(msg);
            dialog.showModal();
        });

        return dialog;
    }

    loader() {
        const dialog = document.createElement('dmb-dialog');

        dialog.classList.add('loader');
        document.body.append(dialog);
        this.#afterRendered(dialog, () => {
            dialog.querySelector('.wrapper').innerHTML = LOADER_SVG;
            dialog.showModal();
        });

        return dialog;
    }

    drawer(content, size = 'small', setCloseButton = true) {
        const dialog = document.createElement('dmb-dialog');

        dialog.classList.add('drawer');
        dialog.classList.add(size);
        document.body.append(dialog);

        this.#afterRendered(dialog, () => {
            if (typeof content === 'string') {
                dialog.querySelector('.wrapper').innerHTML = content;
            } else {
                dialog.querySelector('.wrapper').append(content);
            }
            setCloseButton && dialog.setCloseButton();

            dialog.showModal();
        });

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
