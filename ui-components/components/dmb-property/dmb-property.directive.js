import { DmbButtonAction } from '../dmb-button-action/dmb-button-action.directive.js';

export class DmbProperty extends DmbButtonAction {
    static selector = 'dmb-property';

    init() {
        super.init();
        if (this.classList.contains('icon')) {
            this.classList.remove('icon');
        }
        if (this.classList.contains('button')) {
            this.classList.remove('button');
        }
    }
}
