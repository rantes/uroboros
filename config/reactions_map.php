<?php

use App\Reactions\OnPingStartedReaction;
use tests\fixtures\TestFailingReaction;
use tests\fixtures\TestSucceedingReaction;

return [
    'PingStarted' => [
        OnPingStartedReaction::class,
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
