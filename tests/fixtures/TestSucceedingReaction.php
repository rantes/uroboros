<?php
namespace tests\fixtures;

use App\Models\Event;
use DumboPHP\Controller;

/**
 * Fixture de test — verifica el Requisito 3.4. Su único efecto
 * observable es escribir un marcador temporal; deliberadamente no
 * crea Events (eso rompería el Requisito 3.5) porque no es una
 * Reaction de dominio, es exclusivamente instrumentación de test.
 */
class TestSucceedingReaction extends Controller {

    public function Handle(Event $event): void {
        file_put_contents(INST_PATH . 'tmp/testSucceedingReactionRan.tmp', '1');
    }
}
