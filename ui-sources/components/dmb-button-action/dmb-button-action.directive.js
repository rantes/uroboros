class DmbButtonAction extends DumboDirective {
    constructor() {
        super();
    }

    init() {
        const action = this.getAttribute('action') || '';
        let icon = '';

        this.classList.add('icon');
        this.classList.add('button');

        switch(action) {
            case 'edit':
                icon = 'icon-edit-pencil';
                break;
            case 'delete':
                icon = 'icon-trashcan';
                break;
            case 'new':
                icon = 'icon-plus1';
                break;
            case 'search':
                icon = 'icon-filter';
                break;
            case 'execute':
                icon = 'icon-chevron-right';
                break;
            case 'attachment':
                let type = this.getAttribute('file-type') || 'text2';
                icon = `icon-file-${type}`;
                break;
        }

		if (this.hasAttribute('icon')) {
			icon = (`${icon} `+this.getAttribute('icon')).trim();
		}

        if (icon.length) {
            this.classList.add(icon);
        }

        this.addEventListener('click', () => {
            this.handleClick();
        }, {capture: true, passive: true});
    }

    handleClick() {
        let panel = null;
        let form = null;
        const url = this.getAttribute('url');
        const target = this.getAttribute('target');
        const formToExec = this.getAttribute('form');
        const pageLoader = document.querySelector('#page-loader');
        const service = this.getAttribute('service');
		const init = {
            method: 'GET',
            body: null
        };

        switch (this.getAttribute('behavior')) {
            case 'exec-form':
                if(formToExec) {
                    form = document.querySelector(formToExec);
                    if(url) form.setAttribute('action', url);
                    if(target) form.setAttribute('target', target);
                    form.submit();
                }
            break;
            case 'open-panel':
                panel = document.querySelector(this.getAttribute('panel'));
                if(url) panel.setAttribute('source', url);
                panel.open();
            break;
            case 'launch-url':
                location.href = url;
            break;
            case 'run-service':
                window[`${service}`].run(JSON.parse(this.getAttribute('args')));
            break;
            case 'ajax':

                if (pageLoader) pageLoader.open();
				if (this.hasAttribute('method')) init.method = this.getAttribute('method');
				if (init.method === 'POST' || init.method === 'PUT') init.body = this.getAttribute('body');

                fetch(new Request(url, init))
                    .then(async response => {
						let ret = null;
						let resp = null;

						ret = response.json();

						if (!response.ok) {
							resp = await ret;
							throw resp.message;
						}

                        return ret;
                    })
                    .then(() => {
                        window.location.reload();
                    })
                    .catch(error => {
                        window.dmbDialogService.closeAll();
                        window.dmbDialogService.error(error);
						console.error(error);
                    });
            break;
        }
    }
}

customElements.define('dmb-button-action', DmbButtonAction);