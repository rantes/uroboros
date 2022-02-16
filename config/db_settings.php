<?php
$databases = [
    'dev' => [
        'driver' => DB_DRIVER,
        'host' =>  DB_HOST,
        'charset' =>  DB_CHARSET,
        'dialect' =>  DB_DIALECT,
        'port' =>  DB_PORT,
        'schema' =>  DB_SCHEMA,
        'username' =>  DB_USERNAME,
        'password' =>  DB_PASSWORD,
        'unix_socket' =>  DB_UNIX_SOCKET
    ],
    'production' => [
        'driver' => DB_DRIVER,
        'host' =>  DB_HOST,
        'charset' =>  DB_CHARSET,
        'dialect' =>  DB_DIALECT,
        'port' =>  DB_PORT,
        'schema' =>  DB_SCHEMA,
        'username' =>  DB_USERNAME,
        'password' =>  DB_PASSWORD,
        'unix_socket' =>  DB_UNIX_SOCKET
    ],
    'test' => [
        'driver' => $this->_sysConfig('DB_DRIVER_TEST'),
        'host' =>  $this->_sysConfig('DB_HOST_TEST'),
        'charset' => $this->_sysConfig('DB_CHARSET_TEST'),
        'dialect' => $this->_sysConfig('DB_DIALECT_TEST'),
        'port' => $this->_sysConfig('DB_PORT_TEST'),
        'schema' => $this->_sysConfig('DB_SCHEMA_TEST'),
        'username' => $this->_sysConfig('DB_USERNAME_TEST'),
        'password' => $this->_sysConfig('DB_PASSWORD_TEST'),
        'unix_socket' => $this->_sysConfig('DB_UNIX_SOCKET_TEST')
    ]
];
