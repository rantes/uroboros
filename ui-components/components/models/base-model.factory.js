import { spinalCord } from "../../libs/app/nerve.js";
import { DmbDialogService } from '../dmb-dialog/dmb-dialog.factory.js';
import { appEvents } from "../../libs/app/configs.js";

export class BaseModelClass {
    #_data;
    #_storage;
    #_url;
    useCache = true;
    defaultHeaders = {
        'Accept': 'application/json'
    };
    dialog = new DmbDialogService();

    constructor() {
        this.#_data = {};
        this.#_storage = window.localStorage;
        this.#_url = '';

        if (this.useCache) {
            spinalCord.subscribe(appEvents.cacheReset.listener, (target) => {
                if (target === 'all') {
                    this.resetAll();
                } else {
                    this.resetElement(target);
                }
            });
        }
    }

    #_buildHeaders(params, method, headers, body = null) {
        const qParams = new URLSearchParams(params).toString();
        const url = `${this.#_url}?${qParams}`;
        const options = {
            method,
            headers: null,
            mode: 'cors',
            cache: 'default',
            body
        };
        const tHeaders = new Headers();
        Object.keys(headers).map((el) => {
            if (el.toLowerCase() === 'content-type' && body instanceof FormData) return;
            tHeaders.set(el, headers[el]);
        });
        options.headers = tHeaders;

        return {options, url};
    }

    url(url = undefined) {
        if (url !== undefined) {
            this.#_url = url;
        }

        return this.#_url;
    }

    resetAll() {
        this.#_data = {};
        this.#_storage.clear();
        return true;
    }

    resetElement(element) {
        this.#_data[element] = [];
        this.#_storage.removeItem(element);
        return true;
    }

    getElement(element, fromCache = true) {
        const dialog = this.dialog.loader();

        return new Promise(resolve => {
            dialog.close(undefined, true);
            if (fromCache) {
                if (this.#_data[element] && this.#_data[element].length) {
                    resolve([...this.#_data[element]]);
                } else {
                    this.setElement(element).then(()=>resolve([...this.#_data[element]]));
                }
            } else {
                return this.getFromServer();
            }
        });
    }

    setElement(element) {
        return new Promise(resolve => {
            this.useCache && (this.#_data[element] = JSON.parse(this.#_storage.getItem(element)));

            if (this.useCache && this.#_data[element] && this.#_data[element].length) {
                resolve(this.#_data[element]);
            } else {
                fetch(new Request(this.#_url))
                    .then(res => res.json())
                    .then(data => {
                        this.useCache && this.#_storage.setItem(element, JSON.stringify(data.d));
                        this.useCache && (this.#_data[element] = data.d);
                        resolve(data.d);
                    });
            }
        });
    }

    getFromServer(params = {}, headers = {}) {
        const { options, url } = this.#_buildHeaders(params, 'GET', headers);
        const request = new Request(url, options);
        let retValue = null;

        return fetch(request)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! Status: ${res.status}`);
                }

                if (res.status === 204) {
                    retValue = new Promise((resolve) => resolve({message: 'No se encontraron registros', d: []}));
                } else {
                    retValue = res.json();
                }
                return retValue;
            });
    }

    /**
     * Token CSRF real, vía el mismo mecanismo x-sf-token:fetch que ya
     * usaba postToServer() — GET/main_controller.php nunca lo exige,
     * pero before_filter() SÍ lo exige para POST/PUT/DELETE
     * (_requiredTokenMethods) y desde el fix de CSRF real (before_filter
     * ahora pone $continue=false en vez de solo marcar 403) cualquier
     * método que mande esos verbos sin este token recibe un 403 real.
     * Bug real corregido — updateToServer()/deleteInServer() nunca
     * llamaban esto, solo postToServer() lo hacía: confirmado con un
     * PUT real a /admin/project_credentials capturado en DevTools,
     * headers=[accept, content-type], sin x-sf-token, 403 real del
     * servidor. Extraído aquí para que los tres verbos que sí lo
     * necesitan (POST/PUT/DELETE) compartan un único punto de origen
     * del token, en vez de repetir la lógica en cada uno.
     */
    #_fetchToken(params, headers) {
        const getData = this.#_buildHeaders(params, 'GET', { 'X-SF-TOKEN': 'fetch' });
        const requestToken = new Request(getData.url, getData.options);

        return fetch(requestToken)
            .then(resToken => {
                if (!resToken.ok) {
                    throw new Error(`HTTP error! Status: ${resToken.status}`);
                }

                const token = resToken.headers.get('X-SF-TOKEN');
                return { ...headers, 'X-SF-TOKEN': token };
            });
    }

    updateToServer(body, params = {}, headers = {}) {
        return this.#_fetchToken(params, headers)
            .then(tokenHeaders => {
                const { options, url } = this.#_buildHeaders(params, 'PUT', tokenHeaders, body);
                return fetch(new Request(url, options));
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! Status: ${res.status}`);
                }

                if (res.status === 204) {
                    return {message: 'No se encontraron registros', d: []};
                }
                return res.json();
            });
    }

    postToServer(body, params = {}, headers = {}) {
        return this.#_fetchToken(params, headers)
            .then(tokenHeaders => {
                const { options, url } = this.#_buildHeaders(params, 'POST', tokenHeaders, body);
                return fetch(url, options);
            })
            .then(res => {

                if (res.status === 204) {
                    return {message: 'No se encontraron registros', d: []};
                }

                return res.json().then(data => {
                    if (!res.ok) {
                        const message = res.status < 500
                            ? (data.message || `Error ${res.status}`)
                            : null;
                        throw new Error(message);
                    }
                    return data;
                });
            });
    }

    deleteInServer(params = {}, headers = {}) {
        return this.#_fetchToken(params, headers)
            .then(tokenHeaders => {
                const { options, url } = this.#_buildHeaders(params, 'DELETE', tokenHeaders);
                return fetch(new Request(url, options));
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! Status: ${res.status}`);
                }

                if (res.status === 204) {
                    return {message: 'No se encontraron registros', d: []};
                }
                return res.json();
            });
    }

}
