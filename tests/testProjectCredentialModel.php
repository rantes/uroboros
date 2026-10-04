<?php
namespace tests;

use DumboPHP\Secrets;
use DumboPHP\lib\Timothy\dumboTests;

class testProjectCredentialModel extends dumboTests {

    private int $_projectId = 0;

    public function beforeEach(): void {
        $this->_migrateTables(['projects', 'project_credentials']);

        $project = $this->Project->Niu([
            'name' => 'Test Project ' . bin2hex(random_bytes(4)),
            'type' => 'backend',
        ]);
        $project->Save() or die((string) $project->_error);
        $this->_projectId = (int) $project->id;
    }

    public function modelExistTest(): void {
        $this->describe('Should exist the Model');

        $obj = $this->ProjectCredential->Niu();
        $this->assertFalse(empty($obj), 'Assert there is a model instance');
        $this->assertTrue(
            is_a($obj, 'DumboPHP\ActiveRecord'),
            'Assert the instance is an ActiveRecord'
        );
    }

    public function saveValidCredentialTest(): void {
        $this->describe('Should save a valid credential and encrypt its value');

        $obj = $this->ProjectCredential->Niu([
            'project_id' => $this->_projectId,
            'name'       => 'github_token',
            'value'      => 'ghp_realtoken1234567890',
        ]);
        $result = $obj->Save();

        $this->assertTrue($result, 'Save should return true');
        $this->assertNotEmpty($obj->id);
        $this->assertFalse(
            str_contains((string) $obj->value, 'ghp_realtoken1234567890'),
            'value must never be stored as plaintext'
        );
    }

    public function rejectMissingValueTest(): void {
        $this->describe('Should reject a credential without a value');

        $obj = $this->ProjectCredential->Niu([
            'project_id' => $this->_projectId,
            'name'       => 'github_token',
            'value'      => '',
        ]);

        $this->assertFalse($obj->Save(), 'Save should return false when value is empty');
    }

    public function rejectDuplicateNameForSameProjectTest(): void {
        $this->describe('Should reject a duplicate (project_id, name) combination');

        $first = $this->ProjectCredential->Niu([
            'project_id' => $this->_projectId,
            'name'       => 'github_token',
            'value'      => 'token-1',
        ]);
        $first->Save() or die((string) $first->_error);

        $second = $this->ProjectCredential->Niu([
            'project_id' => $this->_projectId,
            'name'       => 'github_token',
            'value'      => 'token-2',
        ]);

        $this->assertFalse($second->Save(), 'Save should return false for a duplicate name in the same project');
        $this->assertTrue(in_array('name', $second->_error->errFields()));
    }

    public function allowSameNameOnDifferentProjectsTest(): void {
        $this->describe('Should allow the same credential name across different projects');

        $otherProject = $this->Project->Niu([
            'name' => 'Other Project ' . bin2hex(random_bytes(4)),
            'type' => 'backend',
        ]);
        $otherProject->Save() or die((string) $otherProject->_error);

        $first = $this->ProjectCredential->Niu([
            'project_id' => $this->_projectId,
            'name'       => 'github_token',
            'value'      => 'token-1',
        ]);
        $first->Save() or die((string) $first->_error);

        $second = $this->ProjectCredential->Niu([
            'project_id' => (int) $otherProject->id,
            'name'       => 'github_token',
            'value'      => 'token-2',
        ]);

        $this->assertTrue($second->Save(), 'Save should succeed — same name, different project');
    }

    public function allowUpdatingWithoutTriggeringItsOwnUniqueCheckTest(): void {
        $this->describe('Updating a record should not collide with itself in the unique check');

        $obj = $this->ProjectCredential->Niu([
            'project_id' => $this->_projectId,
            'name'       => 'github_token',
            'value'      => 'token-1',
        ]);
        $obj->Save() or die((string) $obj->_error);

        $obj->value = 'token-2';
        $this->assertTrue($obj->Save(), 'Re-saving the same record should not fail uniqueness');
    }

    public function decryptedValueRoundtripTest(): void {
        $this->describe('DecryptedValue() should return the original plaintext');

        $plain = 'ghp_realtoken1234567890';
        $obj   = $this->ProjectCredential->Niu([
            'project_id' => $this->_projectId,
            'name'       => 'github_token',
            'value'      => $plain,
        ]);
        $obj->Save() or die((string) $obj->_error);

        $reloaded = $this->ProjectCredential->Find($obj->id);

        $this->assertEquals($plain, $reloaded->DecryptedValue());
    }
    private function _newCredential(string $plain): object {
        $obj = $this->ProjectCredential->Niu(['project_id' => $this->_projectId, 'name' => 'tok_' . bin2hex(random_bytes(3)), 'value' => $plain]);
        $obj->Save() or die((string) $obj->_error);

        return $obj;
    }

