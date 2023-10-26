<?php
class Setting extends ActiveRecord {
    public $id = null;
    public $name = null;
    public $value = null;
    public $created_at = null;
    public $updated_at = null;
    function _init_() {
    }
}
