/**
 * Component handle login
 */

 class DmbLogin extends DumboDirective {
    #_form;

    constructor() {
        super();

        const template = '<dmb-form dmb-name="login" method="post" action="?" autocomplete="off" class="login" async>' +
                            '<dmb-input class="dmb-input" label="" validate="required" dmb-name="u" dmb-id="email"></dmb-input>' +
                            '<dmb-input class="dmb-input" label="" type="password" dmb-name="p" autocomplete="off" validate="required" dmb-id="password"></dmb-input>' +
                            '<dmb-button type="submit" class="button button-primary" id="login-button"></dmb-button>' +
                        '</dmb-form>';
        this.setTemplate(template);
        this.valids = [];
        this.#_form = null;
    }

    init() {
        const button = this.querySelector('dmb-button');
        const inputs = this.querySelectorAll('dmb-input');
        const target = this.getAttribute('target');
        let areaSelector = null;

        this.#_form = this.querySelector('dmb-form');
        inputs[0].setAttribute('label', this.getAttribute('user-label') || '');
        inputs[1].setAttribute('label', this.getAttribute('pass-label') || '');

        this.#_form.setAttribute('action', `${target}signin`);
        this.#_form.setAttribute('redirect', `${target}index`);
        this.#_form.callback = this.#_handleLogin;
        button.innerText = this.getAttribute('button-label') || '';
    }
    /**
     * Send fields to backend for login process
     */
    #_handleLogin() {
        const target = this.getAttribute('action');
        const redirect = this.getAttribute('redirect');
        const init = {
            method: 'POST',
            body: this.getFormData()
        };
        const loginRequest = new Request(target, init);

        fetch(loginRequest)
            .then(response => {
                if (!response.ok) throw new Error('Usuario o password incorrecto');
                return response.json();
            })
            .then(() => {
                window.location = redirect;
            })
            .catch(error => {
                window.dmbDialogService.error(error);
            });
    }
}

customElements.define('dmb-login', DmbLogin);