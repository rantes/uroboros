import { DmbEvents, DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

import { propertiesService } from '../models/living-units.factory.js';

export class DmbLivingDivisionSelect extends DumboDirective {
    static selector = 'dmb-living-division-select';
    #_mainUnitSelector;
    #_secondarySelector;

    init() {
        this.#_mainUnitSelector = this.querySelector('.main-unit');
        this.#_secondarySelector = this.querySelector('.secondary-unit');

        this.#_mainUnitSelector.addEventListener(DmbEvents.afterRendered.listener, (e) => {
            const mainSelect = e.target.querySelector('select');

            if (mainSelect.options.length === 0) {
                propertiesService.getPrimaryUnits().then(data => {
                    data.unshift({value: '', text: 'Seleccione...'});
                    this.#_mainUnitSelector.values = data;
                });
            }
        });

        this.#_mainUnitSelector.addEventListener('change', (e) => {
            propertiesService.getChildren(e.target.value).then(data => {
                data.unshift({value: '', text: 'Seleccione...'});
                this.#_secondarySelector.values = data;
            });
        }, {capture: true, passive: true});

    }

}
