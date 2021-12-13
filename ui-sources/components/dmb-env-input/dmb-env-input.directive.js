class DmbEnvInput extends DumboDirective {
    constructor() {
        super();
        const template = `
            <div class="section group">
                <div class="col col5 col5-md col5-sm">
                    <dmb-input
                        label="Key"
                        dmb-value=""
                        dmb-name="env[][key]"
                        validate="required"
                    ></dmb-input>
                </div>
                <div class="col col5 col5-md col5-sm">
                    <dmb-input
                        label="Value"
                        dmb-value=""
                        dmb-name="env[][value]"
                    ></dmb-input>
                </div>
                <div class="col col2 col2-md col2-sm">
                    <div class="col col12">&nbsp;</div>
                    <div class="col col12">
                        <dmb-button class="del-env button button-error icon icon-cross"></dmb-button>
                    </div>
                </div>
            </div>
        `;
        this.setTemplate(template);
    }

    init() {
        let counter = 0;
        const inputs = this.querySelectorAll('dmb-input');
        const buttonDel = this.querySelector('dmb-button.del-env');

        if(this.hasAttribute('counter')) {
            counter = parseInt(this.getAttribute('counter'));
        }

        inputs[0].setAttribute('dmb-name', `env[${counter}][key]`);
        inputs[0].value = this.getAttribute('key');
        inputs[1].setAttribute('dmb-name', `env[${counter}][value]`);
        inputs[1].value = this.getAttribute('val');

        buttonDel.click(() => {
            this.remove();
        });
    }
}

customElements.define('dmb-env-input', DmbEnvInput);