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

    /**
     * Manejo de respuesta COMÚN de las lecturas GET (getFromServer y setElement): un status no-ok
     * rechaza; 204 (sin cuerpo, p. ej. "sin resultados") se normaliza a {message, d: []} en vez de
     * intentar parsear JSON de un cuerpo vacío ("Unexpected end of JSON input").
     */
    #_handleResponse(res) {
        if (!res.ok) {
            throw new Error(`HTTP error! Status: ${res.status}`);
        }

        return res.status === 204
            ? Promise.resolve({message: 'No se encontraron registros', d: []})
            : res.json();
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

        return new Promise((resolve, reject) => {
            dialog.close(undefined, true);
            if (fromCache) {
                if (this.#_data[element] && this.#_data[element].length) {
                    resolve([...this.#_data[element]]);
                } else {
                    this.setElement(element).then(()=>resolve([...this.#_data[element]])).catch(reject);
                }
            } else {
                return this.getFromServer();
            }
        });
    }

    setElement(element) {
        return new Promise((resolve, reject) => {
            this.useCache && (this.#_data[element] = JSON.parse(this.#_storage.getItem(element)));

            if (this.useCache && this.#_data[element] && this.#_data[element].length) {
                resolve(this.#_data[element]);
            } else {
                // La URL ya viene armada (con su query) por quien llama a url(): no se pasa por
                // #_buildHeaders (que le añadiría un "?" extra); sí comparte el manejo de respuesta.
                fetch(new Request(this.#_url))
                    .then(res => this.#_handleResponse(res))
                    .then(data => {
                        const items = data.d ?? [];

                        // Un resultado vacío (204 o lista vacía) no se persiste: no envenena la caché
                        // (que de todos modos solo se usa con length > 0) y la próxima llamada reintenta.
                        this.useCache && items.length && this.#_storage.setItem(element, JSON.stringify(items));
                        this.useCache && (this.#_data[element] = items);
                        resolve(items);
                    })
                    .catch(reject);
            }
        });
    }

    getFromServer(params = {}, headers = {}) {
        const { options, url } = this.#_buildHeaders(params, 'GET', headers);
        const request = new Request(url, options);

        return fetch(request).then(res => this.#_handleResponse(res));
    }

    updateToServer(body, params = {}, headers = {}) {
        const getData = this.#_buildHeaders(params, 'GET', { 'X-SF-TOKEN': 'fetch' });
        const requestToken = new Request(getData.url, getData.options);

        return fetch(requestToken)
            .then(resToken => {
                if (!resToken.ok) {
                    throw new Error(`HTTP error! Status: ${resToken.status}`);
                }

                const token = resToken.headers.get('X-SF-TOKEN');
                const putHeaders = { ...headers, 'X-SF-TOKEN': token };
                const { options, url } = this.#_buildHeaders(params, 'PUT', putHeaders, body);
                
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

    postToServer(body, params = {}, headers = {}) {
        const getData = this.#_buildHeaders(params, 'GET', { 'X-SF-TOKEN': 'fetch' });
        const requestToken = new Request(getData.url, getData.options);
        let retValue = null;

        return fetch(requestToken)
            .then(resToken => {
                if (!resToken.ok) {
                    throw new Error(`HTTP error! Status: ${resToken.status}`);
                }

                const token = resToken.headers.get('X-SF-TOKEN');
                const postHeaders = { ...headers, 'X-SF-TOKEN': token };
                const { options, url } = this.#_buildHeaders(params, 'POST', postHeaders, body);
                
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
        console.log('deleteInServer', params, this.#_url);
        const getData = this.#_buildHeaders(params, 'GET', { 'X-SF-TOKEN': 'fetch' });
        const requestToken = new Request(getData.url, getData.options);

        return fetch(requestToken)
            .then(resToken => {
                if (!resToken.ok) {
                    throw new Error(`HTTP error! Status: ${resToken.status}`);
                }

                const token = resToken.headers.get('X-SF-TOKEN');
                const delHeaders = { ...headers, 'X-SF-TOKEN': token };
                const { options, url } = this.#_buildHeaders(params, 'DELETE', delHeaders);
                
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

}
