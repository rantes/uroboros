import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";
import { guardaService } from "../../actions/guarda.js";

export class DmbIdReader extends DumboDirective {
    static selector = 'dmb-id-reader';

    init() {
        const launcher = document.querySelector('.launch-id-reader');
        const manualButton = this.querySelector('#pedestrian-in-manually');
        const dialog = this.querySelector('dmb-dialog');
        const form = this.querySelector('dmb-form');
        const firstField = form.querySelector('input[name="number"]');
        const userData = {
            birthdate: '',
            gender: '',
            lastname: '',
            lastname: '',
            number: '',
            secondlastname: '',
            secondname: ''
        };

        launcher.click(() => {
            dialog.open();
            setTimeout(() => {
                firstField.focus();
            }, 500);
        });

        manualButton.click(() => {
            this.#_launchPanelIn(userData);
        });

        form.submit = () => {
            const obj = {};

            form.getFormData().forEach((x, i) => {
                obj[i] = x.trim();
                if(i === 'number') obj[i] = obj[i].replace(/^0+/gm, '');
            });

            Object.assign(userData, obj);
            this.#_launchPanelIn(userData);
        };
    }

    #_launchPanelIn(userData) {
        const panelIn = document.querySelector('dmb-panel#panelin');
        const form = panelIn.querySelector('#form-pedestrian-in');
        const dialog = this.querySelector('dmb-dialog');
        const url = '/guarda/pedestrianin?visitorid=';

        guardaService.searchVisitoryById(userData.number).then((data) => {
            if (data && data.length) {
                panelIn.setAttribute('source', url + data[0].id);
            }
            panelIn.open();
        });

        dialog.close();
    }
}