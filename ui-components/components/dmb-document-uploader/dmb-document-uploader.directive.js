import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

/**
 * Captura un archivo (imagen o PDF) y lo codifica como base64 en un input
 * hidden, para que dmb-form/dmb-simple-form lo envíe como campo de texto
 * normal (FormData) en vez de un blob multipart. Mismo patrón de
 * FileReader->dataURL que dmb-photo-camera/dmb-user-photo, generalizado a
 * cualquier tipo de archivo aceptado.
 */
export class DmbDocumentUploader extends DumboDirective {
    static selector = 'dmb-document-uploader';
    static templateUrl = 'dmb-document-uploader.html';

    init() {
        const dmbFileInput = this.querySelector('dmb-input[type="file"]');

        const setup = () => {
            const fileInput = dmbFileInput.querySelector('input[type="file"]');
            const hiddenInput = this.querySelector('input[type="hidden"]');
            const previewImg = this.querySelector('.preview img');
            const filenameLabel = this.querySelector('.preview .filename');

            hiddenInput.setAttribute('name', this.getAttribute('dmb-name') || '');
            this.hasAttribute('label') && dmbFileInput.setAttribute('label', this.getAttribute('label'));
            fileInput.setAttribute('accept', this.getAttribute('accept') || 'image/*,application/pdf');

            fileInput.addEventListener('change', (e) => {
                this.loadFile(e.target.files[0], hiddenInput, previewImg, filenameLabel);
            });
        };

        // dmb-input[type="file"] es otro componente DumboJS con su propio
        // connectedCallback() async (templateUrl → fetch → DOM) — si ya
        // terminó de renderizar (atributo 'rendered'), su <input> real ya
        // existe y podemos configurar de inmediato; si no, esperamos su
        // evento dmb.after-rendered una sola vez en vez de asumir que ya
        // insertó el <input>.
        if (dmbFileInput.hasAttribute('rendered')) {
            setup();
        } else {
            dmbFileInput.addEventListener('dmb.after-rendered', setup, { once: true });
        }
    }

    loadFile(file, hiddenInput, previewImg, filenameLabel) {
        const reader = new FileReader();

        if (file) {
            reader.onload = () => {
                hiddenInput.value = reader.result.toString();
                hiddenInput.dispatchEvent(new Event('change'));
                filenameLabel.textContent = file.name;
                if (file.type.startsWith('image/')) {
                    previewImg.setAttribute('src', reader.result.toString());
                    previewImg.removeAttribute('hidden');
                } else {
                    previewImg.setAttribute('hidden', '');
                }
            };
            reader.readAsDataURL(file);
        }
    }
}
