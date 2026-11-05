<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;
use App\Commands\ExecuteWorkflowCommand;
use App\Commands\RunStepCommand;
use App\CommandHandlers\RunStepCommandHandler;
use App\Buses\CommandBus;
use App\Helpers\StepLogStore;
use tests\fixtures\StepLogFixture;

require_once INST_PATH . 'app/helpers/StepOutput_Helper.php';

/**
 * salida-pasos-en-archivos — StepLogStore, integración con el handler,
 * vistas de log y retención. STEP_LOG_BASE_PATH apunta a una carpeta
 * temporal en tests (config/host.php); nada toca los logs de dev.
 */
class testStepLogStore extends dumboTests {
    private ?StepLogFixture $fixture = null;

    public function beforeEach(): void {
        $this->_migrateTables([
            'events',
            'oem_metrics',
            'projects',
            'project_credentials',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
            'app_users',
        ]);
        $this->fixture = new StepLogFixture();
        $this->fixture->reset();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $user = $this->AppUser->Niu([
            'firstname' => 'Test', 'lastname' => 'Logs',
            'email' => 'logs-test@uroboros.local', 'status' => 1,
        ]);
        $user->Save();
        $_SESSION['user'] = $user->id;
    }

    public function _end_(): void {
        (new StepLogFixture())->reset();
    }

    // ---- helpers -------------------------------------------------------

    private function _queueStep(string $command, ?object $project = null): array {
        if ($project === null):
            $project = $this->Project->Niu([
                'name' => 'Logs ' . bin2hex(random_bytes(4)), 'type' => 'backend',
                'status' => 1, 'working_directory' => sys_get_temp_dir(),
            ]);
            $project->Save();
        endif;

        $definition = $this->WorkflowDefinition->Niu([
            'name' => 'WF ' . bin2hex(random_bytes(4)), 'project_id' => $project->id,
            'status' => 1, 'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $definition->Save();

        $step = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id, 'name' => 'Paso log', 'type' => 'build',
            'command' => $command, 'step_order' => 1,
        ]);
        $step->Save();

        (new CommandBus())->Dispatch(new ExecuteWorkflowCommand((int) $definition->id, 'manual'));

        $execution = $this->WorkflowExecution->Find([':first', 'conditions' => [['workflow_definition_id', $definition->id]]]);
        $stepExec  = $this->StepExecution->Find([':first', 'conditions' => [['workflow_execution_id', $execution->id]]]);

        return [$execution, $stepExec, $project];
    }

    /** Encola, ejecuta con el handler real y devuelve la fila final. */
    private function _runStep(string $command, ?StepLogStore $store = null, ?object $project = null): object {
        [$execution, $stepExec] = $this->_queueStep($command, $project);

        $handler           = new RunStepCommandHandler();
        $store === null or ($handler->logStore = $store);
        $handler->Handle(new RunStepCommand((int) $stepExec->id));

        return $this->StepExecution->Find((int) $stepExec->id);
    }

    /**
     * Renderiza una plantilla con el controlador como $this. Las páginas
     * del framework usan include_once: solo la primera de cada proceso se
     * renderiza de verdad, así que los tests de HTML no pueden depender
     * de $page->_rawOutput.
     */
    private function _render(object $page, string $view): string {
        $file = INST_PATH . 'app/views/' . $view;

        return (string) (function () use ($file) {
            ob_start();
            include $file;
            return ob_get_clean();
        })->call($page);
    }

    private function _ageStep(object $step, int $days): void {
        $when = time() - ($days * 86400);
        DB->exec('UPDATE step_executions SET completed_at = ' . $when . ', updated_at = ' . $when . ' WHERE id = ' . (int) $step->id);
    }

    private function _filesUnder(string $dir): array {
        return is_dir($dir) ? array_values(array_diff((array) scandir($dir), ['.', '..'])) : [];
    }

    // ---- escritura y metadatos ----------------------------------------

