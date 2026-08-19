<?php
namespace App\Commands;

class CompleteWorkflowCommand {
    public function __construct(
        public readonly int $workflowExecutionId,
    ) {}
}
