<?php
namespace Migrations;

use DumboPHP\Controller;

class Seeds extends Controller {

    private function _sowAdmin() {
        $user = $this->AppUser->Find(['fields'=>'id', 'conditions'=>"`email`='admin@admin.com'"]);
        if (0 === $user->counter()):
            $user = $this->AppUser->Niu([
                'email' => 'admin@admin.com',
                'firstname' => 'Admin',
                'lastname' => 'Admin',
                'document' => '88267145',
                'document_kind_id' => 1,
                'phone' => 123456,
                'password' => '1234567890',
                'status' => 1,
                'level' => 32,
            ]);
            $user->Save() or die($user->_error);
        endif;
    }

    public function sow(?array $actions = []): void {
        $defaults = [
            '_sowAdmin'
        ];
        $actions = empty($actions) ? $defaults : $actions;
        foreach ($actions as $action):
            $this->{$action}();
        endforeach;
    }
}