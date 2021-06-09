<?php
/**
 * Modelo Usuarios
 *
 * @author Javier Serrano <jserrano@muy.co>
 * @version  1.0
 * @package MUYCCTV
 * @subpackage Models
 */
class User extends ActiveRecord {
    public function _init_(){
        $this->before_save = ['sanitize', 'encryptPassword'];

        $this->validate = [
            'presence_of' => [
                ['field'=>'firstname','message'=>_('model.error.required.firstname')],
                ['field'=>'lastname','message'=>_('model.error.required.lastname')],
                ['field'=>'email','message'=>_('model.error.required.email')],
            ],
            'email' => [
                ['field'=>'email','message'=>_('model.error.format.email')]
            ],
            'unique' => [
                ['field'=>'email','message'=>_('model.error.unique.email')]
            ]
        ];
    }

    public function sanitize() {
        $this->firstname = htmlentities($this->firstname, ENT_QUOTES, 'UTF-8',false);
        $this->lastname = htmlentities($this->lastname, ENT_QUOTES, 'UTF-8',false);
    }

    public function encryptPassword() {
        empty($this->password) or ($this->password = sha1($this->password));
    }

    public function login($user, $password) {
        $user = $this->Find_by_email($user);
        // will return 0 or the user id
        return ($user->counter() === 1 and $user->password === sha1($password)) * $user->id;
    }
}
