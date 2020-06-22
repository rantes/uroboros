<?php
  class Project extends ActiveRecord {
    function _init_() {
        $this->has_many = ['execution', 'command'];
    }

    public function getLastExecution() {
        require_once INST_PATH.'app/models/execution.php';
        $Execution = new Execution();
        $last = $this->counter() === 1 ? $Execution->Niu() : $Execution->Find([
            ':first',
            'conditions' => "project_id='{$this->id}'",
            'sort' => 'id DESC'
        ]);
        return $last;
    }
  }
?>