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

/*
 *	PREPARE FOR DB EXECUTIONS BY BUILDING SOURCER
 */
$host = $db_ini['server'];
$port = $db_ini['port'];
$database = $db_ini['server_db'];
$username = $db_ini['server_user'];
$password = $db_ini['server_password'];
$db_sourcer = new PDO_Sourcer( "mysql:host=$host;port=$port;dbname=$database", $username, $password );

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

$app->add( function( Request $request, Response $response, callable $next ) use( $db_sourcer ){
	
	$uri = $request->getUri();
	$path = $uri->getPath();
	$headers = $request->getHeaders();
	
	$path_wl = ['/users/verify_username', '/users/verify_password'];
	
	
	//PASS IF HAVE THE SYSAUTH TOKEN HEADER
	if( isset( $headers['HTTP_SYSAUTH'] ) ){
		
		$sys_auth = new System_Authentication( $db_sourcer );
		
		//AUTHENTICATE
		$auth_result = $sys_auth->Authenticate_Tremont( $headers['HTTP_SYSAUTH'][0] );
		
		if( $auth_result['success'] == 'true' ){
			
			$newResponse = $response->withHeader( 'User_ID', $auth_result['result']['User_ID'] );
			
			return $next( $request, $newResponse );
			
		} else {
			
			$api_return = new API_Return( "false", $auth_result['result'] );
		
			return $response->withJson( $api_return, 401 );
			
		}
		
	} else {
		//CHECK IF LOGGING IN
		if( in_array( $path, $path_wl ) ){
			
			return $next( $request, $response );
			
		}
		
	}
	
	$api_return = new API_Return( "false", 'No Authentication Provided.' );
		
	return $response->withJson( $api_return, 401 );
	
});

$app->get( '/', function( $request, $response, $args ) use( $db_sourcer ){
	
	$onload = ['title' => 'Tremont UI API Documentation'];
	
	return $this->renderer->render( $response, '/docs.php', $onload );
	
});

$app->group( '/channeladvisor', function() use ( $db_sourcer ){
	
	//REFRESH TOKENS
	$this->get( '/redirect', function( $request, $response, $args ){
		
	});
	
	$this->get( '/refresh_token', function( $request, $response, $args ){
		
		$ca_response = \Httpful\Request::post( 'https://api.channeladvisor.com/oauth2/token' )
			->addHeaders(
				[
					'Content-Type'=>'application/x-www-form-urlencoded',
					'Authorization'=>
				]
			)
			->expectsJson()
			->send();
		$api_response_body = $api_response->body;
		
	});
	
});

$app->group( '/authentications', function() use ( $db_sourcer ){
	
	$this->get( '/user/{token}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$query = "SELECT 	
							authentications.User_ID,
							users.First_Name, 
							users.Last_Name, 
							users.Username 
						FROM 
							authentications INNER JOIN users 
						ON 
						authentications.User_ID=users.ID 
						WHERE authentications.Token = :token";
		$query_params = [':token'=>$args['token']];
		
		$db_return = $db_sourcer->RunQuery( $query, $query_params );
		
		if( $db_return->result == null ){
			
			$api_return = new API_Return( "false", 'Invalid Token' );
		
			return $response->withJson( $api_return, 401 );
			
		} else {
			
			$api_return = new API_Return( "true", $db_return->result[0] );
		
			return $response->withJson( $api_return, 200 );
			
		}
		
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
	$this->post( '/verify_username', function( $request, $response, $args ) use( $db_sourcer ){
			
			$params = $request->getQueryParams();
			if( !isset( $params['username'] ) ){
				
				$api_return = new API_Return( "false", 'No username provided' );
				
				return $response->withJson( $api_return, 400 );
				
			} else {
			
				$query = "SELECT ID FROM users WHERE Username = :username";
				$query_params = [':username'=>$params['username']];
				
				$db_return = $db_sourcer->RunQuery( $query, $query_params );
				
				if( $db_return->result == null ){
					
					$api_return = new API_Return( "false", 'Invalid username' );
				
					return $response->withJson( $api_return, 400 );
					
				} else {
					
					$api_return = new API_Return( "true", ['User_ID'=>$db_return->result[0]['ID']] );
				
					return $response->withJson( $api_return, 200 );
				}
				
			}	
				
	});
	
	$this->post( '/verify_password', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		if( !isset( $params['password'] ) || !isset( $params['user_id'] ) ) {
			
			$api_return = new API_Return( 'false', 'A parameter [password] and [user_id] must be provided.' );
		
			return $response->withJson( $api_return, 400 );
			
		} else {
			
			$pass_svc = new Password_Service();
			
			$query = "SELECT Password FROM users WHERE ID = :id";
			$query_params = [':id'=>$params['user_id']];
			
			$db_return = $db_sourcer->RunQuery( $query, $query_params );
			
			if( $db_return->db_success != 'true' ){
				
				$api_return = new API_Return( "true", $db_return );
		
				return $response->withJson( $api_return, 200 );
				
			} else {
				
				$pass_svc->Store_Hash( $db_return->result[0]['Password'] );
				
				$pass_verified = $pass_svc->Compare_Password( $params['password'] );
				$result['password_verified'] = $pass_verified;
				
				if( $pass_verified == 'true' ){
					
					$sys_auth = new System_Authentication( $db_sourcer );	
					
					$return = [
						'password_verified'=>'true',
						'authentication'=>$sys_auth->Add_Authenticate_Tremont( $params['user_id'] )
					];
					
					$api_return = new API_Return( "true", $return );
		
					return $response->withJson( $api_return, 200 );
					
				} else {
					
					$api_return = new API_Return( "false", $result );
		
					return $response->withJson( $api_return, 401 );
					
				}
				
			}
			
		}
				
	});
	
});

$app->run();
?>