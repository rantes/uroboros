import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbVideoUploader extends DumboDirective {
    static selector = 'dmb-video-uploader';
    static templateUrl = './dmb-video-uploader.html';

    init() {
        const dmbVideoInput = this.querySelector('dmb-input[type="file"]');
        const videoInput = dmbVideoInput.querySelector('input[type="file"]');
        const willPreview = this.hasAttribute('preview') && !!this.getAttribute('preview').length;

        this.hasAttribute('validate') && dmbVideoInput.setAttribute('validate', this.getAttribute('validate'));
        this.hasAttribute('dmb-name') && dmbVideoInput.setAttribute('dmb-name', this.getAttribute('dmb-name'));
        this.hasAttribute('label') && dmbVideoInput.setAttribute('label', this.getAttribute('label'));

        willPreview && videoInput.addEventListener('change', (e) => {
            this.loadFile(e.target.files[0]);
        });

        willPreview || this.querySelector('.preview video').remove();
    }
    
    loadFile (file) {
        const videoComponent = this.querySelector('.preview video');
        const reader = new FileReader();
        const promise = new Promise((resolve) => {
            reader.onload = () => {
                resolve();
                videoComponent.setAttribute('src', reader.result.toString());
            };
        });
        reader.readAsDataURL(file);
        return promise;
    }
}
