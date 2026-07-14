<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;
use App\Commands\PingCommand;
use App\Buses\CommandBus;

class testNucleoOemPingFlow extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables(['events']);
    }

    public function pingFlowTest(): void {
        $this->describe('Should chain PingCommand -> PingStarted -> Reaction -> CompletePingCommand -> PingCompleted');

        (new CommandBus())->Dispatch(new PingCommand('hola'));

        $events = $this->Event->Find([
            'conditions' => [['aggregate_type', 'Ping']],
            'sort'       => '`id` ASC',
        ]);

        $this->assertEquals(2, $events->counter(), 'Should have generated exactly two events');

        $ordered = [];
        foreach ($events as $ev):
            $ordered[] = $ev;
        endforeach;

        $this->assertEquals('PingStarted', $ordered[0]->event_type, 'First event should be PingStarted');
        $this->assertEquals('PingCompleted', $ordered[1]->event_type, 'Second event should be PingCompleted');
        $this->assertEquals(
            $ordered[0]->aggregate_id,
            $ordered[1]->aggregate_id,
            'Both events should belong to the same aggregate'
        );
    }
}
