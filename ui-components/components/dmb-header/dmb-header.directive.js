import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbHeader extends DumboDirective {
    static selector = 'dmb-header';

    init() {
        let titleDOM = null;

        if (this.title) {
            titleDOM = document.createElement('h2');
            titleDOM.classList.add('dmb-header-title');
            titleDOM.textContent = this.title;
            
            this.prepend(titleDOM);
        }
    }
}
