import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbImageUploader extends DumboDirective {
    static selector = 'dmb-image-uploader';
    static templateUrl = './dmb-image-uploader.html';

    init() {
        const dmbimgInput = this.querySelector('dmb-input[type="file"]');
        const imgInput = dmbimgInput.querySelector('input[type="file"]');
        const previewimg = this.querySelector('.preview img');
        
        previewimg.setAttribute('alt', this.getAttribute('img-alt') || 'Image Preview');
        this.hasAttribute('validate') && dmbimgInput.setAttribute('validate', this.getAttribute('validate'));
        this.hasAttribute('dmb-name') && dmbimgInput.setAttribute('dmb-name', this.getAttribute('dmb-name'));
        this.hasAttribute('label') && dmbimgInput.setAttribute('label', this.getAttribute('label'));

        console.log(dmbimgInput, imgInput);
        imgInput.addEventListener('change', (e) => {
            this.loadFile(e.target.files[0]);
        });
    }

    loadFile (file) {
        const previewimg = this.querySelector('.preview img');
        const reader = new FileReader();
        const promise = new Promise((resolve) => {
            reader.onload = () => {
                resolve();
                previewimg.setAttribute('src', reader.result.toString());
            };
        });
        reader.readAsDataURL(file);
        return promise;
    }
}
