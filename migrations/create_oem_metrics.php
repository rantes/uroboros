<?php
namespace Migrations;

use DumboPHP\Migrations;

class CreateOemMetrics extends Migrations {

    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id',          'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'metric_type', 'type' => 'VARCHAR', 'null' => 'false', 'limit' => '50'],
            ['field' => 'hour_bucket', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'count',       'type' => 'INTEGER', 'null' => 'false', 'limit' => '11', 'default' => '0'],
            ['field' => 'created_at',  'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'updated_at',  'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
        ];
    }

    public function up(): void {
        $this->Create_Table();

        // Compuesto, no único: Add_Index()/AddIndex() del framework real
        // (verificado en DumboPHP/lib/db_drivers/{mysql,sqlite}.php) no
        // soporta declarar UNIQUE — mismo pendiente confirmado para
        // project_groups en gestion-proyectos/design.md.
        $this->Add_Index(['name' => 'idx_oem_metrics_type_hour', 'fields' => ['metric_type', 'hour_bucket']]);
    }

    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
