<?php
namespace App\Commands;

class PingCommand {
    public function __construct(
        public readonly string $message,
    ) {}
}
