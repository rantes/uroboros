<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

class testDuplicateProject extends dumboTests {

    private int $_sourceId = 0;

    public function beforeEach(): void {
        $this->_migrateTables([
            'projects',
            'project_groups',
            'project_credentials',
            'project_config_files',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
            'app_users',
        ]);
        $_SERVER['HTTP_x-sf-token'] = 'token';
        $_SESSION['xsfr_token']     = 'token';

        $user = $this->AppUser->Niu([
            'firstname' => 'Test',
            'lastname'  => 'Admin',
            'email'     => 'dup-test@uroboros.local',
            'status'    => 1,
        ]);
        $user->Save();
        $_SESSION['user'] = $user->id;

        $source = $this->Project->Niu([
            'name'              => 'Origen',
            'type'              => 'backend',
            'repository_url'    => 'https://git.example.com/origen.git',
            'working_directory' => '/tmp/origen-no-existe',
            'status'            => 1,
        ]);
        $source->Save() or trigger_error((string) $source->_error, E_USER_ERROR);
        $this->_sourceId = (int) $source->id;
    }

    private function _workflow(string $name, int $projectId, int $chainTo = 0): object {
        $workflow = $this->WorkflowDefinition->Niu([
            'name'                   => $name,
            'project_id'             => $projectId,
            'status'                 => 1,
            'webhook_token'          => bin2hex(random_bytes(16)),
            'workflow_definition_id' => $chainTo,
        ]);
        $workflow->Save() or trigger_error((string) $workflow->_error, E_USER_ERROR);

        return $workflow;
    }

    private function _duplicate(string $newName): object {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['new_name' => $newName];

        return $this->_runAction("/admin/duplicateproject/{$this->_sourceId}");
    }

    public function duplicatesProjectFieldsAndClearsWorkingDirectoryTest(): void {
        $this->describe('Duplicar copia type/repository_url y deja working_directory vacío');

        $result  = $this->_duplicate('Clon');
        $clone   = $this->Project->Find_by_name('Clon');

        $this->assertEquals(HTTP_201, (int) $result->_code);
        $this->assertEquals('backend', $clone->type);
        $this->assertEquals('https://git.example.com/origen.git', $clone->repository_url);
        $this->assertTrue(empty($clone->working_directory), 'working_directory no se copia');
    }

