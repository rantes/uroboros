import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
export class DmbEnvInputs extends DumboDirective {
    static selector = 'dmb-env-inputs';
    static template = `
            <section transclude>
            </section>
            <section>
                <div class="section group">
                    <div class="col col6 col6-md col6-sm">
                        &nbsp;
                    </div>
                    <div class="col col6 col6-md col6-sm">
                        <dmb-button
                            class="add-var button button-primary icon icon-plus"
                        >Agregar</dmb-button>
                    </div>
                </div>
            </section>
        `;

    init() {
        let counter = 0;
        let input = null;
        const inputsArea = this.querySelector('section[transclude]');
        const buttonAdd = this.querySelector('dmb-button.add-var');

        if(this.hasAttribute('counter')) {
            counter = parseInt(this.getAttribute('counter'));
        }

        buttonAdd.click(() => {
            input = document.createElement('dmb-env-input');
            input.setAttribute('counter', counter);
            inputsArea.appendChild(input);
            counter++;
        });
    }
}
