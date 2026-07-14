<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

class testEventModel extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables(['events']);
    }

    public function saveOkTest(): void {
        $this->describe('Should save a valid Event');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Ping',
            'aggregate_id'   => 1,
            'event_type'     => 'PingStarted',
            'payload'        => json_encode(['message' => 'hola']),
        ]);

        $result = $event->Save();
        $errors = $event->_error->errFields();

        $this->assertEquals(0, sizeof($errors), 'Should have no errors');
        $this->assertTrue($result, 'Save should return true');
    }

    public function triggerValidationErrorsTest(): void {
        $this->describe('Should fail validation without aggregate_type or event_type');

        $event  = $this->Event->Niu();
        $result = $event->Save();
        $errors = $event->_error->errFields();

        $this->assertFalse($result, 'Save should return false');
        $this->assertTrue(in_array('aggregate_type', $errors), 'aggregate_type should be in errors');
        $this->assertTrue(in_array('event_type', $errors), 'event_type should be in errors');
    }

    public function replayOrderTest(): void {
        $this->describe('Should retrieve Events of the same aggregate in chronological order');

        $this->Event->Niu(['aggregate_type' => 'Ping', 'aggregate_id' => 5, 'event_type' => 'PingStarted'])->Save();
        $this->Event->Niu(['aggregate_type' => 'Ping', 'aggregate_id' => 5, 'event_type' => 'PingCompleted'])->Save();
        $this->Event->Niu(['aggregate_type' => 'Ping', 'aggregate_id' => 9, 'event_type' => 'PingStarted'])->Save();

        $events = $this->Event->Find([
            'conditions' => [
                ['aggregate_type', 'Ping'],
                ['aggregate_id', 5],
            ],
            'sort' => '`id` ASC',
        ]);

        $this->assertEquals(2, $events->counter(), 'Should find only the events of aggregate_id 5');

        $ordered = [];
        foreach ($events as $ev):
            $ordered[] = $ev;
        endforeach;

        $this->assertEquals('PingStarted', $ordered[0]->event_type, 'First event should be PingStarted');
        $this->assertEquals('PingCompleted', $ordered[1]->event_type, 'Second event should be PingCompleted');
    }
}
