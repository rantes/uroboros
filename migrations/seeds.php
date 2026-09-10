<?php
namespace Migrations;

use DumboPHP\Controller;

class Seeds extends Controller {

    public function sow(?array $actions = []): void {
        $defaults = [];
        $actions = empty($actions) ? $defaults : $actions;
        foreach ($actions as $action):
            $this->{$action}();
        endforeach;
    }
}