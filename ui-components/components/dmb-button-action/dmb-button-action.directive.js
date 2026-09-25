import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
import { DmbDialogService } from '../dmb-dialog/dmb-dialog.factory.js';
import { appModel } from '../models/app-model.factory.js';

export class DmbButtonAction extends DumboDirective {
    static selector = 'dmb-button-action';
    _action = '';
    icon = '';
    type = null;
    #_dialog = null;
    dataId = null;

    constructor() {
        super();
        this.#_dialog = new DmbDialogService();
    }

    init() {
        this._action = this.getAttribute('action') || '';
        this.classList.add('button');

        switch(this._action) {
            case 'edit':
                this.icon = 'edit';
                break;
            case 'delete':
                this.icon = 'delete';
                break;
            case 'new':
                this.icon = 'add';
                break;
            case 'search':
                this.icon = 'search';
                break;
            case 'execute':
                this.icon = 'play_arrow';
                break;
            case 'upload':
                this.icon = 'cloud_upload';
                break;
        }

        if (this.icon.length) {
            this.setAttribute('icon', this.icon);
        }
        if (this.dataset.id) {
            this.dataId = this.dataset.id;
        }

        this.addEventListener('click', () => {
            this.handleClick();
        });
    }

    handleClick() {
        let panel = null;
        let form = null;
        const url = this.getAttribute('url');
        const target = this.getAttribute('target');
        const formToExec = this.getAttribute('form');
        const pageLoader = document.querySelector('#page-loader');
        const reqParams = {
            method: 'GET'
        };

        switch (this.getAttribute('behavior')) {
            case 'exec-form':
                if(formToExec) {
                    form = document.body.querySelector(formToExec);
                    if(url) form.setAttribute('action', url);
                    if(target) form.setAttribute('target', target);
                    form.submit();
                }
                break;
            case 'open-panel':
                panel = document.body.querySelector(this.getAttribute('panel'));
                if (url) panel.setAttribute('source', url);
                panel.open();
                break;
            case 'launch-url':
                location.href = url;
                break;
            case 'ajax':
                appModel.url = url;
                if(pageLoader) pageLoader.open();
                if (this._action === 'delete') {
                    appModel.deleteData({id: this.dataId}, target);
                } else {
                    appModel.getFromServer().then((data) => {
                        this.#_dialog.info(data.message || 'Acción ejecutada correctamente');
                    }).catch((e) => {
                        if(pageLoader) pageLoader.close();
                        this.#_dialog.error(e.message || 'Error en el servidor, intente más tarde.');
                    });
                }
                break;
            case 'download-file':
                open(url, '_blank');
                break;
            case 'print':
                window.print();
                break;
            case 'go-back':
                history.back();
                break;
        }
    }
}
