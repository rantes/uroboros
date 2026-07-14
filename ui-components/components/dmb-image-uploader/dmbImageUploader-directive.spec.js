import { DumboTestApp } from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbImageUploader } from './dmb-image-uploader.directive.js';
import { DmbInput } from '../dmb-input/dmb-input.directive.js';

describe('DmbImageUploader Directive', () => {
    let element = null;
    // eslint-disable-next-line no-unused-vars
    let loadFile = null; // this var is actually used for spy purposes
    let fixture = null;

    const contentType = 'image/png';
    const img1px = 'iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==';

    const dataURLtoFile = (b64Data, contentType='', sliceSize=512) => {
        const byteCharacters = atob(b64Data);
        const byteArrays = [];
    
        for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
            const slice = byteCharacters.slice(offset, offset + sliceSize);
        
            const byteNumbers = new Array(slice.length);
            for (let i = 0; i < slice.length; i++) {
                byteNumbers[i] = slice.charCodeAt(i);
            }
        
            const byteArray = new Uint8Array(byteNumbers);
            byteArrays.push(byteArray);
        }
        const blob = new Blob(byteArrays, {type: contentType});
        return blob;
    };

    const imgFile = dataURLtoFile(img1px, contentType);

    loadFile = function(target) {
        console.log(`trying to send data to ${target}`);
    };

    DumboTestApp.setComponents([
        DmbInput,
        DmbImageUploader
    ]);
    DumboTestApp.createComponent(
        DumboTestApp.fixture(DmbInput)
    );

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbImageUploader);
        element = DumboTestApp.createComponent(fixture);
    });

    afterEach((done) => {
        element && element.remove();
        done();
    });

    it('Should render element', (done) => {
        expect(element).toBeDefined();
        done();
    });

    it('Should check if element was render', (done) => {
        spyOn(element, 'loadFile');
        console.log(element);
        element.querySelector('input[type="file"]').dispatchEvent(new Event('change'));
        expect(element.loadFile).toHaveBeenCalled();
        done();
    });

    it('Should load the image in content preview', (done) => {
        const previewimg = element.querySelector('.preview img');
        previewimg.src = '';
        element.loadFile(imgFile).then(() => {
            expect(previewimg.getAttribute('src')).toBe(`data:image/png;base64,${img1px}`);
            done();
        });
    });
});