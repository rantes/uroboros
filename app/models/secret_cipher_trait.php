<?php
namespace App\Models;

use Exception;
use DumboPHP\Secrets;

/**
 * Cifrado en reposo (AES-256-GCM) compartido por ProjectCredential y
 * ProjectConfigFile. Formato del blob, sin cambios respecto a las
 * filas existentes: base64(iv[12] + tag[16] + ciphertext), con la
 * clave CONFIG_FILES_ENCRYPTION_KEY leída vía DumboPHP\Secrets.
 *
 * Idempotencia: los hooks before_save corren en CADA Save(), y un
 * Find() + Save() entrega al hook el valor ya cifrado. isEncrypted()
 * lo distingue intentando descifrarlo — un blob solo autentica (tag
 * GCM de 16 bytes) si lo cifró esta misma clave; un texto plano
 * nunca lo hace salvo con probabilidad despreciable. Sin marcador ni
 * migración: funciona con las filas ya guardadas.
 */
trait SecretCipherTrait {
    private const CIPHER_IV_BYTES  = 12; // 96 bits, tamaño recomendado para GCM
    private const CIPHER_TAG_BYTES = 16; // tag GCM real (verificado)

    /**
     * @throws Exception si la clave no está configurada
     */
    public function encryptSecret(string $plain, string $failureMessage): string {
        $key        = $this->_cipherKey();
        $iv         = random_bytes(self::CIPHER_IV_BYTES);
        $tag        = '';
        $ciphertext = ($key === '') ? false : openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        $key === ''
            and throw new Exception('CONFIG_FILES_ENCRYPTION_KEY no está configurada.');
        $ciphertext === false
            and throw new Exception($failureMessage);

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * @throws Exception si la clave falta o el blob no autentica
     */
    public function decryptSecret(string $blob, string $failureMessage): string {
        $plain = $this->_tryDecrypt($blob);

        $this->_cipherKey() === ''
            and throw new Exception('CONFIG_FILES_ENCRYPTION_KEY no está configurada.');
        $plain === null
            and throw new Exception($failureMessage);

        return (string) $plain;
    }

    /**
     * true solo si $value es un blob que descifra con un tag GCM
     * válido. Nunca lanza ni emite warnings: vacío, corto, no-base64
     * o sin clave configurada → false.
     */
    public function isEncrypted(string $value): bool {
        return $this->_tryDecrypt($value) !== null;
    }

    private function _cipherKey(): string {
        return (string) base64_decode((string) (new Secrets())->get('CONFIG_FILES_ENCRYPTION_KEY'));
    }

    /**
     * Descifra sin lanzar: null si no es un blob válido. Todas las
     * guardas van ANTES de openssl_decrypt() — con un iv de longitud
     * incorrecta emitiría un warning en vez de devolver false.
     */
    private function _tryDecrypt(string $blob): ?string {
        $key       = $this->_cipherKey();
        $raw       = base64_decode($blob, true);
        $minLength = self::CIPHER_IV_BYTES + self::CIPHER_TAG_BYTES;
        $plain     = false;

        if ($key !== '' and $raw !== false and strlen($raw) >= $minLength):
            $plain = openssl_decrypt(
                substr($raw, $minLength),
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                substr($raw, 0, self::CIPHER_IV_BYTES),
                substr($raw, self::CIPHER_IV_BYTES, self::CIPHER_TAG_BYTES)
            );
        endif;

        return ($plain === false) ? null : $plain;
    }
}