    public function createEncryptsExactlyOnceTest(): void {
        $this->describe('Crear con texto plano: se cifra una sola vez y descifra al original');

        $obj    = $this->_newCredential('plano-1');
        $stored = $this->ProjectCredential->Find((int) $obj->id);

        $this->assertTrue($stored->value !== 'plano-1', 'No queda en claro');
        $this->assertTrue($stored->isEncrypted((string) $stored->value), 'Queda como ciphertext válido');
        $this->assertEquals('plano-1', $stored->DecryptedValue(), 'Un solo nivel de cifrado');
    }

    public function findChangeOtherFieldSaveKeepsValueTest(): void {
        $this->describe('Find() + cambiar otro campo + Save(): el valor sigue descifrando al original');

        $obj    = $this->_newCredential('plano-2');
        $loaded = $this->ProjectCredential->Find((int) $obj->id);
        $before = $loaded->value;
        $loaded->name = 'renombrada';

        $this->assertTrue($loaded->Save(), 'Save() debe funcionar');
        $after = $this->ProjectCredential->Find((int) $obj->id);

        $this->assertEquals('plano-2', $after->DecryptedValue());
        $this->assertEquals($before, $after->value, 'El blob no se vuelve a cifrar (ni cambia el IV)');
    }

    public function threeFindSaveCyclesDoNotDegradeTest(): void {
        $this->describe('Tres ciclos Find()+Save() consecutivos sin degradación');

        $obj = $this->_newCredential('plano-3');
        for ($i = 1; $i <= 3; $i++):
            $loaded = $this->ProjectCredential->Find((int) $obj->id);
            $loaded->name = "ciclo{$i}";
            $loaded->Save() or die((string) $loaded->_error);
        endfor;

        $this->assertEquals('plano-3', $this->ProjectCredential->Find((int) $obj->id)->DecryptedValue());
    }

    public function updateWithNewPlaintextEncryptsTheNewValueTest(): void {
        $this->describe('Actualizar con texto plano nuevo: se cifra el valor nuevo');

        $obj    = $this->_newCredential('viejo');
        $loaded = $this->ProjectCredential->Find((int) $obj->id);
        $loaded->value = 'nuevo';
        $loaded->Save() or die((string) $loaded->_error);

        $this->assertEquals('nuevo', $this->ProjectCredential->Find((int) $obj->id)->DecryptedValue());
    }

    public function base64LookingPlaintextIsStillEncryptedTest(): void {
        $this->describe('Texto plano base64 largo que no autentica: se cifra normalmente, no se confunde con ciphertext');

        $lookalike = base64_encode(random_bytes(60)); // forma válida de blob (>= 28 bytes), pero sin tag GCM auténtico
        $obj       = $this->_newCredential($lookalike);
        $stored    = $this->ProjectCredential->Find((int) $obj->id);

        $this->assertTrue($stored->value !== $lookalike, 'Se cifró');
        $this->assertEquals($lookalike, $stored->DecryptedValue(), 'Descifra al texto base64 original');
    }

    public function isEncryptedNeverThrowsOnOddInputTest(): void {
        $this->describe('isEncrypted() con vacío, corto, no-base64 o sin tag válido: false, sin excepción ni warning');

        $obj = $this->ProjectCredential->Niu();

        foreach (['', 'a', 'abc', '!!!no-base64!!!', base64_encode('corto'), base64_encode(random_bytes(27)), base64_encode(random_bytes(28)), base64_encode(random_bytes(200))] as $odd):
            $this->assertFalse($obj->isEncrypted($odd), 'false para: ' . substr($odd, 0, 20));
        endforeach;
    }

    public function legacyBlobStillDecryptsAndIsNotReEncryptedTest(): void {
        $this->describe('Un blob del formato original (iv+tag+ciphertext, mismo algoritmo) sigue descifrando y no se recifra');

        // Cifrado con la implementación original, escrita aquí de forma independiente
        $key = base64_decode((string) (new Secrets())->get('CONFIG_FILES_ENCRYPTION_KEY'));
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt('valor-legado', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        $legacy = base64_encode($iv . $tag . $ct);

        $obj = $this->_newCredential('temporal');
        $obj->Update(['conditions' => "id='" . (int) $obj->id . "'", 'data' => ['value' => $legacy]]);

        $loaded = $this->ProjectCredential->Find((int) $obj->id);
        $this->assertEquals('valor-legado', $loaded->DecryptedValue());
        $loaded->name = 'tocada';
        $loaded->Save() or die((string) $loaded->_error);
        $this->assertEquals($legacy, $this->ProjectCredential->Find((int) $obj->id)->value, 'El blob legado no se toca');
    }
}
