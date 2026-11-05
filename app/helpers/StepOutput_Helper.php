<?php
/**
 * Helper de salida de pasos. Funciones globales sin namespace (mismo
 * patrón que ProjectDirectory_Helper.php). Sin mbstring ni iconv:
 * corte por bytes y saneo UTF-8 con PCRE sin /u.
 */

/**
 * Elimina bytes que no forman UTF-8 válido (secuencias partidas por un
 * corte por bytes o salida binaria). Nunca devuelve false ni null.
 *
 * Camino rápido: si ya es UTF-8 válido se devuelve tal cual. Si no, se
 * sanea en trozos (un solo preg_replace sobre varios MB puede agotar los
 * límites de PCRE); cada trozo termina antes de un byte de continuación
 * para no partir un carácter válido.
 */
function sanitizeUtf8(string $text): string {
    $chunkSize = 65536;
    $length    = strlen($text);
    $valid     = '[\x00-\x7F]+|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]'
               . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]'
               . '|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';
    $clean     = '';
    $offset    = 0;

    if (preg_match('//u', $text) === 1):
        $clean = $text;
    else:
        while ($offset < $length):
            $end = min($offset + $chunkSize, $length);
            // Retroceder (máx. 3 bytes) hasta que el siguiente byte no sea de continuación.
            for ($back = 0; $back < 3 && $end < $length && (ord($text[$end]) & 0xC0) === 0x80; $back++):
                $end--;
            endfor;
            $piece = substr($text, $offset, $end - $offset);
            $done  = preg_replace("/({$valid})|./s", '$1', $piece);
            // Si PCRE falla (límites), degradar a ASCII imprimible: nunca false/''.
            $clean .= $done ?? (string) preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $piece);
            $offset = $end;
        endwhile;
    endif;

    return $clean;
}

/**
 * Corta $text por bytes desde el final y lo sanea (el corte puede caer
 * a mitad de un carácter multibyte: sanitizeUtf8 descarta el fragmento).
 * Resultado <= $bytes y UTF-8 válido.
 */
function tailBytes(string $text, int $bytes): string {
    return sanitizeUtf8($bytes > 0 ? substr($text, -$bytes) : '');
}

/**
 * Si $text excede $max bytes conserva los primeros $head y el final,
 * con un marcador intermedio. Resultado siempre <= $max y UTF-8 válido.
 * Salidas que caben se devuelven intactas (solo saneadas).
 * $max y $head son obligatorios: cada consumidor (archivo de log,
 * extracto, mensajes cortos) define su propio límite.
 */
function truncateStepOutput(string $text, int $max = STEP_LOG_FILE_MAX_BYTES, int $head = STEP_LOG_FILE_HEAD_BYTES): string {
    $total   = strlen($text);
    $reserve = 64; // espacio para el marcador
    $tailLen = max(0, $max - $head - $reserve);
    $omitted = $total - $head - $tailLen;
    $result  = $text;

    if ($total > $max):
        $result = substr($text, 0, $head) . "\n[... {$omitted} bytes omitidos ...]\n" . ($tailLen > 0 ? substr($text, -$tailLen) : '');
    endif;

    return sanitizeUtf8($result);
}
