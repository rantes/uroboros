import { endpoints } from '../../libs/app/configs.js';
import { BaseModelClass } from './base-model.factory.js';

class LivingUnitsModel extends BaseModelClass {

    getPrimaryUnits() {
        this.url(`${endpoints.livingDivisions}?parent=0`);
        return this.getElement('primaryProperties');
    }

    getAll() {
        this.url(endpoints.livingDivisions);
        return this.getElement('allProperties');
    }

    getChildren(parent) {
        this.url(`${endpoints.livingDivisions}?parent=${parent}`);
        return this.getElement(`childrenProperties-${parent}`);
    }

    getResidents(livingUnit) {
        this.url(`${endpoints.residents}?unit=${livingUnit}`);
        return this.getElement(`unitResidents-${livingUnit}`);
    }
}

export const propertiesService = new LivingUnitsModel();