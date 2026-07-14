<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;
use App\Buses\EventBus;

class testEventBusReactionFailure extends dumboTests {

    private string $_markerPath;

    public function _init_(): void {
        $this->_markerPath = INST_PATH . 'tmp/testSucceedingReactionRan.tmp';
    }

    public function beforeEach(): void {
        $this->_migrateTables(['events']);
        file_exists($this->_markerPath) and unlink($this->_markerPath);
    }

    public function failingReactionDoesNotBlockOthersTest(): void {
        $this->describe('A failing Reaction should not block other Reactions subscribed to the same event');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Test',
            'aggregate_id'   => 0,
            'event_type'     => 'ReactionFailureTestEvent',
            'payload'        => json_encode([]),
        ]);
        $event->Save();

        (new EventBus())->Dispatch($event);

        $this->assertTrue(
            file_exists($this->_markerPath),
            'The succeeding reaction should have run despite the failing one'
        );

        file_exists($this->_markerPath) and unlink($this->_markerPath);
    }
}
