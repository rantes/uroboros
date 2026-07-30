<?php
namespace Migrations;

use DumboPHP\Migrations;

class CreateProjectGroups extends Migrations {

    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id',         'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'project_id', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'group_id',   'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'created_at', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'updated_at', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
        ];
    }

    public function up(): void {
        $this->Create_Table();

        // Compuesto, no único: Add_Index()/AddIndex() del framework real
        // no soporta declarar UNIQUE (verificado en
        // DumboPHP/lib/db_drivers/{mysql,sqlite,postgresql}.php, mismo
        // hallazgo ya confirmado para oem_metrics — ver
        // metricas-oem/design.md). La unicidad del par
        // (project_id, group_id) debe garantizarla la capa de modelo
        // genérica, no esta migración.
        $this->Add_Index(['name' => 'idx_project_groups_project_group', 'fields' => ['project_id', 'group_id']]);
    }

    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
