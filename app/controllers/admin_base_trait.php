<?php

trait AdminBaseTrait {
    private $_model = '';

    private function _init() {
        parent::__construct();

        $this->layout = 'layout';
        $this->helper = ['Sessions'];
    }

    public function before_filter() {
        Require_login();
    }

    public function deleteregAction() {
        $code = HTTP_422;
        /** message key is a kinda standard, if an error occurs, message is the same attribute as in exception object */
        $response = [
            'd' => [],
            'message' => ''
        ];
        if (!empty($this->_model) and !empty($this->params['id'])):
            $model = Camelize(Singulars($this->_model));

            $obj = $this->{$model}->Find((integer)$this->params['id']);
            ($obj->Delete() and ($code = HTTP_200) and ($response['message'] = 'Success')) or ($response['m'] = (string)$obj->_error);
        endif;

        http_response_code($code);
        $this->respondToAJAX(json_encode($response));
    }

    public function addregAction() {
        $code = HTTP_422;
        $response = [
            'd' => [],
            'message' => 'Error Saving'
        ];

        if(!empty($this->_model) and !empty($_POST[$this->_model])):
            $model = Camelize(Singulars($this->_model));
            $data = $this->{$model}->Niu($_POST[$this->_model]);
            (
                $data->Save()
                and ($response['d'] = $data and ($response['message'] = 'Success') and $code = HTTP_200)
            )
            or ($response['message'] = (string)$data->_error);
        endif;

        http_response_code($code);
        $this->respondToAJAX(json_encode($response));
    }
}


?>