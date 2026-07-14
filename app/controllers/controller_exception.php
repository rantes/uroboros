<?php
namespace App\Controllers;

class ControllerException extends \Exception implements \JsonSerializable {
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array {
        return [
            'code' =>  $this->code,
            'message' => $this->message
        ];
    }
}