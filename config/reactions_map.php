<?php

use App\Reactions\OnPingStartedReaction;
use App\Reactions\OnWorkflowStartedReaction;
use App\Reactions\OnStepCompletedReaction;
use App\Reactions\OnStepFailedReaction;
use tests\fixtures\TestFailingReaction;
use tests\fixtures\TestSucceedingReaction;

return [
    'PingStarted' => [
        OnPingStartedReaction::class,
    ],

    // Ejecución de Workflows — StepQueued y WorkflowCompleted/
    // WorkflowFailed no tienen Reaction todavía: nada más se dispara
    // desde ellos en esta parte del spec. Ver
    // .claude/specs/ejecucion-workflows/design.md.
    'WorkflowStarted' => [
        OnWorkflowStartedReaction::class,
    ],
    'StepCompleted' => [
        OnStepCompletedReaction::class,
    ],
    'StepFailed' => [
        OnStepFailedReaction::class,
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
