import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";
export class DmbSimpleForm extends DumboDirective {
    static selector = 'dmb-simple-form';

    init() {
        const form = this.querySelector('dmb-form');
        const method = this.getAttribute('method') || 'POST';
        const init = {
            method,
            body: null
        };
        const accept = this.getAttribute('accept') || 'json';
        const result = this.getAttribute('result') || 'info';
        const size = this.getAttribute('size') || 'large';
        const noreload = this.hasAttribute('no-reload');
        let fullReload = false;
        let data = null;
        let url = form.getAttribute('action');

        form.callback = (e) => {
            fullReload = this.hasAttribute('full-reload');

            init.method = init.method || form.getAttribute('method');
            init.body = init.method === 'POST' ? form.getFormData() : null;

            if (init.method === 'GET') {
                data = new URLSearchParams(form.getFormData()).toString();
                url = `${url}?${data}`
            }
            const request = new Request(url, init);

            fetch(request)
                .then(async response => {
                    let ret = null;
					let resp = null

                    switch (accept) {
                        case 'html':
                            ret = response.text();
                        break;
                        case 'json':
                        default:
                            ret = response.json();
                        break;
                    }

					if (!response.ok && accept === 'json') {
						resp = await ret;
						throw new Error(resp.message);							
					}

                    return ret;
                })
                .then(resp => {
                    window.dmbDialogService.closeAll();
                    switch (result) {
                        case 'drawer':
                            window.dmbDialogService.drawer(resp, size);
                        break;
                        case 'new-window':
                            window.open(resp.openUrl);
                        break;
						case 'self':
							location.assign(resp.openUrl);
						break;
                        case 'info':
                        default:
                            window.dmbDialogService.info(resp.message);
                        break;
                    }
                })
                .catch(error => {
                    window.dmbDialogService.closeAll();
                    window.dmbDialogService.error(error.message);
                    console.error(error.message);
                }).finally(() => {
                    if (!noreload) {
                        setTimeout(() => {
                            fullReload ? location.replace(location.href) : location.reload();
                        }, 3000);
                    }
                });
        };
    }
}
