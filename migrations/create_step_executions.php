<?php
namespace Migrations;

use DumboPHP\Migrations;

class CreateStepExecutions extends Migrations {

    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id',                          'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'workflow_execution_id',       'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'workflow_step_definition_id', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'status',                       'type' => 'VARCHAR', 'null' => 'false', 'limit' => '20'],
            ['field' => 'exit_code',                    'type' => 'INTEGER', 'null' => 'true'],
            // salida-pasos-en-archivos: la salida completa vive en un archivo
            // (STEP_LOG_BASE_PATH/output_path); aquí solo el puntero, los
            // metadatos, el checksum SHA-256 y un extracto del final. Los tipos
            // son los que soporta Migrations (sin CHAR ni UNSIGNED).
            ['field' => 'output_path',                   'type' => 'VARCHAR', 'null' => 'true',  'limit' => '255'],
            ['field' => 'output_size',                   'type' => 'INTEGER', 'null' => 'false', 'limit' => '11', 'default' => '0'],
            ['field' => 'output_checksum',               'type' => 'VARCHAR', 'null' => 'true',  'limit' => '64'],
            ['field' => 'output_tail',                   'type' => 'TEXT',    'null' => 'true'],
            ['field' => 'started_at',                    'type' => 'INTEGER', 'null' => 'true'],
            ['field' => 'completed_at',                  'type' => 'INTEGER', 'null' => 'true'],
            ['field' => 'created_at',                    'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'updated_at',                    'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
        ];
    }

    public function up(): void {
        $this->Create_Table();

        // Compuesto, no único (mismo hallazgo ya confirmado para
        // project_groups/oem_metrics/workflow_step_definitions).
        // Orden importa: el controlador de background consulta SOLO
        // por status='pending', sin filtrar por workflow_execution_id,
        // así que el índice debe empezar por `status` para servir esa
        // consulta directamente — ver design.md.
        $this->Add_Index(['name' => 'idx_step_executions_status_exec', 'fields' => ['status', 'workflow_execution_id']]);
    }

    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
