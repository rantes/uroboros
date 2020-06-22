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
        if(empty($this->params['id'])):
            $this->data = $this->Project->Niu();
        else:
            $this->data = $this->Project->Find($this->params['id']);
        endif;
    }
}
?>