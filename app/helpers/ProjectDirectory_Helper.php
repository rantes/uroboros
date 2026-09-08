<?php
/**
 * Helper de directorios de trabajo de un Proyecto. Funciones globales
 * sin namespace — mismo patrón que OperationalShell_Helper.php y
 * HealthMetrics_Helper.php (Controller::LoadHelper() solo hace
 * require_once del archivo, no instancia clase ni inyecta métodos).
 *
 * Bug real corregido: el working_directory de un Proyecto puede
 * crearse indistintamente desde una petición HTTP real (corre como
 * www-data — AdminController::saveprojectAction()/
 * syncconfigfilesAction()) o desde `dumbo run
 * workflow_runner/processpending` vía cron (corre como rantes —
 * RunStepCommandHandler). `mkdir($path, 0755, true)` deja el
 * directorio escribible solo por su dueño — el otro usuario no puede
 * escribir ahí después (confirmado con un `git clone` real fallando
 * con "Permiso denegado" al crear `.git`).
 *
 * Confirmado en ambas direcciones (`id www-data` / `id rantes`):
 * www-data pertenece al grupo `rantes` Y rantes pertenece al grupo
 * `www-data`. Con eso, 0775 (rwxrwxr-x) alcanza sin necesitar un
 * chgrp() explícito — sea quien sea de los dos que cree el
 * directorio, el otro usuario ya es miembro de su grupo por defecto.
 *
 * mkdir($path, 0775, ...) por sí solo NO basta — el modo pasa por el
 * umask del proceso (0022 típico), que lo recorta de vuelta a 0755.
 * chmod() explícito después bypassa el umask.
 *
 * Segundo bug real corregido — un directorio que YA EXISTÍA de antes
 * de este helper (creado por el mkdir(0755) original, previo al fix)
 * nunca se autocorregía: el chmod solo corría dentro del `if
 * (!is_dir($path))`, así que un working_directory preexistente con
 * permisos viejos se quedaba sin escritura de grupo para siempre.
 * Ahora también se intenta chmod() sobre un directorio existente que
 * no es escribible.
 *
 * Ese chmod() puede fallar en silencio (@) por una razón real, no
 * solo teórica: chmod() en Linux solo lo puede hacer el dueño del
 * archivo o root — NUNCA un mero miembro del grupo, sin importar los
 * permisos. Confirmado empíricamente: `rantes` intentando chmod un
 * directorio propiedad de `www-data` falla con "Operación no
 * permitida" (exit 1), aunque rantes sea miembro del grupo www-data.
 * Si RunStepCommandHandler (corre como rantes) encuentra un
 * directorio viejo propiedad de www-data, este chmod() NO puede
 * arreglarlo — is_writable() sigue en false después del intento, y
 * la función lo refleja correctamente en su valor de retorno (nunca
 * asume que el chmod funcionó). El único chmod que sí puede
 * autocorregir ese caso es el que corre como www-data (ej. la
 * próxima vez que se guarda/sincroniza el Proyecto desde la UI) —
 * ver RunStepCommandHandler::Handle() para el mensaje distinto que
 * se le muestra al usuario cuando esto ocurre.
 *
 * Tercer bug real corregido — la condición de disparo del chmod NO
 * puede ser is_writable($path): esa función refleja el acceso del
 * PROCESO ACTUAL, no el modo real del directorio. Confirmado contra
 * el caso real reportado (/var/www/html/uroboros_test/quedicende,
 * propiedad de www-data, modo 0755 — sin bit de escritura de grupo):
 * cuando www-data (el dueño) lo toca, is_writable() YA da true por
 * el bit de dueño (rwx) — el chmod nunca se disparaba, así que el
 * bit de grupo (el que necesita rantes) nunca se agregaba, aunque
 * www-data sí podía haberlo corregido. Se comprueba el bit real de
 * escritura de grupo (S_IWGRP, 0020) en el modo del archivo — así
 * SÍ dispara el chmod incluso cuando el usuario actual ya tiene
 * acceso vía su propio bit de dueño, que es exactamente cuándo hace
 * falta para que el OTRO usuario pueda escribir después.
 */
function ensureWritableProjectDirectory(string $path): bool {
    if (!is_dir($path)):
        @mkdir($path, 0775, true);
    endif;

    if (projectDirectoryMissingGroupWrite($path)):
        @chmod($path, 0775);
        clearstatcache(true, $path);
    endif;

    return is_dir($path) and is_writable($path);
}

/**
 * Expuesta aparte (no solo interna a ensureWritableProjectDirectory())
 * para que el log verboso de orquestación (RunStepCommandHandler::
 * _prepareWorkingDirectory()) pueda mostrar "intentando corregir"
 * exactamente cuando el chmod de arriba SÍ va a dispararse — nunca
 * is_writable($path), por la razón documentada más arriba (refleja
 * el acceso del proceso actual, no el modo real).
 */
function projectDirectoryMissingGroupWrite(string $path): bool {
    return is_dir($path) and !(fileperms($path) & 0020);
}