    public function writeStoresFileAndMetadataTest(): void {
        $this->describe('write() stores the file atomically with size, sha256, tail and permissions');

        $store  = new StepLogStore();
        $text   = "[Uroboros] cabecera\nsalida línea 1 ñandú\nfin";
        $result = $store->write(7, 11, $text);
        $full   = $store->resolve('7/11.log');

        $this->assertEquals(null, $result['error']);
        $this->assertEquals('7/11.log', $result['path']);
        $this->assertEquals(strlen($text), $result['size']);
        $this->assertEquals(hash('sha256', $text), $result['checksum'], 'Checksum is SHA-256 of the exact file bytes');
        $this->assertEquals(hash_file('sha256', $full), $result['checksum']);
        $this->assertEquals($text, $result['tail'], 'A short output is its own tail');
        $this->assertEquals('0664', substr(sprintf('%o', fileperms($full)), -4));
        $this->assertEquals(['11.log'], $this->_filesUnder($store->base . '/7'), 'No temporary file left behind');
    }

    public function hundredKbStepIsCompleteInFileTest(): void {
        $this->describe('a ~100 KB step is stored complete; tail <= 2 KB and checksum matches the file');

        $text   = str_repeat("linea de salida de prueba\n", 4000);
        $store  = new StepLogStore();
        $result = $store->write(3, 5, $text);

        $this->assertEquals(strlen($text), $result['size'], 'No truncation under the 5 MB cap');
        $this->assertEquals($text, file_get_contents($store->resolve('3/5.log')));
        $this->assertTrue(strlen($result['tail']) <= STEP_OUTPUT_TAIL_BYTES);
        $this->assertTrue(str_ends_with($text, $result['tail']), 'Tail is the end of the output');
        $this->assertEquals(hash('sha256', $text), $result['checksum']);
    }

    public function fileIsCappedAtFiveMegabytesWithValidUtf8Test(): void {
        $this->describe('over 5 MB the file keeps head and end with a marker and stays valid UTF-8');

        foreach ([0, 1, 2, 3] as $shift):
            $text   = 'INICIO' . str_repeat('a', $shift) . str_repeat('ñ€😀', 700000) . 'FINAL';
            $store  = new StepLogStore();
            $result = $store->write(4, 9 + $shift, $text);
            $file   = (string) file_get_contents($store->resolve('4/' . (9 + $shift) . '.log'));

            $this->assertTrue(strlen($file) <= STEP_LOG_FILE_MAX_BYTES, "File within cap (shift {$shift})");
            $this->assertEquals(strlen($file), $result['size']);
            $this->assertTrue(str_starts_with($file, 'INICIO'));
            $this->assertTrue(str_ends_with($file, 'FINAL'));
            $this->assertTrue((bool) preg_match('/\[\.\.\. \d+ bytes omitidos \.\.\.\]/', $file), 'Omitted-bytes marker present');
            $this->assertTrue((bool) preg_match('//u', $file), "Valid UTF-8 (shift {$shift})");
            $this->assertEquals(hash('sha256', $file), $result['checksum']);
        endforeach;
    }

    public function tailIsValidUtf8EvenWhenCutMidCharacterTest(): void {
        $this->describe('the tail is cut by bytes from the end but never leaves a broken character');

        foreach ([0, 1, 2, 3] as $shift):
            $store  = new StepLogStore();
            $result = $store->write(6, 20 + $shift, str_repeat('x', $shift) . str_repeat('😀', 3000));

            $this->assertTrue(strlen($result['tail']) <= STEP_OUTPUT_TAIL_BYTES);
            $this->assertTrue($result['tail'] !== '', "Tail not empty (shift {$shift})");
            $this->assertTrue((bool) preg_match('//u', $result['tail']), "Tail valid UTF-8 (shift {$shift})");
        endforeach;
    }

    // ---- fallos de escritura --------------------------------------------

    public function unwritableBaseReturnsErrorAndExplanatoryTailTest(): void {
        $this->describe('an unwritable base never throws: error returned, no path, tail explains why');

        $base = sys_get_temp_dir() . '/uroboros_logs_ro_' . bin2hex(random_bytes(3));
        mkdir($base, 0555);
        $store  = new StepLogStore($base);
        $result = $store->write(1, 1, "salida importante\nCAUSA-FINAL");
        chmod($base, 0755);
        rmdir($base);

        $this->assertTrue($result['error'] !== null, 'Error reported');
        $this->assertEquals(null, $result['path']);
        $this->assertEquals(null, $result['checksum']);
        $this->assertTrue(str_contains($result['tail'], 'No se pudo guardar el log completo'));
        $this->assertTrue(str_ends_with($result['tail'], 'CAUSA-FINAL'), 'Tail still ends with the real output');
        $this->assertTrue(strlen($result['tail']) <= STEP_OUTPUT_TAIL_BYTES);
    }

