<?php
namespace App\Commands;

class RunStepCommand {
    public function __construct(
        public readonly int $stepExecutionId,
    ) {}
}
