import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";
export class DmbInfoPop extends DumboDirective {
    static selector = 'dmb-info-pop';
    static template = '<div class="float-content" transclude></div>';

    init() {
        const size = this.getAttribute('size') || 'small';

        this.querySelector('.float-content').classList.add(`float-content_${size}`);
    }

}
