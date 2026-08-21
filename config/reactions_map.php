<?php

use App\Reactions\OnPingStartedReaction;
use App\Reactions\OnWorkflowStartedReaction;
use App\Reactions\OnStepCompletedReaction;
use App\Reactions\OnStepFailedReaction;
use App\Reactions\OnWorkflowCompletedReaction;
use tests\fixtures\TestFailingReaction;
use tests\fixtures\TestSucceedingReaction;

return [
    'PingStarted' => [
        OnPingStartedReaction::class,
    ],

    // Ejecución de Workflows — StepQueued y WorkflowRunning no tienen
    // Reaction todavía: nada más se dispara desde ellos en esta parte
    // del spec. Ver .claude/specs/ejecucion-workflows/design.md.
    // WorkflowFailed nunca dispara nada — Requisito 2.2 de
    // encadenamiento-workflows, solo completed encadena.
    'WorkflowStarted' => [
        OnWorkflowStartedReaction::class,
    ],
    'StepCompleted' => [
        OnStepCompletedReaction::class,
    ],
    'StepFailed' => [
        OnStepFailedReaction::class,
    ],
    // encadenamiento-workflows — dispara ExecuteWorkflowCommand con
    // trigger_type='cascade' hacia cualquier WorkflowDefinition
    // configurado con workflow_definition_id apuntando
    // al que acaba de completar. Ver design.md.
    'WorkflowCompleted' => [
        OnWorkflowCompletedReaction::class,
    ],

    // Fixture de test del Requisito 3.4 (una Reaction fallida no
    // bloquea a las demás). Eliminar esta entrada junto con las
    // Reactions de prueba al cerrar la tarea 19 de tasks.md, o dejarla
    // marcada como fixture permanente si se decide conservarla.
    'ReactionFailureTestEvent' => [
        TestFailingReaction::class,
        TestSucceedingReaction::class,
    ],
];