    public function failedRenameCleansTemporaryFileTest(): void {
        $this->describe('when rename() fails the temporary file is removed');

        $store = new StepLogStore();
        mkdir($store->base . '/8/12.log', 02775, true); // el destino es un directorio: rename() falla
        $result = $store->write(8, 12, 'contenido');

        $this->assertTrue($result['error'] !== null);
        $this->assertEquals(null, $result['path']);
        $this->assertEquals(['12.log'], $this->_filesUnder($store->base . '/8'), 'Only the pre-existing directory remains, no .tmp');
        rmdir($store->base . '/8/12.log');
    }

    public function baseBlockedByRegularFileReturnsErrorTest(): void {
        $this->describe('a base path that cannot be created (a file is in the way) is reported, not thrown');

        $blocker = sys_get_temp_dir() . '/uroboros_logs_blocker_' . bin2hex(random_bytes(3));
        file_put_contents($blocker, 'x');
        $result = (new StepLogStore($blocker . '/sub'))->write(1, 1, 'salida');
        unlink($blocker);

        $this->assertTrue($result['error'] !== null);
        $this->assertTrue(str_contains($result['tail'], 'salida'));
    }

    // ---- path traversal -------------------------------------------------

    public function resolveRejectsTraversalAndSymlinksOutsideBaseTest(): void {
        $this->describe('resolve() rejects ../, absolute paths, bad formats and symlinks pointing outside the base');

        $store = new StepLogStore();
        $store->write(9, 1, 'legítimo');
        $outside = sys_get_temp_dir() . '/uroboros_outside_' . bin2hex(random_bytes(3));
        file_put_contents($outside, 'secreto fuera de la base');
        mkdir($store->base . '/10', 02775, true);
        symlink($outside, $store->base . '/10/2.log');

        $this->assertTrue($store->resolve('9/1.log') !== null, 'Control: legit path resolves');
        $this->assertEquals(null, $store->resolve('../' . basename($outside)));
        $this->assertEquals(null, $store->resolve('9/../../../etc/passwd'));
        $this->assertEquals(null, $store->resolve('9/../9/1.log'), 'Even a normalizable ../ is refused');
        $this->assertEquals(null, $store->resolve('/etc/passwd'));
        $this->assertEquals(null, $store->resolve($outside));
        $this->assertEquals(null, $store->resolve('9/1.txt'));
        $this->assertEquals(null, $store->resolve(''));
        $this->assertEquals(null, $store->resolve(null));
        $this->assertEquals(null, $store->resolve('10/2.log'), 'Symlink to a file outside the base is refused (well-formed path, realpath escapes)');
        $this->assertEquals(null, (new StepLogStore())->readForView('10/2.log'));

        unlink($outside);
    }

    // ---- checksum ---------------------------------------------------------

    public function checksumVerifiesAndDetectsTamperingTest(): void {
        $this->describe('verify() is true for the untouched file and false after altering one byte');

        $store  = new StepLogStore();
        $result = $store->write(2, 2, "linea uno\nlinea dos\n");

        $this->assertTrue($store->verify($result['path'], $result['checksum']));

        $full  = $store->resolve($result['path']);
        $bytes = (string) file_get_contents($full);
        $bytes[3] = 'X';
        file_put_contents($full, $bytes);

        $this->assertFalse($store->verify($result['path'], $result['checksum']), 'Altered byte detected');
        $this->assertFalse($store->verify('2/99.log', $result['checksum']), 'Missing file never verifies');
    }

    public function viewReadCapAddsMarkerTest(): void {
        $this->describe('readForView() caps what is shown at STEP_LOG_VIEW_MAX_BYTES with head, marker and end');

        $store = new StepLogStore();
        $store->write(2, 3, 'INICIO' . str_repeat("linea\n", 100000) . 'FINAL');
        $view = (string) $store->readForView('2/3.log');

        $this->assertTrue(strlen($view) <= STEP_LOG_VIEW_MAX_BYTES + 200);
        $this->assertTrue(str_starts_with($view, 'INICIO'));
        $this->assertTrue(str_ends_with($view, 'FINAL'));
        $this->assertTrue(str_contains($view, 'bytes omitidos'));
    }

