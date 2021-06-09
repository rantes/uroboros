<?php
require_once INST_PATH.'app/controllers/admin_base_trait.php';
class AdminController extends Page {
    use AdminBaseTrait;

    public function __construct() {
        $this->_init();
    }

    public function indexAction() {
        $this->render = ['text'=>'noop'];
    }

    public function groupsAction() {
        $this->groups = $this->ProjectGroup->Find();
    }

    public function deletegroupAction() {
        $this->_model = 'project_group';
        $this->deleteregAction();
    }

    public function groupaddregAction() {
        $this->_model = 'project_group';
        $this->addregAction();
    }

    public function addeditgroupAction() {
        $this->layout = false;
        if(empty($this->params['id'])):
            $this->data = $this->ProjectGroup->Niu();
        else:
            $this->data = $this->ProjectGroup->Find($this->params['id']);
        endif;
    }
}