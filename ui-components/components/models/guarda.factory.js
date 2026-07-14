import { BaseModelClass } from "./base-model.factory.js";

export class GuardaService extends BaseModelClass {

    searchVisitoryById(id) {
        this.url(`/guarda/searchvisitorbyid?term=${id}`);
        return this.getElement(id, true).then((data) => {
            return data;
        });
    }
}