<?php
namespace tests;

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
}
