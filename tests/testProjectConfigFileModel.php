<?php
namespace tests;

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
}
