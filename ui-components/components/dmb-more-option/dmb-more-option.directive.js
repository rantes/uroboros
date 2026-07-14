import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";
import { DmbDialogService } from '../../components/dmb-dialog/dmb-dialog.factory.js';

/**
 * @dmbdoc directive
 * @name DMB.directive:DmbMoreOption
 *
 * @description
 * Will render a DOM element for more option entry for an action
 *
 * @attribute behavior tells to the component how to perform the action. Valid options: open-panel, launch-url, ajax
 * @attribute url URL to link or to load according the behavior
 * @attribute panel (Optional) Query selector for the panel to open if the behavior is set to open-panel
 *
 * @example
<dmb-more-option
    behavior="open-panel"
    url="/url/to/run/action"
    panel="#panel-to-open">
</dmb-more-option>
 */
export class DmbMoreOption extends DumboDirective {
    static selector = 'dmb-more-option';
    url = '';
    pageLoader = null;
    panel = null;
    behavior = '';
    #_dialog = null;

    constructor() {
        super();
        this.#_dialog = new DmbDialogService();
    }

    init() {
        this.behavior = this.getAttribute('behavior') || '';

        if (!this.behavior.length) {
            throw 'A behavior attribute must to be defined.';
        }

        this.url = this.getAttribute('url') || '';
        this.pageLoader = document.querySelector('#page-loader');
        this.panel = this.getAttribute('panel');

        this.addEventListener('click', e => {
            e.preventDefault();
            this.handleClick();
        });
    }

    handleClick() {
        let panel = null;
        switch (this.behavior) {
        case 'open-panel':
            panel = document.querySelector(this.panel);
            if(this.url) panel.setAttribute('source', this.url);
            panel.open();
            break;
        case 'launch-url':
            location.href = this.url;
            break;
        case 'ajax':
            if(this.pageLoader) this.pageLoader.open();
            fetch(new Request(this.url))
                .then(response => {
                    if(this.pageLoader) this.pageLoader.close();
                    return response.json();
                })
                .then((response) => {
                    this.#_dialog.closeAll();
                    this.#_dialog.info(response.message);
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                })
                .catch(error => {
                    this.#_dialog.closeAll();
                    this.#_dialog.error(error.message);
                });
            break;
        }
    }
}
