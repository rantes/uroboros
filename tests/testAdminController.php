<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

class testAdminController extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables(['events', 'oem_metrics']);
        $_SERVER['HTTP_x-sf-token'] = 'token';
        $_SESSION['xsfr_token']     = 'token';
    }

    public function eventsListRendersWithoutErrorTest(): void {
        $this->describe('GET /admin/events debe listar sin errores fatales');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Test',
            'aggregate_id'   => 0,
            'event_type'     => 'ExplorerListTest',
            'payload'        => json_encode([]),
        ]);
        $event->Save();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/admin/events');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'GET /admin/events debe responder 200');
        $this->assertEquals(1, $result->data->counter(), 'Debe listar el Event creado arriba');
    }

    /**
     * Nota sobre por qué estos tres tests no assertan $result->_code:
     * AdminBaseTrait::landingAction() reporta el código de la
     * ControllerException vía $this->setResponseCode($code), que
     * escribe DumboPHP\Controller::$_http_response_code (privado, el
     * que sí llega al cliente real vía http_response_code()) — nunca
     * escribe MainController::$_code (la propiedad pública que
     * $result->_code expone y que el resto de este proyecto usa para
     * aserciones). Es un bug preexistente del trait compartido, no
     * introducido por este guard, y arreglarlo está fuera de alcance
     * de explorador-eventos. Verificado con curl contra el servidor
     * real (https://uroboros.rantes.local) que las tres respuestas
     * SÍ son HTTP 405 de verdad — ver reporte. Aquí se assertea lo
     * que _runAction() sí refleja de forma confiable: que ninguna
     * escritura tuvo efecto real sobre la tabla `events`.
     */
    public function eventsCreateIsBlockedTest(): void {
        $this->describe('POST /admin/events/add no debe crear ninguna fila (bloqueado por el guard)');

        $before = $this->Event->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['event'] = ['aggregate_type' => 'Test', 'aggregate_id' => 0, 'event_type' => 'ShouldNotBeCreated'];
        $this->_runAction('/admin/events/add');

        $this->assertEquals($before, $this->Event->Find()->counter(), 'No debe haberse creado ninguna fila nueva');
    }

    public function eventsUpdateIsBlockedTest(): void {
        $this->describe('PUT /admin/events/{id} no debe modificar la fila (bloqueado por el guard)');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Test',
            'aggregate_id'   => 0,
            'event_type'     => 'Original',
            'payload'        => json_encode([]),
        ]);
        $event->Save();

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_POST['event'] = ['event_type' => 'Hacked'];
        $this->_runAction("/admin/events/{$event->id}");

        $stillOriginal = $this->Event->Find((int) $event->id);
        $this->assertEquals('Original', $stillOriginal->event_type, 'El event_type no debe haber cambiado');
    }

    public function eventsDeleteIsBlockedTest(): void {
        $this->describe('DELETE /admin/events/{id} no debe eliminar la fila (bloqueado por el guard)');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Test',
            'aggregate_id'   => 0,
            'event_type'     => 'ShouldSurvive',
            'payload'        => json_encode([]),
        ]);
        $event->Save();

        $before = $this->Event->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->_runAction("/admin/events/{$event->id}");

        $this->assertEquals($before, $this->Event->Find()->counter(), 'No debe haberse eliminado ninguna fila');
    }
}
