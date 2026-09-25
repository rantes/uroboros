// import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";
import { DmbButtonAction } from "../../components/dmb-button-action/dmb-button-action.directive.js";
import { DmbDialogService } from '../../components/dmb-dialog/dmb-dialog.factory.js';
import { appModel } from '../models/app-model.factory.js';

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
export class DmbMoreOption extends DmbButtonAction {
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
        this._action = this.getAttribute('action') || '';

        if (this.dataset.id) {
            this.dataId = this.dataset.id;
        }

        this.addEventListener('click', () => {
            this.handleClick();
        });
    }

}
