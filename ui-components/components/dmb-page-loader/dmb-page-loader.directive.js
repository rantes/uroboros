import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";
export class DmbPageLoader extends DumboDirective {
    static selector = 'dmb-page-loader';
    constructor() {
        super();

        window.addEventListener('beforeunload', () => {
            this.classList.add('active');
        });

        document.addEventListener('readystatechange', () => setTimeout(
            () => {
                (document.readyState === 'complete') && this.classList.contains('active') && this.close();
            }, 100
        ));

        document.addEventListener('DOMContentLoaded', () => setTimeout(
            () => {
                this.classList.contains('active') && this.close();
            }, 100
        ));

        window.addEventListener(window.DmbEvents.pageLoaderClose.listener, () => this.close());
        window.addEventListener('load', () => this.close());
    }

    open() {
        !this.classList.contains('active') && this.classList.add('active');
        return true;
    }

    close() {
        this.classList.contains('active') && this.classList.remove('active');
        return true;
    }

}
