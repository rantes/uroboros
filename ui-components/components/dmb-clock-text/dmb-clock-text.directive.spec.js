import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbClockText } from './dmb-clock-text.directive.js';

describe('DmbClockText Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbClockText
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbClockText);
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });


    it('Should render element', () => {
        expect(component).toBeDefined();
    });
});