    // ---- integración con el handler -------------------------------------

    public function secretSplitByFileCutNeverReachesFileNorTailTest(): void {
        $this->describe('credentials are masked BEFORE the 5 MB cut: no secret fragment in file or tail');

        $secret = 'TOPSECRET9876';

        // Control: SIN enmascarar antes, el corte deja un fragmento del secreto en la cabecera.
        $raw      = str_repeat($secret . "\n", 450000); // 6.3 MB, periodo 14 bytes
        $unmasked = truncateStepOutput($raw, STEP_LOG_FILE_MAX_BYTES, STEP_LOG_FILE_HEAD_BYTES);
        $this->assertTrue(str_contains($unmasked, "TOPSECRE\n[..."), 'Control: the cut splits the secret when not masked first');

        $project = $this->Project->Niu([
            'name' => 'Sec ' . bin2hex(random_bytes(3)), 'type' => 'backend', 'status' => 1,
            'working_directory' => sys_get_temp_dir(),
        ]);
        $project->Save();
        $credential = $this->ProjectCredential->Niu(['project_id' => $project->id, 'name' => 'tok', 'value' => $secret]);
        $credential->Save();

        $step = $this->_runStep('yes {{credential:tok}} | head -c 19999994', null, $project);
        $file = (new StepLogFixture())->read($step);

        $this->assertEquals('completed', $step->status);
        $this->assertTrue(strlen($file) > 4000000 && strlen($file) <= STEP_LOG_FILE_MAX_BYTES, 'Big file, capped');
        $this->assertTrue(str_contains($file, 'bytes omitidos'), 'The cut really happened');
        $this->assertFalse(str_contains($file, 'TOPS'), 'No secret prefix in the file');
        $this->assertFalse(str_contains($file, '9876'), 'No secret suffix in the file');
        $this->assertFalse(str_contains((string) $step->output_tail, 'TOPS'), 'No secret prefix in the tail');
        $this->assertFalse(str_contains((string) $step->output_tail, '9876'), 'No secret suffix in the tail');
        $this->assertTrue(str_contains((string) $step->output_tail, '***'), 'The mask is there');
        $this->assertEquals(hash('sha256', $file), $step->output_checksum);
    }

    public function shortStepStoresMetadataAndTailTest(): void {
        $this->describe('a short completed step records path, size, checksum and tail');

        $step = $this->_runStep('echo hola-mundo');
        $file = (new StepLogFixture())->read($step);

        $this->assertEquals('completed', $step->status);
        $this->assertEquals("{$step->workflow_execution_id}/{$step->id}.log", $step->output_path);
        $this->assertEquals(strlen($file), (int) $step->output_size);
        $this->assertEquals(hash('sha256', $file), $step->output_checksum);
        $this->assertTrue(str_contains((string) $step->output_tail, 'hola-mundo'));
        $this->assertTrue(strlen((string) $step->output_tail) <= STEP_OUTPUT_TAIL_BYTES);
    }

    public function failingStepWithHugeOutputKeepsCauseInTailTest(): void {
        $this->describe('a failing step with huge output keeps the final cause in the tail');

        $step = $this->_runStep('i=0; while [ $i -lt 6000 ]; do echo linea numero $i; i=$((i+1)); done; echo CAUSA-FINAL; exit 3');

        $this->assertEquals('failed', $step->status);
        $this->assertEquals(3, (int) $step->exit_code);
        $this->assertTrue(str_ends_with(trim((string) $step->output_tail), 'CAUSA-FINAL'));
        $this->assertTrue(str_contains((new StepLogFixture())->read($step), 'linea numero 0'), 'Full log has the beginning');
    }

