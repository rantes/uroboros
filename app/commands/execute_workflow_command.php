<?php
namespace App\Commands;

class ExecuteWorkflowCommand {
    public function __construct(
        public readonly int    $workflowDefinitionId,
        public readonly string $triggerType,
    ) {}
}
