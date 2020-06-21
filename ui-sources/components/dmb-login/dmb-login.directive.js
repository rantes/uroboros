/**
 * Component handle login 
 */ 

class DmbLogin extends DumboDirective {
    constructor() {
        super();

        const template = '<dmb-form dmb-name="login" method="post" action="?" class="login" async>' +
                            '<dmb-input class="dmb-input" label="User" validate="required" dmb-name="e" dmb-id="email"></dmb-input>' +
                            '<dmb-input class="dmb-input" label="Password" type="password" dmb-name="p" autocomplete="off" validate="required" dmb-id="password"></dmb-input>' +
                            '<dmb-button class="button button-primary" id="login-button">Ingresar</dmb-button>' +
                        '</dmb-form>';
        this.setTemplate(template);
        this.valids = [];
        this.form = null;
    }

    init() {
        const button = this.querySelector('dmb-button');
        this.form = this.querySelector('dmb-form');

        this.form.setAttribute('action', `${this.getAttribute('target')}signin`);
        button.click((e) => {
            e.preventDefault(e);
            this.loginClick();
        });
    }

    /**
     * * Reset and set validations, and send fields content to target on click
     */
    loginClick() {

        const canSend = this.form.submit();

        if (canSend) {
            this.handleLogin(this.getAttribute('target'));
        }
    }

    /**
     * Send fields to backend for login process
     * @param target string - target controler for login, and redirect
     */
    handleLogin(target) {
        const init = {
            method: 'POST',
            body: this.form.getFormData()
        };
        const loginRequest = new Request(`${target}signin`, init);

        fetch(loginRequest)
            .then(response => {
                if (!response.ok) throw new Error('Usuario o password incorrecto');
                return response.json();
            })
            .then(() => {
                window.location = `${target}index`;
            })
            .catch(error => {
                window.dmbDialogService.error(error);
            });
    }
}

customElements.define('dmb-login', DmbLogin);