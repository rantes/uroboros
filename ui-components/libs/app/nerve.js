/**
 * Event Handler Redux-like
 */
class Spinal {
    #_events

    constructor() {
        this.#_events = new Map();
    }
    /**
     *
     * @param {string} event Event to subscribe for
     * @param {function} callback Method to run when event is triggered
     */
    subscribe(event, callback) {
        let eventActions = [];

        if (this.#_events.has(event)) {
            eventActions = this.#_events.get(event);
        }
        eventActions.push(callback);
        this.#_events.set(event, eventActions);
    }
    /**
     *
     * @param {string} event Event to trigger
     * @param {Array} args
     */
    dispatch(event, args) {
        let callbacks = [];
        let callback = undefined;

        if (this.#_events.has(event)) {
            callbacks = this.#_events.get(event);
        }

        while(undefined !== (callback = callbacks.shift())) {
            callback.call(this, ...args);
        }
    }
}

export const spinalCord = new Spinal();