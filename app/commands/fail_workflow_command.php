<?php
namespace App\Commands;

class FailWorkflowCommand {
    public function __construct(
        public readonly int $workflowExecutionId,
        public readonly int $failedStepExecutionId,
    ) {}
}
