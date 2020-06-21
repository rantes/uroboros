<?php
/**
 * @author rantes
 */
class menuEntry {
    public $link = '';
    public $label = '';
    public $items = [];
    public $icon = '';
    public $hasItems = false;
/**
 *
 * @param string $link
 * @param string $label
 * @param array $items
 * @param string $icon
 */
    public function __construct($link, $label, $items = [], $icon = '') {
        $this->link = $link;
        $this->label = $label;
        $this->items = $items;
        $this->icon = $icon;

        $this->hasItems = !empty($this->items);
    }
}
?>