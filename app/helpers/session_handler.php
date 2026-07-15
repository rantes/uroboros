<?php
namespace App\Helpers;

use App\Models\StorageSession;
use \SessionHandlerInterface;

class UroborosSessionHandler implements SessionHandlerInterface {
    private $_session = null;

    public function __construct() {
        $this->_session = new StorageSession();
    }

    public function open(string $path, string $name): bool {
        return true;
    }
    public function close(): bool {
        return true;
    }
    public function read($id): string {
        $id = preg_replace('/[^a-zA-Z0-9,-]/', '', $id);
        $session = $this->_session->Find(['conditions'=>"sessid='{$id}'"]);

        $values = ['', (string)($session->data ?? '')];
        return $values[(int)($session->counter() === 1)];
    }
    public function write($id, $data): bool {
        $session = $this->_session->Find(['conditions'=>"sessid='{$id}'"]);
        if ($session->counter() < 1):
            $session = $this->_session->Niu();
            $session->sessid = $id;
        endif;
        $session->data = $data;
        $session->Save() or die($session->_error);

        return true;
    }
    public function destroy($id): bool {
        $session = $this->_session->Find(['conditions'=>"sessid='{$id}'"]);
        return $session->counter() === 1 and $session->Delete();
    }
    public function gc($max): int|false {
        $old = time() - $max;
        $result = $this->_session->Delete(['conditions'=>"`updated_at`>0 AND `updated_at`<{$old}"]);
        return $result ? 1 : false;
    }
}