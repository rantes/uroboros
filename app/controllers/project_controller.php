<?php
require_once INST_PATH.'app/controllers/admin_base_trait.php';
class ProjectController extends Page {
    use AdminBaseTrait;

    public function __construct() {
        $this->_init();
        $this->_model = 'project';
    }

    public function addeditAction() {
        $this->layout = false;
        $this->groups = $this->ProjectGroup->Find();
        $this->environment = [];
        if(empty($this->params['id'])):
            $this->data = $this->Project->Niu();
            $this->projects = $this->Project->Find();
        else:
            $this->data = $this->Project->Find($this->params['id']);
            $this->projects = $this->Project->Find(['conditions' => "`id` <> {$this->data->id}"]);
            $this->environment = parse_ini_file("{$this->data->path}.env");
        endif;
    }
}