import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbFooter extends DumboDirective {
    static selector = 'dmb-footer';

    init() {
        let dmbview = undefined;

        if (this.parentNode) {
            dmbview = this.parentNode.querySelector('dmb-content');
        }

        if (dmbview) {
            dmbview.classList.add('padded-footer');
        }
    }
}
