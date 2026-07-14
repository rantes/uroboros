import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbQrCode } from './dmb-qr-code.directive.js';

describe('DmbQrCode Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbQrCode
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbQrCode);
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
    });
});