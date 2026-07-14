import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";
export class DmbNotification extends DumboDirective {
    static selector = 'dmb-notification';
    static template = '<i class="icon icon-cancel close-notification-button"></i>' +
        '<dmb-view class="wrapper" transclude>' +
        '</dmb-view>';

    close() {
        this.remove();
        return true;
    }

    init() {
        const icon = this.querySelector('.close-notification-button');
        icon.addEventListener('click', (e) => {
            e.target.closest('dmb-notification').close();
        });
    }
}
