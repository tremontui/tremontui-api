<?php
/**
 * BOOTSTRAP
 */
require_once './vendor/autoload.php';
require_once './api_bootstrap.php';

session_start();

/**
 *	NAMESPACING
 */
use Slim\Views\PhpRenderer;	
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$configuration = [
	'settings' => [
		'displayErrorDetails' => true,
	],
];
$c = new \Slim\Container( $configuration );

$app = new Slim\App( $c );

$container = $app->getContainer();
$container['renderer'] = new PhpRenderer( './templates' );

/**
 *	MIDDLEWARE
 */
$app->add( function( Request $request, Response $response, callable $next ) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/') {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));
        return $response->withRedirect((string)$uri, 301);
    }

    return $next($request, $response);
});

$app->get( '/', function( $request, $response, $args ) use( $db_ini ){
	
	print_r( $db_ini );
	
	$onload = ['title' => 'Tremont UI API Documentation'];
	
	return $this->renderer->render( $response, '/docs.php', $onload );
	
});

$app->group( '/users', function() use( $db_ini ){
	
	$host = $db_ini['server'];
	$port = $db_ini['port'];
	$database = $db_ini['server_db'];
	$username = $db_ini['server_user'];
	$password = $db_ini['server_password'];
	
	$db_sourcer = new PDO_Sourcer( "mysql:host=$host;port=$port;dbname=$database", $username, $password );
	
	//GET ALL USERS
	$this->get( '', function( $request, $response, $args ) use( $db_sourcer ){

		$query = "SELECT * users";
		print_r( $db_sourcer->RunQuery( $query, [] ) );
		
	});
	
	//GET USER BY ID
	$this->get( '{user_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
	});
	
	//POST NEW USER
	$this->post( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		
		print_r( $params );
		
	});
	
	//PATCH USER UPDATE WITH PARAMS
	$this->patch( '{user_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
	});
	
});

$app->run();
?>