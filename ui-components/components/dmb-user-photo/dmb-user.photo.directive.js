import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbUserPhoto extends DumboDirective {
    static selector = 'dmb-user-photo';

    init() {
        const inputPic = this.querySelector('input');

        this.addEventListener('click', () => {
            document.querySelector(this.getAttribute('target')).open();
        });

        if(inputPic) {
            this.querySelector('img').addEventListener('load', (e) => {
                inputPic.value = e.target.src;
            });
        }
    }
}
