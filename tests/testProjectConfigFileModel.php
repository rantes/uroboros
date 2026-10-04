<?php
namespace tests;

use DumboPHP\Secrets;
use DumboPHP\lib\Timothy\dumboTests;

class testProjectConfigFileModel extends dumboTests {

    private int $_projectId = 0;

    public function beforeEach(): void {
        $this->_migrateTables(['projects', 'project_config_files']);

        $project = $this->Project->Niu([
            'name' => 'Test Project ' . bin2hex(random_bytes(4)),
            'type' => 'backend',
        ]);
        $project->Save() or die((string) $project->_error);
        $this->_projectId = (int) $project->id;
    }

    public function modelExistTest(): void {
        $this->describe('Should exist the Model');

        $obj = $this->ProjectConfigFile->Niu();
        $this->assertFalse(empty($obj), 'Assert there is a model instance');
        $this->assertTrue(
            is_a($obj, 'DumboPHP\ActiveRecord'),
            'Assert the instance is an ActiveRecord'
        );
    }

    public function saveValidIniTest(): void {
        $this->describe('Should save a valid INI file and encrypt its content');

        $obj = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => "KEY=value\nOTHER=1",
        ]);
        $result = $obj->Save();

        $this->assertTrue($result, 'Save should return true');
        $this->assertNotEmpty($obj->id);
        $this->assertFalse(
            str_contains((string) $obj->content, 'KEY=value'),
            'content must never be stored as plaintext'
        );
    }

    public function saveValidJsonTest(): void {
        $this->describe('Should save a valid JSON file');

        $obj = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => 'config/database.json',
            'format'     => 'json',
            'content'    => '{"host":"localhost"}',
        ]);

        $this->assertTrue($obj->Save(), 'Save should return true for valid JSON');
    }

    public function saveValidYamlTest(): void {
        $this->describe('Should save a valid YAML file');

        $obj = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => 'config/app.yaml',
            'format'     => 'yaml',
            'content'    => "host: localhost\nport: 8080",
        ]);

        $this->assertTrue($obj->Save(), 'Save should return true for valid YAML');
    }

    public function rejectInvalidIniTest(): void {
        $this->describe('Should reject malformed INI content without saving');

        $obj = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => "[unterminated section",
        ]);

        $this->assertFalse($obj->Save(), 'Save should return false for invalid INI');
        $this->assertTrue(
            in_array('content', $obj->_error->errFields()),
            'content should be flagged as invalid'
        );
    }

    public function rejectInvalidJsonTest(): void {
        $this->describe('Should reject malformed JSON content without saving');

        $obj = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => 'config/database.json',
            'format'     => 'json',
            'content'    => '{"host":',
        ]);

        $this->assertFalse($obj->Save(), 'Save should return false for invalid JSON');
    }

    public function rejectInvalidYamlTest(): void {
        $this->describe('Should reject malformed YAML content without saving');

        $obj = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => 'config/app.yaml',
            'format'     => 'yaml',
            'content'    => "key: [unclosed",
        ]);

        $this->assertFalse($obj->Save(), 'Save should return false for invalid YAML');
    }

    public function rejectInvalidFormatTypeTest(): void {
        $this->describe('Should reject a format outside ini/json/yaml');

        $obj = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => 'config/app.xml',
            'format'     => 'xml',
            'content'    => '<x/>',
        ]);

        $this->assertFalse($obj->Save(), 'Save should return false for unsupported format');
        $this->assertTrue(in_array('format', $obj->_error->errFields()));
    }

    public function rejectDuplicateFilenameForSameProjectTest(): void {
        $this->describe('Should reject a duplicate (project_id, filename) combination');

        $first = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => 'KEY=1',
        ]);
        $first->Save() or die((string) $first->_error);

        $second = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => 'KEY=2',
        ]);

        $this->assertFalse($second->Save(), 'Save should return false for a duplicate filename in the same project');
        $this->assertTrue(in_array('filename', $second->_error->errFields()));
    }

    public function allowSameFilenameOnDifferentProjectsTest(): void {
        $this->describe('Should allow the same filename across different projects');

        $otherProject = $this->Project->Niu([
            'name' => 'Other Project ' . bin2hex(random_bytes(4)),
            'type' => 'backend',
        ]);
        $otherProject->Save() or die((string) $otherProject->_error);

        $first = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => 'KEY=1',
        ]);
        $first->Save() or die((string) $first->_error);

        $second = $this->ProjectConfigFile->Niu([
            'project_id' => (int) $otherProject->id,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => 'KEY=2',
        ]);

        $this->assertTrue($second->Save(), 'Save should succeed — same filename, different project');
    }

    public function allowUpdatingWithoutTriggeringItsOwnUniqueCheckTest(): void {
        $this->describe('Updating a record should not collide with itself in the unique check');

        $obj = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => 'KEY=1',
        ]);
        $obj->Save() or die((string) $obj->_error);

        $obj->content = 'KEY=2';
        $this->assertTrue($obj->Save(), 'Re-saving the same record should not fail uniqueness');
    }

    public function decryptedContentRoundtripTest(): void {
        $this->describe('DecryptedContent() should return the original plaintext');

        $plain = "KEY=value\nOTHER=1";
        $obj   = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_projectId,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => $plain,
        ]);
        $obj->Save() or die((string) $obj->_error);

        $reloaded = $this->ProjectConfigFile->Find($obj->id);

        $this->assertEquals($plain, $reloaded->DecryptedContent());
    }
    private function _newConfigFile(string $plain, string $filename = '.env', string $format = 'ini'): object {
        $obj = $this->ProjectConfigFile->Niu(['project_id' => $this->_projectId, 'filename' => $filename, 'format' => $format, 'content' => $plain]);
        $obj->Save() or die((string) $obj->_error);

        return $obj;
    }

    public function createEncryptsExactlyOnceTest(): void {
        $this->describe('Crear con texto plano: se cifra una sola vez y descifra al original');

        $obj    = $this->_newConfigFile("A=1\nB=2");
        $stored = $this->ProjectConfigFile->Find((int) $obj->id);

        $this->assertTrue($stored->content !== "A=1\nB=2", 'No queda en claro');
        $this->assertTrue($stored->isEncrypted((string) $stored->content), 'Queda como ciphertext válido');
        $this->assertEquals("A=1\nB=2", $stored->DecryptedContent(), 'Un solo nivel de cifrado');
    }

    public function findChangeOtherFieldSaveKeepsContentTest(): void {
        $this->describe('Find() + cambiar otro campo + Save(): el contenido sigue descifrando al original');

        $obj    = $this->_newConfigFile("A=1\nB=2");
        $loaded = $this->ProjectConfigFile->Find((int) $obj->id);
        $before = $loaded->content;
        $loaded->is_secret = 1;

        $this->assertTrue($loaded->Save(), 'Save() debe funcionar: el blob cifrado no debe pasar por validateFormat como texto');
        $after = $this->ProjectConfigFile->Find((int) $obj->id);

        $this->assertEquals("A=1\nB=2", $after->DecryptedContent());
        $this->assertEquals($before, $after->content, 'El blob no se vuelve a cifrar');
        $this->assertEquals(1, (int) $after->is_secret);
    }

    public function findSaveWorksForJsonAndYamlFormatsTest(): void {
        $this->describe('Find() + Save() de un archivo json no se rechaza por validar el blob cifrado como json');

        $obj    = $this->_newConfigFile('{"a":[1,2]}', 'c.json', 'json');
        $loaded = $this->ProjectConfigFile->Find((int) $obj->id);
        $loaded->is_secret = 1;

        $this->assertTrue($loaded->Save(), 'Re-guardar un json cifrado debe funcionar');
        $this->assertEquals('{"a":[1,2]}', $this->ProjectConfigFile->Find((int) $obj->id)->DecryptedContent());
    }

    public function threeFindSaveCyclesDoNotDegradeTest(): void {
        $this->describe('Tres ciclos Find()+Save() consecutivos sin degradación');

        $obj = $this->_newConfigFile("A=1\nB=2");
        for ($i = 1; $i <= 3; $i++):
            $loaded = $this->ProjectConfigFile->Find((int) $obj->id);
            $loaded->is_secret = $i % 2;
            $loaded->Save() or die((string) $loaded->_error);
        endfor;

        $this->assertEquals("A=1\nB=2", $this->ProjectConfigFile->Find((int) $obj->id)->DecryptedContent());
    }

    public function updateWithNewPlaintextEncryptsTheNewContentTest(): void {
        $this->describe('Actualizar con texto plano nuevo: se cifra el contenido nuevo');

        $obj    = $this->_newConfigFile("A=1");
        $loaded = $this->ProjectConfigFile->Find((int) $obj->id);
        $loaded->content = "A=2\nC=3";
        $loaded->Save() or die((string) $loaded->_error);

        $this->assertEquals("A=2\nC=3", $this->ProjectConfigFile->Find((int) $obj->id)->DecryptedContent());
    }

    public function base64LookingPlaintextIsStillEncryptedTest(): void {
        $this->describe('Contenido base64 largo que no autentica: se cifra normalmente, no se confunde con ciphertext');

        $lookalike = json_encode(base64_encode(random_bytes(60)));            // JSON válido
        $bare      = str_replace(['+', '/'], ['x', 'y'], base64_encode(random_bytes(60))); // base64-forma pura como ini
        $asJson    = $this->_newConfigFile($lookalike, 'a.json', 'json');
        $this->assertEquals($lookalike, $this->ProjectConfigFile->Find((int) $asJson->id)->DecryptedContent());

        $this->assertFalse($asJson->isEncrypted($bare), 'Base64 de forma válida pero sin tag auténtico no es ciphertext');
        $asIni = $this->_newConfigFile($bare, 'b.ini', 'ini');
        $this->assertEquals($bare, $this->ProjectConfigFile->Find((int) $asIni->id)->DecryptedContent());
    }

    public function isEncryptedNeverThrowsOnOddInputTest(): void {
        $this->describe('isEncrypted() con vacío, corto, no-base64 o sin tag válido: false, sin excepción ni warning');

        $obj = $this->ProjectConfigFile->Niu();

        foreach (['', 'a', 'abc', '!!!no-base64!!!', base64_encode('corto'), base64_encode(random_bytes(27)), base64_encode(random_bytes(28)), base64_encode(random_bytes(200))] as $odd):
            $this->assertFalse($obj->isEncrypted($odd), 'false para: ' . substr($odd, 0, 20));
        endforeach;
    }

    public function legacyBlobStillDecryptsAndIsNotReEncryptedTest(): void {
        $this->describe('Un blob del formato original sigue descifrando y no se recifra');

        $key = base64_decode((string) (new Secrets())->get('CONFIG_FILES_ENCRYPTION_KEY'));
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt("LEGADO=1\n", 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        $legacy = base64_encode($iv . $tag . $ct);

        $obj = $this->_newConfigFile('X=1');
        $obj->Update(['conditions' => "id='" . (int) $obj->id . "'", 'data' => ['content' => $legacy]]);

        $loaded = $this->ProjectConfigFile->Find((int) $obj->id);
        $this->assertEquals("LEGADO=1\n", $loaded->DecryptedContent());
        $loaded->is_secret = 1;
        $loaded->Save() or die((string) $loaded->_error);
        $this->assertEquals($legacy, $this->ProjectConfigFile->Find((int) $obj->id)->content, 'El blob legado no se toca');
    }
}
