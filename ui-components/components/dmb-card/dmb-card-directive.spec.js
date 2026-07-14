import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbCard } from './dmb-card.directive.js';

describe('DmbCard Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbCard
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbCard);
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