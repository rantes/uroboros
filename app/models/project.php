<?php
class Project extends ActiveRecord {
    function _init_() {
        $this->has_many = ['execution', 'command'];
        $this->after_save = ['cloneCommands'];
        $this->belongs_to = ['project_group'];
        $this->before_insert = ['setFolder', 'setFiles'];
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

    public function setFolder() {
        is_dir($this->path) or mkdir($this->path);
    }

    public function setFiles() {
        !empty($this->git_repo) && is_dir($this->path) && exec("git clone {$this->git_repo} {$this->path}");
    }
}