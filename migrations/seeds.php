<?php
class Seed extends Page {


    private function _sowAdmin() {
        $user = $this->User->Find(array('fields'=>'id', 'conditions'=>"`email`='rantes.javier@gmail.com'"));
        0 === $user->counter() and $this->User->Niu([
            'email' => 'rantes.javier@gmail.com',
            'firstname' => 'Javier',
            'lastname' => 'Serrano',
            'password' => '1234567890',
            'status' => 1
            ])->Save() or die($user->_error);
    }

    private function _sowParams() {
    }

    public function sow() {
        $this->_sowAdmin();
    }
}
?>