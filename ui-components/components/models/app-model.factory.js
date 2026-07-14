import { DmbDialogService } from '../../components/dmb-dialog/dmb-dialog.factory.js';
import { BaseModelClass } from './base-model.factory.js';

class AppModelClass extends BaseModelClass {
    #_dialog = undefined;

    constructor() {
        super();
        this.#_dialog = new DmbDialogService();
    }

    login(data, redirect) {
        const dialog = this.#_dialog.loader();

        this.postToServer(data)
            .then(() => {
            window.location = redirect;
        })
        .catch((e) => {
            dialog.close();
            this.#_dialog.error( e.message || 'Nombre de usuario o contraseña incorrecto.');
        });
    }

    createData(data, redirect) {
        let dialog = this.#_dialog.loader();
        this.postToServer(data, undefined, {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded'})
            .then(() => {
                dialog.close();
                dialog = this.#_dialog.info('Registro creado correctamente');
                if (redirect) {
                    setTimeout(() => {
                        window.location = redirect;
                    }, 1000);
                }
            })
            .catch((e) => {
                dialog.close();
                this.#_dialog.error(e.message || 'Error en el servidor, intente más tarde.');
            });
    }

    updateData(data, redirect) {
        let dialog = this.#_dialog.loader();

        this.updateToServer(data, undefined, {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded'})
            .then(() => {
                dialog.close();
                dialog = this.#_dialog.info('Registro actualizado correctamente');
                if (redirect) {
                    setTimeout(() => {
                        window.location = redirect;
                    }, 1000);
                }
            })
            .catch((e) => {
                dialog.close();
                this.#_dialog.error(e.message || 'Error en el servidor, intente más tarde.');
            });
    }

    deleteData(data, redirect) {
        let dialog = this.#_dialog.loader();

        this.deleteInServer(data)
            .then(() => {
                dialog.close();
                dialog = this.#_dialog.info('Registro borrado correctamente');
                setTimeout(() => {
                    window.location = redirect;
                }, 1000);
            })
            .catch((e) => {
                dialog.close();
                this.#_dialog.error(e.message || 'Error en el servidor, intente más tarde.');
            });
    }
}

export const appModel = new AppModelClass();