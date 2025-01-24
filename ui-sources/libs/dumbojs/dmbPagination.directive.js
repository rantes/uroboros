import { DumboDirective } from "./dumbo.min.js";

export class DmbPagination extends DumboDirective {
    static selector = 'dmb-pagination';

    init() {
        const formTarget = this.getAttribute('filter-form') || '';
        let form = null;

        this.addEventListener('click', e => {
            const target = e.target;
            const classes = target.getAttribute('class');

            if(formTarget) {
                form = document.querySelector(formTarget);
            }

            if (/paginate-page(-\w+)?/gm.test(classes)) {
                if (form) {
                    e.preventDefault();
                    form.setAttribute('action', e.target.getAttribute('href'));
                    form.submit();
                }
            }
        });
    }
}
