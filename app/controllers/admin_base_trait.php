<?php

trait AdminBaseTrait {
    private $_model = '';
    public string $nonce = '';
    public string $uid = '';

    private function _init() {
        parent::__construct();

        $this->layout = 'layout';
        $this->uid = $_SERVER['UNIQUE_ID'] ?? strGenerate();
        $this->nonce = 'nonce-'.base64_encode($this->uid);
    }

    public function before_filter() {
        $this->_requireLogin();
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

        try {
            if(empty($this->_model) or empty($_POST[$this->_model])) throw new Exception('Not enough params', HTTP_400);
            $model = Camelize(Singulars($this->_model));
            $data = $this->{$model}->Niu($_POST[$this->_model]);
            if(!$data->Save()) throw new Exception($data->_error, HTTP_402);

            switch ($this->_getController_()):
                case 'project':
                    if(!empty($_POST['env'])):
                        $envString = '';
                        while (null !== ($entry = array_shift($_POST['env']))):
                            $envString = "{$envString}{$entry['key']}={$entry['value']}\n";
                        endwhile;
                        file_put_contents("{$data->path}.env", $envString);
                    endif;
                break;
            endswitch;

            $response['d'] = $data;
            $response['message'] = 'Success';
            $code = HTTP_200;
        } catch (\Throwable $th) {
            $response['message'] = $th->getMessage();
            $code = $th->getCode();
        }

        http_response_code($code);
        $this->respondToAJAX(json_encode($response));
    }

    private function _requireLogin(): bool {
        $actions = ['login', 'signin', 'logout'];
        $canGo = true;
        if (empty($_SESSION['user']) and !in_array($this->_getAction_(), $actions)):
            $canGo = false;
            $this->redirect(INST_URI.'/index/login');
        endif;

        return $canGo;
    }
}
