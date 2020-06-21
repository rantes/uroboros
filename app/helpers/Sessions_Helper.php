<?php

  function Require_login() {
    if (empty($_SESSION['user']) and _ACTION !== 'login' and _ACTION !== 'signin'):
        header('Location: /index/login');
        exit;
    endif;

    return true;
  }
?>