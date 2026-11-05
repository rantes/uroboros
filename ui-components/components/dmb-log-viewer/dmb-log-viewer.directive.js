import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

/**
 * Visor de logs estilo terminal (design-guide.md 15). Presentacional: el
 * contenido (un <pre> ya escapado por PHP, avisos de integridad) llega
 * transcluido; aquí solo viven los estilos del componente.
 */
export class DmbLogViewer extends DumboDirective {
    static selector = 'dmb-log-viewer';
    static templateUrl = 'dmb-log-viewer.html';
}
