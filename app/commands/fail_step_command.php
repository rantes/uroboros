<?php
namespace App\Commands;

class FailStepCommand {
    public function __construct(
        public readonly int $stepExecutionId,
        public readonly string $reason,
        public readonly int $exitCode = 1,
    ) {}
}
