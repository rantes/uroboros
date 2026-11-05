<?php
namespace App\Helpers;

use RuntimeException;
use Throwable;

require_once INST_PATH . 'app/helpers/StepOutput_Helper.php';

/**
 * Almacén de la salida completa de los pasos: un archivo por paso en
 * {base}/{workflow_execution_id}/{step_execution_id}.log. La BD guarda
 * solo la ruta RELATIVA, el tamaño, el SHA-256 y un extracto del final.
 *
 * Clase de servicio con instancia (sin métodos estáticos). La base se
 * inyecta en el constructor (tests); por defecto STEP_LOG_BASE_PATH.
 *
 * Seguridad:
 *  - La ruta se construye solo con ids enteros del sistema.
 *  - resolve() valida el formato y exige realpath() dentro de la base
 *    (sin ../ ni symlinks que apunten fuera).
 *  - El checksum detecta alteración o corrupción accidental; NO es una
 *    firma: quien pueda escribir el archivo también podría editar la fila.
 */
class StepLogStore {
    public string $base;

    public function __construct(?string $base = null) {
        $this->base = rtrim($base ?? STEP_LOG_BASE_PATH, '/');
    }

    /**
     * Crea la base si falta (2775) y comprueba que sea escribible.
     * @return array{ready: bool, error: ?string}
     */
    public function ensureBase(): array {
        $error = null;

        is_dir($this->base) or $this->_mkdir($this->base);

        if (!is_dir($this->base)):
            $error = "No existe la carpeta de logs y no se pudo crear: {$this->base}";
        elseif (!is_writable($this->base)):
            $error = "La carpeta de logs no es escribible para el usuario del proceso: {$this->base}";
        endif;

        return ['ready' => $error === null, 'error' => $error];
    }

    /**
     * Escribe el log (ya enmascarado). Nunca lanza hacia el llamador:
     * cualquier fallo vuelve en 'error' y 'path' queda null. 'tail' se
     * llena siempre (con una nota del fallo si lo hubo), <= STEP_OUTPUT_TAIL_BYTES.
     * @return array{path: ?string, size: int, checksum: ?string, tail: string, error: ?string}
     */
    public function write(int $executionId, int $stepId, string $maskedOutput): array {
        $content  = truncateStepOutput($maskedOutput, STEP_LOG_FILE_MAX_BYTES, STEP_LOG_FILE_HEAD_BYTES);
        $result   = ['path' => null, 'size' => 0, 'checksum' => null, 'tail' => '', 'error' => null];
        $relative = "{$executionId}/{$stepId}.log";
        $dir      = "{$this->base}/{$executionId}";
        $tmp      = "{$dir}/.{$stepId}.log." . bin2hex(random_bytes(6)) . '.tmp';
        $tmpMade  = false;

        try {
            $baseState = $this->ensureBase();
            $baseState['ready'] or throw new RuntimeException((string) $baseState['error']);

            is_dir($dir) or $this->_mkdir($dir);
            is_dir($dir) or throw new RuntimeException("No se pudo crear la carpeta {$dir}");

            $tmpMade = true; // desde aquí puede existir un temporal (incluso parcial)
            $bytes = @file_put_contents($tmp, $content);
            $bytes === strlen($content) or throw new RuntimeException($this->_lastError('No se pudo escribir el archivo (¿disco lleno o sin permisos?)'));

            @chmod($tmp, 0664);
            $checksum = hash_file('sha256', $tmp);
            $checksum !== false or throw new RuntimeException('No se pudo calcular el checksum');

            @rename($tmp, "{$this->base}/{$relative}") or throw new RuntimeException($this->_lastError('No se pudo renombrar el archivo temporal'));
            $tmpMade = false;

            $result['path']     = $relative;
            $result['size']     = strlen($content);
            $result['checksum'] = $checksum;
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
        } finally {
            // Limpieza del temporal si algo falló después de crearlo.
            $tmpMade and @unlink($tmp);
        }

        $note             = $result['error'] === null ? '' : 'No se pudo guardar el log completo: ' . substr($result['error'], 0, 300) . "\n";
        $result['tail']   = sanitizeUtf8($note . tailBytes($content, STEP_OUTPUT_TAIL_BYTES - strlen($note)));

        return $result;
    }

    /**
     * Ruta absoluta validada del log, o null. Dos capas: formato estricto
     * "{id}/{id}.log" y realpath() dentro de la base.
     */
    public function resolve(?string $relativePath): ?string {
        $baseReal = realpath($this->base);
        $full     = null;
        $valid    = is_string($relativePath) && preg_match('#^\d+/\d+\.log$#', $relativePath) === 1 && $baseReal !== false;

        if ($valid):
            $real = realpath("{$baseReal}/{$relativePath}");
            ($real !== false && str_starts_with($real, $baseReal . '/') && is_file($real)) and ($full = $real);
        endif;

        return $full;
    }

