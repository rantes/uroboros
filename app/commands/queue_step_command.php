<?php
namespace App\Commands;

class QueueStepCommand {
    public function __construct(
        public readonly int $workflowExecutionId,
        public readonly int $stepDefinitionId,
    ) {}
}
