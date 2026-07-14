
import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

export class DmbDonutChart extends DumboDirective {
    static selector = 'dmb-donut-chart';
    static template = `<svg viewBox="0 0 36 36" class="chart">
                            <path class="circle-bg"
                                d="M18 2.0845
                                a 15.9155 15.9155 0 0 1 0 31.831
                                a 15.9155 15.9155 0 0 1 0 -31.831"
                            />
                            <path class="circle"
                                d="M18 2.0845
                                a 15.9155 15.9155 0 0 1 0 31.831
                                a 15.9155 15.9155 0 0 1 0 -31.831"
                            />
                            <text x="18" y="20.35" class="percentage">{{percentage}}%</text>
                        </svg>`;
    #_colors = ['red','orange','green','blue'];
    #_colorClass = '';
    percentage = 0;

    init () {
        const chart = this.querySelector('svg.chart');
        const circle = chart.querySelector('path.circle');
        const text = chart.querySelector('text.percentage');
        this.percentage = parseFloat(this.dataset.percent);

        this.#_colorClass = this.#_colors[
            (this.percentage > 15) + (this.percentage > 45) + (this.percentage > 75)
        ];

        circle.setAttribute('stroke-dasharray', `${this.percentage}, 100`);
        chart.classList.add(this.#_colorClass);
    }
}
