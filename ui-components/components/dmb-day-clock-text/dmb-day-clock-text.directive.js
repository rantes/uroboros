import { DmbClockText } from "../dmb-clock-text/dmb-clock-text.directive.js";

export class DmbDayClockText extends DmbClockText {
    static selector = 'dmb-day-clock-text';
    static template = '<span class="hours">{{daysCount}}(D)</span> : ' + DmbClockText.template;
}
