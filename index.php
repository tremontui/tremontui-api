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

    return $next( $request, $response );
});

/*$app->add( function( Request $request, Response $response, callable $next ){
	
	$uri = $request->getUri();
	$path = $uri->getPath();
	$headers = $request->getHeaders();
	
	$path_thru = ['/users/'];
	
	//PASS IF HAVE THE SYSAUTH TOKEN HEADER
	if( isset( $headers['HTTP_SYSAUTH'] ) ){
		//AUTHENTICATE
	} else {
		//CHECK IF LOGGING IN
	}
	
	return $response->withJson( $headers );
	
});*/

$app->get( '/', function( $request, $response, $args ) use( $db_ini ){
	
	$onload = ['title' => 'Tremont UI API Documentation'];
	
	return $this->renderer->render( $response, '/docs.php', $onload );
	
});

/*
 *	PREPARE FOR DB EXECUTIONS BY BUILDING SOURCER
 */
$host = $db_ini['server'];
$port = $db_ini['port'];
$database = $db_ini['server_db'];
$username = $db_ini['server_user'];
$password = $db_ini['server_password'];
$db_sourcer = new PDO_Sourcer( "mysql:host=$host;port=$port;dbname=$database", $username, $password );

$app->group( '/authentications', function() use ( $db_sourcer ){
	
	$this->get( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		print_r( bin2hex( openssl_random_pseudo_bytes( 32 ) ) );
		
	});
	
});

$app->group( '/users', function() use( $db_sourcer ){
	
	//GET ALL USERS
	$this->get( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		$select_prep = new Select_Prepare( 'ID,Username,Email,First_Name,Last_Name', $params );
		$select_fields = $select_prep->Protect_Passwords()->Get_Selects();
		
		$query = "SELECT $select_fields FROM users";
		
		$db_return = $db_sourcer->RunQuery( $query, [] );
		
		$api_return = new API_Return( "true", $db_return );
		
		return $response->withJson( $api_return, 200 );
		
	});
	
	//GET USER BY ID
	$this->get( '/{user_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		$select_prep = new Select_Prepare( 'ID,Username,Email,First_Name,Last_Name', $params );
		$select_fields = $select_prep->Protect_Passwords()->Get_Selects();
		
		$query = "SELECT $select_fields FROM users WHERE ID = :id";
		$query_params = [':id'=>$args['user_id']];
		
		$db_return = $db_sourcer->RunQuery( $query, $query_params );
		
		$api_return = new API_Return( "true", $db_return );
		
		return $response->withJson( $api_return, 200 );
		
	});
	
	//POST NEW USER
	$this->post( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		$pass_svc = new Password_Service();
		
		$pass_hash = $pass_svc->Encrpyt_Password( $params['password'] )->Get_Hash();
		
		$query = "INSERT INTO users (Username, Email, First_Name, Last_Name, Password) VALUES (:username, :email, :first_name, :last_name, :pass_hash)";
		$query_params = [
			':username'=>$params['username'],
			':email'=>$params['email'],
			':first_name'=>$params['first_name'],
			'last_name'=>$params['last_name'],
			':pass_hash'=>$pass_hash 
		];
		
		$db_return = $db_sourcer->RunQuery( $query, $query_params );
		
		$api_return = new API_Return( "true", $db_return );
		
		return $response->withJson( $api_return, 201 );
		
	});
	
	//PATCH USER UPDATE WITH PARAMS
	$this->patch( '/{user_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
	});
	
	//VERB BASED ROUTES
	$this->post( '/verify_username/{username}', function( $request, $response, $args ) use( $db_sourcer ){
			
			$pass_svc = new Password_Service();
			
			$query = "SELECT Password FROM users WHERE ID = :id";
			$query_params = [':id'=>$args['user_id']];
			
			$db_return = $db_sourcer->RunQuery( $query, $query_params );
		
			return $response->withJson( $api_return, 200 );
				
	});
	
	$this->post( '/verify_password/{user_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		if( !isset( $params['password'] ) ){
			
			$api_return = new API_Return( 'false', 'A parameter [password] must be provided.' );
		
			return $response->withJson( $api_return, 400 );
			
		} else {
			
			$pass_svc = new Password_Service();
			
			$query = "SELECT Password FROM users WHERE ID = :id";
			$query_params = [':id'=>$args['user_id']];
			
			$db_return = $db_sourcer->RunQuery( $query, $query_params );
			
			if( $db_return->db_success != 'true' ){
				
				$api_return = new API_Return( "true", $db_return );
		
				return $response->withJson( $api_return, 200 );
				
			} else {
				
				$pass_svc->Store_Hash( $db_return->result[0]['Password'] );
				
				$result = ['password_verified'=>$pass_svc->Compare_Password( $params['password'] )];
				
				$api_return = new API_Return( "true", $result );
		
				return $response->withJson( $api_return, 200 );
				
			}
			
		}
				
	});
	
});

$app->run();
?>