    /** SHA-256 actual del archivo == checksum guardado. false si no existe. */
    public function verify(?string $relativePath, ?string $checksum): bool {
        $full   = $this->resolve($relativePath);
        $actual = $full === null ? false : hash_file('sha256', $full);

        return $actual !== false && is_string($checksum) && hash_equals($checksum, $actual);
    }

    /**
     * Contenido para la vista, <= STEP_LOG_VIEW_MAX_BYTES: si el archivo es
     * mayor se muestran inicio y final con marcador. null si no hay archivo.
     * Siempre UTF-8 válido (el archivo pudo alterarse); el escape HTML es
     * responsabilidad de la vista.
     */
    public function readForView(?string $relativePath): ?string {
        $full    = $this->resolve($relativePath);
        $max     = STEP_LOG_VIEW_MAX_BYTES;
        $content = null;
        $handle  = $full === null ? false : @fopen($full, 'rb');

        if ($handle !== false):
            $size = (int) filesize($full);
            if ($size <= $max):
                $content = (string) stream_get_contents($handle);
            else:
                $half    = intdiv($max, 2);
                $head    = (string) fread($handle, $half);
                fseek($handle, -$half, SEEK_END);
                $tail    = (string) stream_get_contents($handle);
                $omitted = $size - 2 * $half;
                $content = $head . "\n[... {$omitted} bytes omitidos en esta vista; descarga el log completo ...]\n" . $tail;
            endif;
            fclose($handle);
            $content = sanitizeUtf8($content);
        endif;

        return $content;
    }

    /**
     * Borra el archivo y la carpeta de la ejecución si queda vacía.
     * Un archivo que ya no existe cuenta como borrado.
     */
    public function delete(?string $relativePath): bool {
        $full    = $this->resolve($relativePath);
        $deleted = true;
        $dir     = null;

        $full === null or ($deleted = @unlink($full));
        // Aunque el archivo ya no exista, la carpeta de la ejecución (id
        // validado por formato) puede haber quedado vacía.
        preg_match('#^(\d+)/\d+\.log$#', (string) $relativePath, $parts) === 1 and ($dir = "{$this->base}/{$parts[1]}");
        ($dir !== null && is_dir($dir) && !is_link($dir)) and @rmdir($dir); // falla (y se ignora) si no está vacía

        return $deleted;
    }

    /**
     * Elimina carpetas de ejecuciones que ya no existen en la BD. Hasta
     * $limit carpetas por llamada. $existingIds(array $ids): array recibe
     * ids de ejecución (trozos de 500) y devuelve los que SÍ existen.
     * Solo toca carpetas con nombre numérico (no symlinks) y, dentro,
     * archivos regulares; nada fuera de la base. De las carpetas de
     * ejecuciones que sí existen solo retira las que quedaron vacías.
     * @return int carpetas eliminadas
     */
    public function sweepOrphans(int $limit, callable $existingIds): int {
        $removed = 0;
        $names   = is_dir($this->base) ? (array) scandir($this->base) : [];
        $dirs    = array_values(array_filter($names, fn($name) => ctype_digit((string) $name) && is_dir("{$this->base}/{$name}") && !is_link("{$this->base}/{$name}")));

        foreach (array_chunk($dirs, 500) as $chunk):
            $alive   = array_map('strval', (array) $existingIds(array_map('intval', $chunk)));
            $orphans = array_diff($chunk, $alive);

            // Ejecuciones vivas: solo se retira la carpeta si quedó VACÍA (rmdir no borra contenido).
            foreach (array_intersect($chunk, $alive) as $name):
                @rmdir("{$this->base}/{$name}");
            endforeach;

            foreach ($orphans as $name):
                if ($removed < $limit):
                    try {
                        $this->_removeDir("{$this->base}/{$name}") and $removed++;
                    } catch (Throwable $e) {
                        error_log("StepLogStore: no se pudo barrer la carpeta huérfana {$name}: " . $e->getMessage());
                    }
                endif;
            endforeach;
        endforeach;

        return $removed;
    }

    private function _removeDir(string $dir): bool {
        foreach ((array) scandir($dir) as $entry):
            $path = "{$dir}/{$entry}";
            (is_file($path) || is_link($path)) and @unlink($path);
        endforeach;

        return @rmdir($dir);
    }

    /** mkdir -p con modo 2775 (el mkdir() de PHP pasa por umask y no aplica setgid). */
    private function _mkdir(string $path): void {
        $old = umask(0);
        @mkdir($path, 02775, true);
        umask($old);
        is_dir($path) and @chmod($path, 02775);
    }

    private function _lastError(string $fallback): string {
        $last = error_get_last();

        return $fallback . (empty($last['message']) ? '' : ': ' . $last['message']);
    }
}
