import { DmbDialogService } from '../dmb-dialog/dmbDialog.factory.js';
export class DmbBarCodeReader {
    #_template;

    constructor() {
        this.#_template = `<div class="dmb-bar-code-reader">
            <div class="section group">
                <div class="col col12">
                    <h3 class="title">Active el lector o continue manualmente...</h3>
                </div>
            </div>
            <div class="section group">
                <div class="col col1"></div>
                <div class="col col10">
                    <dmb-button-action
                        service=""
                        class="primary button"
                        args=""
                        behavior="run-service">
                        Manualmente
                    </dmb-button-action>
                </div>
                <div class="col col1"></div>
            </div>
            <dmb-form method="post" action="#" class="reader-form" autocomplete="off" name="reader" async>
                <input class="reader-field" tabindex="1" autocomplete="off" type="text" name="number" autofocus />
                <input class="reader-field" tabindex="2" autocomplete="off" type="text" name="lastname" />
                <input class="reader-field" tabindex="3" autocomplete="off" type="text" name="secondlastname" />
                <input class="reader-field" tabindex="4" autocomplete="off" type="text" name="firstname" />
                <input class="reader-field" tabindex="5" autocomplete="off" type="text" name="secondname" />
                <input class="reader-field" tabindex="6" autocomplete="off" type="text" name="gender" />
                <input class="reader-field" tabindex="7" autocomplete="off" type="text" name="birthdate" />
                <input class="reader-field" tabindex="8" autocomplete="off" type="text" name="blood" />
                <input class="reader-field" tabindex="9" autocomplete="off" type="text" name="field1" />
            </dmb-form>
            </div>`;
    }
/**
 * @todo perform in the proper way, the services to run
 * @param {*} args
 */
    run(args) {
        let button = null;
        let service = 'pedestrianOutService';
        let icon = 'icon-exit';
        let dialog = null;
        let form = null;

        if (args) {
            if (args.inorout === 'in') {
                service = 'pedestrianInService';
                icon = 'icon-enter';
            }
        }

        DmbDialogService.closeAll();
        dialog = DmbDialogService.drawer(this.#_template, 'small', false);
        form = dialog.querySelector('dmb-form');
        button = dialog.querySelector('dmb-button-action');

        button.setAttribute('args', JSON.stringify(args));
        button.classList.add(icon);
        button.setAttribute('service', service);

        setTimeout(() => {
            dialog.querySelector('.reader-field').focus();
        }, 500);

        dialog.querySelector('dmb-button-action').addEventListener('click', () => {
            dialog.close();
        }, {capture: true, passive: true});

        form.submit = () => {
            const obj = {};

            form.getFormData().forEach((x, i) => {
                obj[i] = x.trim();
                if(i === 'number') obj[i] = obj[i].replace(/^0+/gm, '');
            });

            Object.assign(args, obj);

            appServices[`${service}`].run(args);

            dialog.close();
        };
    }
}

export const barCodeReaderService = new DmbBarCodeReader();