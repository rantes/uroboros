import { DmbButtonAction } from '../dmb-button-action/dmb-button-action.directive.js';
/**
 * @dmbdoc directive
 * @name DMB.directive:DmbCard
 *
 * @description
 * This directive only will contains the data, the directive is set to add proper styles and further behaviors.
 *
 * @example
 <dmb-card></dmb-card>
 */
export class DmbCard extends DmbButtonAction {
    static selector = 'dmb-card';

    init() {
        this.classList.contains('with-icon') || this.classList.remove('icon');
        this.classList.contains('with-button') || this.classList.remove('button');
        this.addEventListener('click', () => {
            this.handleClick();
        });
    }
}
