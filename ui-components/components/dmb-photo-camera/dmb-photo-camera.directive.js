import { DmbEvents, DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbPhotoCamera extends DumboDirective {
    static selector = 'dmb-photo-camera';
    #_constraints = {
        video: {
            width: 320,
            height: 240,
            playbackRate: 3.0
        },
        audio: false
    };
    #_videoStream
    #_panelContainer
    #_videoContainer

    constructor () {
        super();

        navigator.getUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
    }

    init() {
        this.#_panelContainer = this.closest('dmb-panel');
        this.#_videoContainer = this.querySelector('video');

        this.#_panelContainer.addEventListener(DmbEvents.panelClosed.listener, () => {
            !!this.#_videoStream && this.#_videoStream.stop();
        }, {capture: true, passive: true});

        this.#_panelContainer.addEventListener(DmbEvents.panelOpened.listener, () => {
            try {
                navigator.mediaDevices.getUserMedia && navigator.mediaDevices.getUserMedia(this.#_constraints).then(stream => {
                    try {
                        this.#_videoContainer.srcObject = stream;
                        this.#_videoStream = stream.getTracks()[0];

                        this.#_videoContainer.onloadedmetadata = () => {
                            this.#_videoContainer.play();
                        };
                    } catch (videoError) {
                        this.#_panelContainer.close();
                        console.error(videoError.message);
                    }
                }).catch(mediaError => {
                    this.#_panelContainer.close();
                    console.error('Error on trying to get camera:', mediaError.message);
                });
            } catch (error) {
                this.#_panelContainer.close();
                console.error(error.message);
            }
        }, {capture: true, passive: true});

        this.querySelector('dmb-button').addEventListener('click', () => {
            this.takephoto();
        });

    }

    takephoto() {
        let temp = document.createElement('canvas');
        let tempContext = null;
        let data = '';
        let imgTarget = document.querySelector(this.getAttribute('dmb-target'));

        temp.width = 320;
        temp.height = 240;
        tempContext = temp.getContext('2d');

        tempContext.drawImage(this.#_videoContainer, 0, 0, this.#_videoContainer.offsetWidth, this.#_videoContainer.offsetHeight);
        data = temp.toDataURL('image/png');
        imgTarget.setAttribute('src', data);
        this.#_videoContainer.pause();
        !!this.#_videoStream && this.#_videoStream.stop();
        this.#_panelContainer.close();
    }
}
