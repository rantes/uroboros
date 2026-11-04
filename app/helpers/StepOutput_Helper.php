<?php
/**
 * Helper de salida de pasos. Funciones globales sin namespace (mismo
 * patrón que ProjectDirectory_Helper.php). Sin mbstring ni iconv:
 * corte por bytes y saneo UTF-8 con PCRE sin /u.
 */

/**
 * Elimina bytes que no forman UTF-8 válido (secuencias partidas por un
 * corte por bytes o salida binaria). Nunca devuelve false ni null.
 */
function sanitizeUtf8(string $text): string {
    $valid = '[\x00-\x7F]+|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]'
           . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]'
           . '|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';
    $clean = preg_replace("/({$valid})|./s", '$1', $text);

    // Si PCRE falla (límites), degradar a ASCII imprimible: nunca false/''.
    return $clean ?? (string) preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $text);
}

/**
 * Si $text excede $max bytes conserva los primeros $head y el final,
 * con un marcador intermedio. Resultado siempre <= $max y UTF-8 válido.
 * Salidas que caben se devuelven intactas.
 */
function truncateStepOutput(string $text, int $max = STEP_OUTPUT_MAX_BYTES, int $head = STEP_OUTPUT_HEAD_BYTES): string {
    $total   = strlen($text);
    $reserve = 64; // espacio para el marcador
    $tailLen = $max - $head - $reserve;
    $omitted = $total - $head - $tailLen;
    $marker  = "\n[... {$omitted} bytes omitidos ...]\n";
    $cut     = substr($text, 0, $head) . $marker . substr($text, -$tailLen);

    return sanitizeUtf8($total > $max ? $cut : $text);
}
