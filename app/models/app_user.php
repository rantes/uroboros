<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

/**
 * Modelo Usuarios
 *
 * @author Javier Serrano <rantes.javier@gmail.com>
 * @version  1.0
 * @package Komodo
 * @subpackage Models
 */
class AppUser extends ActiveRecord {
    public ?string $password      = '';
    public ?int $level            = 0;
    public ?int $is_patroller     = 0;
    public ?string $photo         = '';
    public ?string $storage_path       = '';
    public ?string $storage_type       = '';

    /**
     * @return void
     */
    public function _init_(): void {
        $this->before_save = ['sanitizeFirstname', 'sanitizeLastname'];
        
        $this->before_insert = ['setPassword'];
        $this->before_save[]  = 'encryptPassword';

        $this->validate = [
            'presence_of' => [
                ['field' => 'email', 'message' => 'El email es obligatorio'],
            ],
            'email'       => [
                ['field' => 'email', 'message' => 'El email no es valido'],
            ],
            'unique'      => [
                ['field' => 'email', 'message' => 'El email ya se encuentra registrado'],
            ],
        ];
    }

    /**
     * @return void
     */
    public function encryptPassword(): void {
        empty($this->password) or ($this->password = password_hash($this->password, PASSWORD_BCRYPT));
    }

    /**
     * @param $email
     * @param $password
     * @return int
     */
    public function login($email, $password): int {
        $N     = new AppUser();
        $nuser = $N->Find([
            'conditions' => "`email`='{$email}' AND `status`=1",
        ]);
        // will return 0 or the user id
        return ($nuser->counter() === 1 and password_verify($password, $nuser->password)) * (int) $nuser->id;
    }

    /**
     *
     */
    public function setPassword(): void {
        ! empty($this->document) and empty($this->password) and ($this->password = $this->document);
    }

     /**
     * @return void
     */
    public function sanitizeFirstname(): void {
        if (isset($this->firstname)):
            $this->firstname = htmlentities($this->firstname ?? '', ENT_QUOTES, 'UTF-8', false);
        endif;
    }
    public function sanitizeLastname(): void {
        if (isset($this->lastname)):
            $this->lastname = htmlentities($this->lastname ?? '', ENT_QUOTES, 'UTF-8', false);
        endif;
    }

}
