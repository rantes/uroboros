<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;
use App\Buses\CommandBus;
use App\Buses\EventBus;
use tests\fixtures\Commands\TestSucceedingCommand;
use tests\fixtures\Commands\TestFailingCommand;

class testOemMetrics extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables(['events', 'oem_metrics']);
    }

    private function _currentHourBucket(): int {
        return (int) (floor(time() / 3600) * 3600);
    }

    private function _metricCount(string $metricType): int {
        $metric = $this->OemMetric->Find([
            ':first',
            'conditions' => [
                ['metric_type', $metricType],
                ['hour_bucket', $this->_currentHourBucket()],
            ],
        ]);

        return (int) $metric->count;
    }

    public function commandDispatchedIncrementsOnSuccessTest(): void {
        $this->describe('Should increment command_dispatched when CommandBus::Dispatch succeeds');

        (new CommandBus())->Dispatch(new TestSucceedingCommand());

        $this->assertEquals(1, $this->_metricCount('command_dispatched'), 'command_dispatched should be 1 after a single successful dispatch');
        $this->assertEquals(0, $this->_metricCount('command_failed'), 'command_failed should stay 0 on success');
    }

    public function commandFailedIncrementsAndRethrowsTest(): void {
        $this->describe('Should increment command_dispatched and command_failed, and rethrow, when the Handler fails');

        $thrown = false;

        try {
            (new CommandBus())->Dispatch(new TestFailingCommand());
        } catch (\Exception $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'Dispatch should rethrow the Handler exception (Requisito 2.1)');
        $this->assertEquals(1, $this->_metricCount('command_dispatched'), 'command_dispatched should increment even when the Handler fails');
        $this->assertEquals(1, $this->_metricCount('command_failed'), 'command_failed should increment when the Handler fails');
    }

    public function reactionExecutedIncrementsOnSuccessTest(): void {
        $this->describe('Should increment reaction_executed when a Reaction succeeds');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Ping',
            'aggregate_id'   => 0,
            'event_type'     => 'PingStarted',
            'payload'        => json_encode(['message' => 'hola']),
        ]);
        $event->Save();

        (new EventBus())->Dispatch($event);

        $this->assertEquals(1, $this->_metricCount('reaction_executed'), 'reaction_executed should be 1 after the single Reaction registered for PingStarted');
        $this->assertEquals(0, $this->_metricCount('reaction_failed'), 'reaction_failed should stay 0 on success');
    }

    public function reactionFailedIncrementsBothCountersTest(): void {
        $this->describe('Should increment reaction_executed and reaction_failed when a Reaction fails, without blocking the others');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Test',
            'aggregate_id'   => 0,
            'event_type'     => 'ReactionFailureTestEvent',
            'payload'        => json_encode([]),
        ]);
        $event->Save();

        (new EventBus())->Dispatch($event);

        // ReactionFailureTestEvent tiene dos Reactions registradas
        // (una falla, una tiene éxito) — reaction_executed sube por
        // ambas, reaction_failed sube solo por la que falla.
        $this->assertEquals(2, $this->_metricCount('reaction_executed'), 'reaction_executed should count both the failing and succeeding reactions');
        $this->assertEquals(1, $this->_metricCount('reaction_failed'), 'reaction_failed should count only the failing reaction');

        $markerPath = INST_PATH . 'tmp/testSucceedingReactionRan.tmp';
        file_exists($markerPath) and unlink($markerPath);
    }
}
