<?php

require_once 'C:/xampp5/tremontui-api/vendor/autoload.php';
require_once 'C:/xampp5/tremontui-api/api_bootstrap.php';

$host = $db_ini['server'];
$port = $db_ini['port'];
$database = $db_ini['server_db'];
$username = $db_ini['server_user'];
$password = $db_ini['server_password'];
$db_sourcer = new PDO_Sourcer( "mysql:host=$host;port=$port;dbname=$database", $username, $password );

$auth_service = new CA_Auth_Service( $db_sourcer, $ca_ini );

$user_id = 1;
$auth = $auth_service->Get_Auth( $user_id );

$uri_base = 'https://api.channeladvisor.com/v1/products?$select=ID,Brand,Classification';

$data_svc = new CA_Data( $auth, $uri_base );

print_r( $data_svc->GetAllPages() );

?>