import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbSearch extends DumboDirective {
    static selector ='dmb-search';
    static template = '<dmb-form dmb-name="search" method="post" action="#" class="search" enctype="multipart/form-data">' +
                        '<dmb-input class="dmb-input" dmb-name="search[term]"></dmb-input>' +
                        '<dmb-button type="submit" class="primary icon icon-filter"></dmb-button>' +
                        '<dmb-button type="reset" class="error icon icon-cancel-circle" onclick="location.assign(location.href);"></dmb-button>' +
                    '</dmb-form>';

    init() {
        let fields = '';
        let field = '';
        let input = null;
        let newinput = null;
        let form = null;
        let i = 0;
        let value = '';
        let id = null;

        if (!this.hasAttribute('fields')) throw new Error('Fields to include in search must be set.');
        fields = this.getAttribute('fields').trim();
        if (!fields.length) throw new Error('Fields to include in search must be set.');

        form = this.querySelector('form');
        form.setAttribute('action', this.getAttribute('action'));
        this.querySelector('dmb-input').setAttribute('label', this.getAttribute('label'));
        value = this.getAttribute('dmb-value') || '';
        id = this.getAttribute('dmb-id') || null;
        if(id) {
            form.id = id;
        }

        fields = fields.split(',');
        input = document.createElement('input');
        input.setAttribute('type', 'hidden');
        this.querySelector('dmb-input input').value = value;

        while (!!(field = fields.shift())) {
            newinput = input.cloneNode();
            newinput.setAttribute('name', `search[fields][${i}]`);
            newinput.value = field;
            form.prepend(newinput);
            i++;
        }

    }
}
