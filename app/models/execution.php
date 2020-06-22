<?php
  class Execution extends ActiveRecord {
    function _init_() {
        $this->belongs_to = ['project'];
    }
  }
?>