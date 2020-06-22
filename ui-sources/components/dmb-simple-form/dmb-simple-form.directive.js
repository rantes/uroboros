/**
 * 
 */
class DmbSimpleForm extends DumboDirective {
    constructor () {
        super();
    }
    
    init() {
        const form = this.querySelector('dmb-form');
        const init = {
            method: 'POST',
            body: null
        };

        let fullReload = false;

        form.callback = () => {
            fullReload = this.hasAttribute('full-reload');

            init.body = form.getFormData();
            init.method = form.getAttribute('method');

            const loginRequest = new Request(form.getAttribute('action'), init);

            fetch(loginRequest)
                .then(response => {
                    response.json().then(resp => {
                        if(response.ok) {

                            window.dmbDialogService.closeAll();
                            window.dmbDialogService.info(resp.message);

                            setTimeout(() => {
                                fullReload ? location.replace(location.href) : location.reload();
                            }, 1500);
                        } else {
                            window.dmbDialogService.closeAll();
                            window.dmbDialogService.error(resp.message);
                        }
                    });
                })
                .catch(error => {
                    window.dmbDialogService.closeAll();
                    window.dmbDialogService.error(error.message);
                });
        };
    }
}

customElements.define('dmb-simple-form', DmbSimpleForm);