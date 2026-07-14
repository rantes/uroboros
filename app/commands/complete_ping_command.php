<?php
namespace App\Commands;

class CompletePingCommand {
    public function __construct(
        public readonly int    $pingId,
        public readonly string $message,
    ) {}
}
