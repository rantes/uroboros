<?php
namespace tests\fixtures;

use App\Helpers\StepLogStore;

/**
 * Apoyo de tests para los logs en archivo (salida-pasos-en-archivos):
 * la base de tests (STEP_LOG_BASE_PATH) es una carpeta temporal.
 */
class StepLogFixture {
    public StepLogStore $store;

    public function __construct(?string $base = null) {
        $this->store = new StepLogStore($base);
    }

    /** Contenido completo del archivo del paso ('' si no hay archivo). */
    public function read(object $step): string {
        $full = $this->store->resolve($step->output_path);

        return $full === null ? '' : (string) file_get_contents($full);
    }

    /** Borra la carpeta base completa (solo la temporal de tests). */
    public function reset(): void {
        $this->_rm($this->store->base);
    }

    private function _rm(string $path): void {
        if (is_link($path) || is_file($path)):
            @unlink($path);
        elseif (is_dir($path)):
            foreach ((array) scandir($path) as $entry):
                ($entry !== '.' && $entry !== '..') and $this->_rm("{$path}/{$entry}");
            endforeach;
            @rmdir($path);
        endif;
    }
}
