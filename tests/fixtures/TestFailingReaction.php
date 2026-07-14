<?php
namespace tests\fixtures;

use App\Models\Event;
use DumboPHP\Controller;

/**
 * Fixture de test — verifica el Requisito 3.4 (una Reaction que falla
 * no debe bloquear a las demás). No es una Reaction de dominio.
 */
class TestFailingReaction extends Controller {

    public function Handle(Event $event): void {
        throw new \Exception('Fallo intencional de prueba (Requisito 3.4)');
    }
}
