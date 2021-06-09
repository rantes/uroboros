<?php
class ProjectGroup extends ActiveRecord {
    function _init_() {
        $this->has_many = ['project'];
    }
}