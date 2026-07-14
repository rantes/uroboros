import { DmbButtonAction } from '../dmb-button-action/dmb-button-action.directive.js';

export class DmbHexagonButton extends DmbButtonAction {
    static selector = 'dmb-hexagon-button';

    init() {
        this.classList.remove('icon');
        this.classList.remove('button');
        this.addEventListener('click', () => {
            this.handleClick();
        });
    }

}
