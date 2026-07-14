import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

export class DmbClockText extends DumboDirective {
    static selector = 'dmb-clock-text';
    static template = `<span class="hours">{{hoursCount}}(h)</span> :
                        <span class="minutes">{{minutesCount}}(m)</span> :
                        <span class="seconds">{{secondsCount}}(s)</span>`;
    daysCount = '';
    hoursCount = '';
    minutesCount = '';
    secondsCount = '';

    init() {
        let hours = parseInt(this.dataset.hours);
        let mins = parseInt(this.dataset.minutes);
        let secs = parseInt(this.dataset.seconds);
        let days = parseInt(this.dataset.days);
        let pDays = days;
        let pHours = hours;
        let pMinutes = mins;

        this.daysCount = '00'.slice(`${days}`.length) + `${days}`;
        this.hoursCount = '00'.slice(`${hours}`.length) + `${hours}`;
        this.minutesCount = '00'.slice(`${mins}`.length) + `${mins}`;
        this.secondsCount = '00'.slice(`${secs}`.length) + `${secs}`;

        setInterval(() => {
            secs++;
            if (secs > 59) {
                secs = 0;
                mins++;
            }

            if (mins > 59) {
                mins = 0;
                hours++;
            }

            if (hours > 23) {
                hours = 0;
                days++;
            }

            if (days !== pDays) {
                this.daysCount = '00'.slice(`${days}`.length) + `${days}`;
                pDays= days;

            }
            if (pHours !== hours) {
                this.hoursCount = '00'.slice(`${hours}`.length) + `${hours}`;
                pHours = hours;
            }
            if (pMinutes !== mins) {
                this.minutesCount = '00'.slice(`${mins}`.length) + `${mins}`;
                pMinutes = mins;
            }
            this.secondsCount = '00'.slice(`${secs}`.length) + `${secs}`;
        }, 1000);
    }
}