    public function duplicatedSecretsDecryptToSameValueTest(): void {
        $this->describe('Credenciales y archivos duplicados descifran al mismo valor pero con blob distinto');

        $credential = $this->ProjectCredential->Niu(['project_id' => $this->_sourceId, 'name' => 'token', 'value' => 'ghp_secreto_real']);
        $credential->Save() or trigger_error((string) $credential->_error, E_USER_ERROR);
        $file = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_sourceId, 'filename' => '.env', 'format' => 'ini',
            'content' => "A=1\nB=dos\n", 'is_secret' => 1,
        ]);
        $file->Save() or trigger_error((string) $file->_error, E_USER_ERROR);

        $this->_duplicate('Clon');
        $clone     = $this->Project->Find_by_name('Clon');
        $copyCred  = $this->ProjectCredential->Find([':first', 'conditions' => [['project_id', $clone->id]]]);
        $copyFile  = $this->ProjectConfigFile->Find([':first', 'conditions' => [['project_id', $clone->id]]]);
        $origCred  = $this->ProjectCredential->Find((int) $credential->id);
        $origFile  = $this->ProjectConfigFile->Find((int) $file->id);

        $this->assertEquals('ghp_secreto_real', $copyCred->DecryptedValue());
        $this->assertEquals("A=1\nB=dos\n", $copyFile->DecryptedContent());
        $this->assertEquals('.env', $copyFile->filename);
        $this->assertEquals(1, (int) $copyFile->is_secret);
        $this->assertFalse($copyCred->value === $origCred->value, 'El blob cifrado no se copia tal cual');
        $this->assertFalse($copyFile->content === $origFile->content, 'El blob cifrado no se copia tal cual');
    }

    public function chainedWorkflowsAreRemappedTest(): void {
        $this->describe('Workflows encadenados: la copia apunta al Workflow nuevo, con "(copia)", steps y token propio');

        $first  = $this->_workflow('Build', $this->_sourceId);
        $second = $this->_workflow('Deploy', $this->_sourceId, (int) $first->id);
        $step   = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $first->id, 'name' => 'Compilar', 'type' => 'build',
            'command' => 'make "all" && echo <ok>', 'step_order' => 1,
        ]);
        $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);

        $this->_duplicate('Clon');
        $clone      = $this->Project->Find_by_name('Clon');
        $newFirst   = $this->WorkflowDefinition->Find([':first', 'conditions' => [['project_id', $clone->id], ['name', 'Build (copia)']]]);
        $newSecond  = $this->WorkflowDefinition->Find([':first', 'conditions' => [['project_id', $clone->id], ['name', 'Deploy (copia)']]]);
        $newStep    = $this->WorkflowStepDefinition->Find([':first', 'conditions' => [['workflow_definition_id', $newFirst->id]]]);

        $this->assertEquals((int) $newFirst->id, (int) $newSecond->workflow_definition_id, 'Apunta al Workflow nuevo');
        $this->assertTrue((int) $newSecond->workflow_definition_id !== (int) $first->id, 'No apunta al Workflow del Proyecto original');
        $this->assertTrue($newFirst->webhook_token !== $first->webhook_token, 'webhook_token propio');
        $this->assertEquals('Compilar', $newStep->name);
        $this->assertEquals('make "all" && echo <ok>', $newStep->command, 'command sin sanitizar');
        $this->assertEquals(1, (int) $newStep->step_order);
    }

    public function crossProjectChainIsLeftUnchainedTest(): void {
        $this->describe('Una cadena hacia OTRO Proyecto queda sin encadenar y se reporta');

        $other   = $this->Project->Niu(['name' => 'Ajeno', 'type' => 'backend']);
        $other->Save() or trigger_error((string) $other->_error, E_USER_ERROR);
        $foreign = $this->_workflow('Ajeno wf', (int) $other->id);
        $this->_workflow('Propio', $this->_sourceId, (int) $foreign->id);

        $result = $this->_duplicate('Clon');
        $clone  = $this->Project->Find_by_name('Clon');
        $copy   = $this->WorkflowDefinition->Find([':first', 'conditions' => [['project_id', $clone->id]]]);

        $this->assertEquals(HTTP_201, (int) $result->_code);
        $this->assertTrue(empty($copy->workflow_definition_id), 'Sin encadenar');
        $this->assertTrue(
            str_contains($result->_response['message'], '«Propio (copia)» (apuntaba a «Ajeno wf»)'),
            'El mensaje nombra la copia Y el workflow externo al que apuntaba el original'
        );

        // Ninguna referencia hacia el Proyecto ajeno: ni el workflow copiado ni
        // ningún otro del Proyecto nuevo apunta a un workflow que no sea suyo.
        $cloneWorkflows = $this->WorkflowDefinition->Find(['conditions' => [['project_id', $clone->id]]]);
        $this->assertEquals(1, $cloneWorkflows->counter(), 'Solo se copió el workflow propio');
        $referencing = $this->WorkflowDefinition->Find(['conditions' => [['workflow_definition_id', $foreign->id]]]);
        $this->assertEquals(1, $referencing->counter(), 'Solo el original sigue apuntando al ajeno; la copia no');
        $this->assertEquals((int) $other->id, (int) $this->WorkflowDefinition->Find((int) $foreign->id)->project_id, 'El workflow ajeno quedó intacto');
        $this->assertEquals(2, $this->Project->Find(['conditions' => "name IN ('Ajeno','Clon')"])->counter());
    }

    public function crossProjectChainToDeletedWorkflowIsReportedTest(): void {
        $this->describe('Cadena hacia un workflow ya inexistente: queda sin encadenar y el mensaje lo dice');

        $other   = $this->Project->Niu(['name' => 'Ajeno', 'type' => 'backend']);
        $other->Save() or trigger_error((string) $other->_error, E_USER_ERROR);
        $foreign = $this->_workflow('Ajeno wf', (int) $other->id);
        $this->_workflow('Propio', $this->_sourceId, (int) $foreign->id);
        // DELETE crudo: un Delete() del modelo ya desvincula al encadenado (hook), así que no
        // produciría el id colgante que dejan los borrados legados previos a la desvinculación.
        DB->exec("DELETE FROM workflow_definitions WHERE id = {$foreign->id}");

        $result = $this->_duplicate('Clon');

        $this->assertEquals(HTTP_201, (int) $result->_code);
        $this->assertTrue(str_contains($result->_response['message'], '«Propio (copia)» (apuntaba a «#' . (int) $foreign->id . ' (ya no existe)»)'));
    }

    public function secondDuplicationGetsNumberedCopyNamesTest(): void {
        $this->describe('Duplicar dos veces el mismo Proyecto no choca con el nombre único de Workflow');

        $this->_workflow('Build', $this->_sourceId);
        $this->_duplicate('Clon 1');
        $result = $this->_duplicate('Clon 2');
        $clone2 = $this->Project->Find_by_name('Clon 2');
        $copy   = $this->WorkflowDefinition->Find([':first', 'conditions' => [['project_id', $clone2->id]]]);

        $this->assertEquals(HTTP_201, (int) $result->_code);
        $this->assertEquals('Build (copia 2)', $copy->name);
    }

    public function rejectsMissingNameAndUnknownSourceTest(): void {
        $this->describe('Sin nombre → 422; origen inexistente → 404; no se crea nada');

        $before = $this->Project->Find()->counter();
        $noName = $this->_duplicate('   ');
        $this->assertEquals(HTTP_422, (int) $noName->_code);

        $_POST = ['new_name' => 'X'];
        $missing = $this->_runAction('/admin/duplicateproject/9999');
        $this->assertEquals(HTTP_404, (int) $missing->_code);
        $this->assertEquals($before, $this->Project->Find()->counter());
    }

    public function failedDuplicationLeavesNothingBehindTest(): void {
        $this->describe('Un nombre de Proyecto repetido → 422 y no queda ninguna copia a medias');

        $this->_workflow('Build', $this->_sourceId);
        $before = $this->Project->Find()->counter();
        $result = $this->_duplicate('Origen');

        $this->assertEquals(HTTP_422, (int) $result->_code);
        $this->assertEquals($before, $this->Project->Find()->counter());
        $this->assertEquals(1, $this->WorkflowDefinition->Find()->counter(), 'Ningún workflow huérfano');
    }

    public function discardRemovesPartialCopyTest(): void {
        $this->describe('Si falla a mitad (archivo de config ilegible), se borra lo ya creado');

        // Un blob que no descifra hace lanzar DecryptedContent() DESPUÉS de
        // haber creado el Project y sus credenciales.
        $credential = $this->ProjectCredential->Niu(['project_id' => $this->_sourceId, 'name' => 'token', 'value' => 'v']);
        $credential->Save() or trigger_error((string) $credential->_error, E_USER_ERROR);
        $file = $this->ProjectConfigFile->Niu([
            'project_id' => $this->_sourceId, 'filename' => '.env', 'format' => 'ini', 'content' => 'A=1', 'is_secret' => 0,
        ]);
        $file->Save() or trigger_error((string) $file->_error, E_USER_ERROR);
        // Update() es SQL directo (sin hooks): deja un blob con forma válida
        // (iv+tag+ciphertext) pero que no autentica contra la clave real.
        $file->Update([
            'conditions' => "id='" . (int) $file->id . "'",
            'data'       => ['content' => base64_encode(random_bytes(40))],
        ]);

        $before = $this->Project->Find()->counter();
        $result = $this->_duplicate('Clon');

        $this->assertEquals(HTTP_500, (int) $result->_code);
        $this->assertEquals($before, $this->Project->Find()->counter(), 'El Project nuevo se descartó');
        $this->assertEquals(1, $this->ProjectCredential->Find()->counter(), 'La credencial copiada se descartó');
    }

    public function formPanelRendersAndHandlesMissingProjectTest(): void {
        $this->describe('El panel pide el nombre; id inexistente → 404');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $ok = $this->_runAction("/admin/projectduplicateform/{$this->_sourceId}");
        $this->assertEquals(HTTP_200, (int) $ok->_code);

        $missing = $this->_runAction('/admin/projectduplicateform/9999');
        $this->assertEquals('Proyecto no encontrado.', $missing->render['text'] ?? '');
    }
}
