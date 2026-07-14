import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
export class DmbPropertyUsersAdd extends DumboDirective {
    static selector = 'dmb-property-users-add';
    #_duplicator;
    #_content;
    counting = 0;

    setCounting() {
        if (isNaN(this.counting)) {
            this.counting = 0;
        }

        if (this.hasAttribute('count')) {
            this.counting = parseInt(this.getAttribute('count')) - 1;
            if (this.counting < 0) {
                this.counting = 0;
            }
        }
    }

    init() {
        const parser = new DOMParser();
        const strings = [
            'Unidad padre',
            'Seleccione',
            'Unidad',
            'Es propietario?',
            'Es residente?',
            'no',
            'si'
        ];
        const userId = parseInt(this.getAttribute('user'));
        this.setCounting();
        this.#_content = `<div class="clone-this col col12">
                <dmb-living-division-select>
                    <dmb-input
                        type="hidden"
                        dmb-value="${userId}"
                        dmb-name="user_property[0][user_id]"
                    >
                    </dmb-input>
                    <div class="section group">
                        <div class="col col12">
                            <dmb-select
                                class="main-unit"
                                label="${strings[0]}"
                                dmb-value="0"
                            >
                            </dmb-select>
                        </div>
                    </div>
                    <div class="section group">
                        <div class="col col12">
                            <dmb-select
                                class="secondary-unit"
                                dmb-name="user_property[0][property_id]"
                                label="${strings[2]}"
                                validate="required"
                                dmb-value=""
                            >
                            </dmb-select>
                        </div>
                    </div>
                </dmb-living-division-select>
                <div class="section group">
                    <div class="col col12">
                        <dmb-select
                            label="${strings[3]}"
                            dmb-name="user_property[0][is_owner]"
                            dmb-value="0"
                            validate="required"
                        >
                            <option value="0">${strings[5]}</option>
                            <option value="1">${strings[6]}</option>
                        </dmb-select>
                    </div>
                    </div>
                    <div class="section group">
                        <div class="col col12">
                            <dmb-select
                                label="${strings[4]}"
                                dmb-name="user_property[0][is_resident]"
                                dmb-value="0"
                                validate="required"
                            >
                                <option value="0">${strings[5]}</option>
                                <option value="1">${strings[6]}</option>
                            </dmb-select>
                        </div>
                    </div>
                    <hr>
                </div>`;
        this.setCounting();

        this.#_duplicator = this.querySelector('.cloner');
        if (this.#_duplicator) {
            this.#_duplicator.addEventListener('click', (e) => {
                const content = parser.parseFromString(this.#_content, 'text/html').body.firstChild;
                const els = content.querySelectorAll("[dmb-name^=user_property]");
                let attribute = '';

                this.counting++;
                this.append(content);
                els.forEach((dom) => {
                    attribute = dom.getAttribute('dmb-name').replace(/\[\d+\]/i, `[${this.counting}]`);
                    dom.setAttribute(
                        'dmb-name',
                        attribute
                    );
                });
                this.setAttribute('count', this.counting);
            });

        } else {
            throw new Error('No clonator found');
        }
    }
}