    public function logWriteFailureNeverLeavesStepRunningTest(): void {
        $this->describe('an unwritable log base closes the step with its REAL status and an explanatory tail');

        $base = sys_get_temp_dir() . '/uroboros_logs_ro_' . bin2hex(random_bytes(3));
        mkdir($base, 0555);
        $store = new StepLogStore($base);

        $ok   = $this->_runStep('echo todo-bien', $store);
        $fail = $this->_runStep('echo explotó; exit 4', $store);
        chmod($base, 0755);
        rmdir($base);

        $this->assertEquals('completed', $ok->status, 'Real status kept (exit 0)');
        $this->assertEquals('failed', $fail->status, 'Real status kept (exit 4)');
        $this->assertEquals(4, (int) $fail->exit_code);
        $this->assertTrue(empty($ok->output_path), 'No file pointer when the log could not be written');
        $this->assertTrue(empty($fail->output_path));
        $this->assertTrue(str_contains((string) $fail->output_tail, 'No se pudo guardar el log completo'));
        $this->assertTrue(str_contains((string) $fail->output_tail, 'explotó'), 'The real output is still in the tail');
    }

    public function runnerPassWithUnwritableBaseDoesNotBreakTheChainTest(): void {
        $this->describe('a disk problem during a runner pass leaves no step running and the pass finishes');

        $store = new StepLogStore();
        mkdir($store->base, 02775, true);
        chmod($store->base, 0555);

        [$execution, $stepExec] = $this->_queueStep('echo hi');
        $this->_runAction('/workflow_runner/processpending');
        chmod($store->base, 0755);

        $after = $this->StepExecution->Find((int) $stepExec->id);
        $this->assertEquals('completed', $after->status, 'Step closed, not running');
        $this->assertTrue(empty($after->output_path));
        $this->assertEquals('completed', $this->WorkflowExecution->Find((int) $execution->id)->status);
        $this->assertEquals(0, $this->StepExecution->Find(['conditions' => "`status` IN ('pending','running')"])->counter());
    }

    // ---- vistas ---------------------------------------------------------------

