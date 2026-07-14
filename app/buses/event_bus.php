<?php
namespace App\Buses;

use App\Models\Event;

class EventBus {

    public function Dispatch(Event $event): void {
        $map = include INST_PATH . 'config/reactions_map.php';
        $reactionClasses = $map[$event->event_type] ?? [];

        foreach ($reactionClasses as $reactionClass):
            try {
                $reaction = new $reactionClass();
                $reaction->Handle($event);
            } catch (\Exception $e) {
                // Requisito 3.4: una Reaction fallida no debe tumbar a
                // las demás. Se registra el error sin librería externa
                // (regla "Aprovechamiento de lo existente").
                error_log("Reaction fallida [{$reactionClass}] para evento {$event->event_type}: " . $e->getMessage());
            }
        endforeach;
    }
}
