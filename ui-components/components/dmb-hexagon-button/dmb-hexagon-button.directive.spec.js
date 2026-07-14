import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbHexagonButton } from './dmb-hexagon-button.directive.js';

describe('DmbHexagonButton Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbHexagonButton
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbHexagonButton);
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