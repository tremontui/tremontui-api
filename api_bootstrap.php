<?php

require_once( './autoload.php' );

DEFINE( "SUPER_ROOT", $_SERVER['DOCUMENT_ROOT'] . '/../' );

$db_ini = parse_ini_file( SUPER_ROOT . 'config/db_config.ini' );
$ca_ini = parse_ini_file( SUPER_ROOT . 'config/ca_config.ini' );

?>