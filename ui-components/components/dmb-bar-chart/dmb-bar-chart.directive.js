import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

/**
 * @dmbdoc directive
 * @name DMB.directive:DmbBarChart
 *
 * @description
 * Gráfico de barras SVG generado en JS (CSP-safe: sin inline styles ni
 * innerHTML con datos; la geometría usa atributos de presentación SVG y el
 * color se aplica por clase resuelta en SCSS, igual que dmb-donut-chart).
 *
 * @example
<dmb-bar-chart
    data-labels='["Ene","Feb","Mar"]'
    data-values='[120,90,200]'
    data-color="primary"
    data-unit="%">
</dmb-bar-chart>
 */
export class DmbBarChart extends DumboDirective {
    static selector = 'dmb-bar-chart';
    static template = '<div class="dmb-bar-chart-wrap"></div>';

    #_svgNS = 'http://www.w3.org/2000/svg';
    #_colors = ['primary', 'secondary', 'success', 'error', 'warning', 'information', 'default'];

    init() {
        const labels = this.#_parse(this.dataset.labels);
        const values = this.#_parse(this.dataset.values).map(v => parseFloat(v) || 0);
        const color  = this.#_colors.includes(this.dataset.color) ? this.dataset.color : 'primary';
        const unit   = this.dataset.unit || '';
        const total  = Math.min(labels.length, values.length);

        const wrap = this.querySelector('.dmb-bar-chart-wrap');
        wrap.innerHTML = '';
        if (total === 0) return;

        this.#_render(wrap, labels.slice(0, total), values.slice(0, total), color, unit);
    }

    #_parse(raw) {
        if (!raw) return [];
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    #_el(name, attrs = {}) {
        const node = document.createElementNS(this.#_svgNS, name);
        Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
        return node;
    }

    #_render(wrap, labels, values, color, unit) {
        const barCount = values.length;
        // viewBox dinámico: ancho según el nº de barras (mín. 120), alto fijo.
        const totalWidth  = Math.max(120, barCount * 22);
        const totalHeight = 95;            // más espacio para etiquetas rotadas
        const padX    = 8;                 // padding horizontal a cada lado
        const baseY   = 55;                // eje base
        const labelY  = 70;                // etiquetas de categoría (rotadas -45°)
        const topMargin = 8;               // espacio para los valores sobre la barra más alta
        const plotH   = baseY - topMargin; // altura útil del área de barras
        const usableW = totalWidth - (padX * 2);
        const slotW   = usableW / barCount;
        const barW    = slotW * 0.6;
        const max     = Math.max(0, ...values);

        const svg = this.#_el('svg', {
            viewBox: `0 0 ${totalWidth} ${totalHeight}`,
            class: `chart color-${color}`,
            preserveAspectRatio: 'xMidYMid meet',
        });

        // Eje base
        svg.appendChild(this.#_el('line', {
            class: 'axis', x1: padX, y1: baseY, x2: totalWidth - padX, y2: baseY,
        }));

        for (let i = 0; i < barCount; i++) {
            const value  = values[i];
            const barH   = max > 0 ? (value / max) * plotH : 0;
            const x      = padX + (i * slotW) + ((slotW - barW) / 2);
            const cx     = x + (barW / 2);
            const barTop = baseY - barH;

            svg.appendChild(this.#_el('rect', {
                class: 'bar', x: x, y: barTop, width: barW, height: barH, rx: 0.6,
            }));

            const valueLabel = this.#_el('text', {
                class: 'value', x: cx, y: barTop - 2, 'text-anchor': 'middle',
            });
            valueLabel.textContent = `${this.#_fmt(value)}${unit}`;
            svg.appendChild(valueLabel);

            // Etiqueta del eje X rotada -45° (anclada al final) para evitar solapamiento.
            const catLabel = this.#_el('text', {
                class: 'label', x: cx, y: labelY, 'text-anchor': 'end',
                transform: `rotate(-45, ${cx}, ${labelY})`,
            });
            catLabel.textContent = labels[i];
            svg.appendChild(catLabel);
        }

        wrap.appendChild(svg);
    }

    #_fmt(value) {
        // Enteros sin decimales; decimales con hasta 2 cifras. Separador es_CO.
        return Number.isInteger(value)
            ? value.toLocaleString('es-CO')
            : value.toLocaleString('es-CO', { maximumFractionDigits: 2 });
    }
}