    public function viewEscapesHtmlFromTheLogTest(): void {
        $this->describe('log content is untrusted: <script> in the output is escaped in the page');

        $step = $this->_runStep("echo '<script>alert(1)</script><img src=x onerror=alert(2)>'");
        $page = $this->_runAction("/step_log/view/{$step->id}");

        $this->assertEquals(HTTP_200, (int) $page->_code);
        $html = $this->_render($page, 'step_log/step_log_view.phtml');
        $this->assertFalse(str_contains($html, '<script>alert(1)</script>'), 'Raw script never reaches the page');
        $this->assertFalse(str_contains($html, '<img src=x'), 'Raw tag never reaches the page');
        $this->assertTrue(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Escaped form is shown');
    }

    public function viewHasNoWarningWhenChecksumMatchesTest(): void {
        $this->describe('an intact file shows its content with no checksum warning');

        $step = $this->_runStep('echo contenido-visible');
        $page = $this->_runAction("/step_log/view/{$step->id}");

        $html = $this->_render($page, 'step_log/step_log_view.phtml');
        $this->assertTrue($page->checksumOk);
        $this->assertTrue(str_contains($html, 'contenido-visible'));
        $this->assertFalse(str_contains($html, 'no coincide con su checksum'), 'No warning when intact');
        $this->assertTrue(str_contains($html, 'Descargar log completo'));
    }

    public function viewWarnsOnChecksumMismatchButShowsContentTest(): void {
        $this->describe('an altered file shows the checksum warning and still shows its content');

        $step  = $this->_runStep('echo contenido-visible');
        $full  = (new StepLogFixture())->store->resolve($step->output_path);
        $bytes = (string) file_get_contents($full);
        file_put_contents($full, str_replace('visible', 'VISIBLE', $bytes));

        $page = $this->_runAction("/step_log/view/{$step->id}");

        $this->assertEquals(HTTP_200, (int) $page->_code);
        $html = $this->_render($page, 'step_log/step_log_view.phtml');
        $this->assertFalse($page->checksumOk);
        $this->assertTrue(str_contains($html, 'El archivo no coincide con su checksum'), 'Warning shown');
        $this->assertTrue(str_contains($html, 'contenido-VISIBLE'), 'Content NOT hidden');
    }

    public function viewWithMissingFileShowsTailAndNoticeWithout500Test(): void {
        $this->describe('a missing file shows the tail and "Archivo no disponible", never a 500');

        $step = $this->_runStep('echo extracto-guardado');
        unlink((new StepLogFixture())->store->resolve($step->output_path));

        $page = $this->_runAction("/step_log/view/{$step->id}");

        $this->assertEquals(HTTP_200, (int) $page->_code);
        $html = $this->_render($page, 'step_log/step_log_view.phtml');
        $this->assertFalse($page->logAvailable);
        $this->assertTrue(str_contains($html, 'Archivo no disponible'));
        $this->assertTrue(str_contains($html, 'extracto-guardado'), 'Tail still shown');
        $this->assertFalse(str_contains($html, 'Descargar log completo'), 'No download offered without a file');
    }

    public function downloadServesTheExactFileTest(): void {
        $this->describe('download serves the exact bytes of the file');

        $step = $this->_runStep('echo descarga-ok');
        $page = $this->_runAction("/step_log/download/{$step->id}");

        $this->assertEquals(HTTP_200, (int) $page->_code);
        $this->assertEquals((new StepLogFixture())->read($step), $page->_rawOutput);
    }

    public function downloadOfMissingFileOrUnknownStepIs404Test(): void {
        $this->describe('download of a missing file or an unknown step is 404');

        $step = $this->_runStep('echo descarga-ok');
        unlink((new StepLogFixture())->store->resolve($step->output_path));

        $gone    = $this->_runAction("/step_log/download/{$step->id}");
        $unknown = $this->_runAction('/step_log/download/987654');

        $this->assertEquals(HTTP_404, (int) $gone->_code);
        $this->assertEquals(HTTP_404, (int) $unknown->_code);
    }

    public function viewOfUnknownStepIs404Test(): void {
        $this->describe('view of an unknown step is a 404, not a 500');

        $page = $this->_runAction('/step_log/view/987654');

        $this->assertEquals(HTTP_404, (int) $page->_code);
    }

    public function logActionsRequireLoginTest(): void {
        $this->describe('without a session neither view nor download expose the log');

        $step = $this->_runStep('echo solo-con-sesion');
        unset($_SESSION['user']);

        $view     = $this->_runAction("/step_log/view/{$step->id}");
        $download = $this->_runAction("/step_log/download/{$step->id}");

        $this->assertFalse(str_contains($view->_rawOutput, 'solo-con-sesion'));
        $this->assertFalse(str_contains($download->_rawOutput, 'solo-con-sesion'));
    }

    public function executionDetailShowsTailAndLinkTest(): void {
        $this->describe('the execution view shows the tail and "Ver log completo" only when a file exists');

        $step = $this->_runStep('echo en-la-vista-de-ejecucion');
        $page = $this->_runAction("/admin/workflowexecutiondetail/{$step->workflow_execution_id}");

        $this->assertEquals(HTTP_200, (int) $page->_code);
        $html = $this->_render($page, 'admin/workflow_execution_detail.phtml');
        $this->assertTrue(str_contains($html, 'en-la-vista-de-ejecucion'));
        $this->assertTrue(str_contains($html, "/step_log/view/{$step->id}"));
    }

    // ---- retención ----------------------------------------------------------------

    public function purgeRemovesOldFilesKeepingMetadataTest(): void {
        $this->describe('retention deletes old files and empty folders, keeps size/checksum/tail; recent ones stay');

        $old    = $this->_runStep('echo paso-viejo');
        $recent = $this->_runStep('echo paso-reciente');
        $meta   = ['size' => (int) $old->output_size, 'checksum' => $old->output_checksum, 'tail' => $old->output_tail];
        $store  = new StepLogStore();
        $oldDir = $store->base . '/' . $old->workflow_execution_id;

        $this->_ageStep($old, STEP_LOG_RETENTION_DAYS + 1);
        $this->_runAction('/workflow_runner/processpending');

        $afterOld    = $this->StepExecution->Find((int) $old->id);
        $afterRecent = $this->StepExecution->Find((int) $recent->id);

        $this->assertTrue(empty($afterOld->output_path), 'Pointer cleared');
        $this->assertEquals($meta['size'], (int) $afterOld->output_size);
        $this->assertEquals($meta['checksum'], $afterOld->output_checksum);
        $this->assertEquals($meta['tail'], $afterOld->output_tail);
        $this->assertFalse(is_dir($oldDir), 'Empty execution folder removed');
        $this->assertEquals($recent->output_path, $afterRecent->output_path, 'Recent step untouched');
        $this->assertTrue($store->resolve($recent->output_path) !== null, 'Recent file still on disk');

        $page = $this->_runAction("/step_log/view/{$old->id}");
        $this->assertEquals(HTTP_200, (int) $page->_code);
        $html = $this->_render($page, 'step_log/step_log_view.phtml');
        $this->assertTrue(str_contains($html, 'Archivo no disponible'), 'Purged step reads as unavailable with its tail');
        $this->assertTrue(str_contains($html, 'paso-viejo'), 'Its tail is still shown');
    }

    public function purgeWithAlreadyMissingFileStillClearsPointerAndFolderTest(): void {
        $this->describe('purging a step whose file was deleted by hand clears the pointer and the empty folder');

        $step = $this->_runStep('echo archivo-borrado-a-mano');
        $dir  = (new StepLogStore())->base . '/' . $step->workflow_execution_id;
        unlink((new StepLogStore())->resolve($step->output_path));
        $this->_ageStep($step, STEP_LOG_RETENTION_DAYS + 1);

        $this->_runAction('/workflow_runner/processpending');

        $this->assertTrue(empty($this->StepExecution->Find((int) $step->id)->output_path));
        $this->assertFalse(is_dir($dir), 'Empty folder removed even though the file was already gone');
    }

    public function purgeFailureOnOneElementDoesNotStopTheBatchTest(): void {
        $this->describe('a file that cannot be deleted is logged and skipped; the rest of the batch is purged');

        $stuck = $this->_runStep('echo no-se-puede-borrar');
        $fine  = $this->_runStep('echo si-se-borra');
        $store = new StepLogStore();
        $this->_ageStep($stuck, STEP_LOG_RETENTION_DAYS + 5);
        $this->_ageStep($fine, STEP_LOG_RETENTION_DAYS + 5);

        $lockedDir = $store->base . '/' . $stuck->workflow_execution_id;
        chmod($lockedDir, 0555); // unlink() dentro de un directorio sin escritura falla
        $this->_runAction('/workflow_runner/processpending');
        chmod($lockedDir, 02775);

        $this->assertTrue($this->StepExecution->Find((int) $stuck->id)->output_path !== null, 'Failed element keeps its pointer (retried next pass)');
        $this->assertTrue(empty($this->StepExecution->Find((int) $fine->id)->output_path), 'The next element was still purged');
    }

    public function orphanFoldersAreSweptAndLiveOnesKeptTest(): void {
        $this->describe('folders of executions that no longer exist are swept; live ones are kept');

        $live  = $this->_runStep('echo ejecucion-viva');
        $dead  = $this->_runStep('echo ejecucion-borrada');
        $store = new StepLogStore();
        mkdir($store->base . '/987654', 02775, true);
        mkdir($store->base . '/' . $live->workflow_execution_id . '_x', 02775, true); // no numérica pura: no se toca
        $emptyLive = $this->_runStep('echo carpeta-quedara-vacia');
        unlink($store->resolve($emptyLive->output_path)); // ejecución viva, carpeta vacía
        file_put_contents($store->base . '/987654/1.log', 'huérfano sin ejecución');
        file_put_contents($store->base . '/not-numeric.txt', 'no se toca');

        // Borrado "en cascada" de la BD: no toca archivos.
        DB->exec('DELETE FROM step_executions WHERE workflow_execution_id = ' . (int) $dead->workflow_execution_id);
        DB->exec('DELETE FROM workflow_executions WHERE id = ' . (int) $dead->workflow_execution_id);
        $this->assertTrue(is_dir($store->base . '/' . $dead->workflow_execution_id), 'DB delete alone leaves the folder');

        $this->_runAction('/workflow_runner/processpending');

        $this->assertFalse(is_dir($store->base . '/' . $dead->workflow_execution_id), 'Folder of the deleted execution swept');
        $this->assertFalse(is_dir($store->base . '/987654'), 'Folder without any execution swept');
        $this->assertTrue($store->resolve($live->output_path) !== null, 'Live execution folder kept');
        $this->assertTrue(is_file($store->base . '/not-numeric.txt'), 'Non-numeric entries are never touched');
        $this->assertTrue(is_dir($store->base . '/' . $live->workflow_execution_id . '_x'), 'Non-numeric folders are never touched');
        $this->assertFalse(is_dir($store->base . '/' . $emptyLive->workflow_execution_id), 'Empty folder of a LIVE execution is removed');
    }
}
