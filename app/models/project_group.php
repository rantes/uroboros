<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class ProjectGroup extends ActiveRecord {

    public ?int $project_id = null;
    public ?int $group_id   = null;
    public ?int $batch_workflow_definition_id = null;

    public function _init_(): void {
        $this->belongs_to = ['project', 'group'];
    }
}
