<?php
  class Project extends ActiveRecord {
    function _init_() {
        $this->has_many = ['execution', 'command'];
        $this->after_save = ['cloneCommands'];
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
        
        public function cloneCommands() {
        if(!empty($_POST['project']['clone_from'])):
            require_once INST_PATH.'app/models/command.php';
            $Command = new Command();
            $commands = $Command->Find_by_project_id($_POST['project']['clone_from']);
            foreach($commands as $command):
                $Command->Niu([
                    'project_id' => $this->id,
                    'command' => $command->command
                ])->Save();
            endforeach;
        endif;
    }
  }
?>