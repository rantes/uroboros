<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class Event extends ActiveRecord {

    public ?string $aggregate_type = null;
    public ?int    $aggregate_id   = null;
    public ?string $event_type     = null;
    public ?string $payload        = null; // JSON string

    public function _init_(): void {
        $this->validate = [
            'presence_of' => [
                ['field' => 'aggregate_type', 'message' => 'El tipo de agregado es obligatorio'],
                ['field' => 'event_type', 'message' => 'El tipo de evento es obligatorio'],
            ]
        ];

        // Sin before_save de sanitización con htmlentities(): el
        // payload es JSON técnico, no texto para render en HTML.
        // htmlentities() lo corrompería. Ver design.md — Núcleo OEM,
        // sección "Modelos".
    }
}
