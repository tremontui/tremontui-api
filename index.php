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
	
	$path_wl = ['/users/verify_username', '/users/verify_password', '/channeladvisor/redirect'];
	
	
	//PASS IF HAVE THE SYSAUTH TOKEN HEADER
	if( isset( $headers['HTTP_SYSAUTH'] ) ){
		
		$sys_auth = new System_Authentication( $db_sourcer );
		
		//AUTHENTICATE
		$auth_result = $sys_auth->Authenticate_Tremont( $headers['HTTP_SYSAUTH'][0] );
		
		if( $auth_result['success'] == 'true' ){
			
			$newResponse = $response->withHeader( 'User_ID', $auth_result['result']['User_ID'][0] );

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

$app->group( '/channeladvisor', function() use ( $db_sourcer, $ca_ini ){
	
	$auth_service = new CA_Auth_Service( $db_sourcer, $ca_ini );
	
	$this->get( '/updatebase', function( $request, $response, $args ) use( $auth_service, $db_sourcer ){
		$user_id = $response->getHeader('User_ID')[0];
		$auth = $auth_service->Get_Auth( $user_id );
		
		$db_results = [];
		
		//$select_qty = '$select=ID,DCQuantities[' ."'Charlotte'" .']';
		$select_price = '$select=ID,Cost,RetailPrice,ReservePrice,BuyItNowPrice';
		$select_cr = '$select=ID,Cost,RetailPrice';
		$select_mp = '$select=ID,ReservePrice,BuyItNowPrice';
		
		/************
		//GET Specs
		************/
		$select_specs = '$select=ID,Brand,Classification';
		$uri_specs = "https://api.channeladvisor.com/v1/products?$select_specs";
		$data_svc_specs = new CA_Data( $auth, $uri_specs );
		$data_vol_specs = $data_svc_specs->GetAllPages();
		$params_specs = [];
		foreach( $data_vol_specs as $pages ){
			foreach( $page as $product ){
				$params_specs[] = [
					':channeladvisor_id'=>$product->ID,
					':brand_name'=>$product->Brand,
					':classification_name'=>$product->Classification
				];
			}
		}
		$query_specs = "CALL update_specs(:channeladvisor_id,:brand_name,:classification_name)";
		$db_response_specs = $db_sourcer->RunQueries( $query_specs, $params_specs );
		
		/************
		//GET Base
		************/
		$select_base = '$select=ID,Sku,UPC';
		$uri_base = "https://api.channeladvisor.com/v1/products?$select_base";
		$data_svc_base = new CA_Data( $auth, $uri_base );
		$data_vol_base = $data_svc_base->GetAllPages();
		$params_base = [];
		foreach( $data_vol_base as $pages ){
			foreach( $page as $product ){
				$params_base[] = [
					':channeladvisor_id'=>$product->ID,
					':sku'=>$product->Sku,
					':upc'=>$product->UPC
				];
			}
		}
		$query_base = "CALL update_base(:channeladvisor_id,:sku,:upc)";
		$db_response_base = $db_sourcer->RunQueries( $query_base, $params_base );
		
		/************
		//GET Titles
		************/
		$select_titles = '$select=ID,Title';
		$uri_titles = "https://api.channeladvisor.com/v1/products?$select_titles";
		$data_svc_titles = new CA_Data( $auth, $uri_titles );
		$data_vol_titles = $data_svc_titles->GetAllPages();
		$params_titles = [];
		foreach( $data_vol_titles as $pages ){
			foreach( $page as $product ){
				$params_titles[] = [
					':channeladvisor_id'=>$product->ID,
					':title'=>$product->Title,
				];
			}
		}
		$query_titles = "CALL update_item_title(:channeladvisor_id,:title)";
		$db_response_titles = $db_sourcer->RunQueries( $query_titles, $params_titles );
		
		/*$param_data = [];
		$return_data = [];

		foreach( $data_vol as $page ){
			
			foreach( $page as $product ){
				
				$return_data[] = 
				[
					'channeladvisor_id'=>$product->ID,
					'brand_name'=>$product->Brand,
					'classification_name'=>$product->Classification,
					'sku'=>$product->Sku,
					'upc'=>$product->UPC,
					'title'=>$product->Title
				];
				
				$param_data[] = 
				[
					':channeladvisor_id'=>$product->ID,
					':brand_name'=>$product->Brand,
					':classification_name'=>$product->Classification,
					':sku'=>$product->Sku,
					':upc'=>$product->UPC,
					':title'=>$product->Title
				];
				
			}

		}
		
		$query = "CALL full_item_update(:channeladvisor_id,:brand_name,:classification_name,:sku,:upc,:title)";
		$db_response = $db_sourcer->RunQueries( $query, $param_data );*/
		
		return $response->withJson( $db_response_specs, 200 );
		
	});
	
	$this->get( '/updatespecs', function( $request, $response, $args ) use ( $auth_service, $db_sourcer ) {
		$user_id = $response->getHeader('User_ID')[0];
		$auth = $auth_service->Get_Auth( $user_id );

		$select_query = "SELECT Channel_Advisor_ID FROM items WHERE Last_Update < CURRENT_DATE OR Last_Update IS NULL ORDER BY Last_Update ASC LIMIT 300";
		$db_response_1 = $db_sourcer->RunQuery( $select_query, [] );
		
		if( $db_response_1->db_success == 'true' ){
			$multi_params = [];
			
			$result_set = $db_response_1->result;
			foreach( $result_set as $row ){
				$ca_id = $row['Channel_Advisor_ID'];
				$uri_base = 'https://api.channeladvisor.com/v1/products(' . $ca_id . ')?$select=Sku,UPC,Title,Classification,Brand';
				
				$data_svc = new CA_Data( $auth, $uri_base );
				$data_response = $data_svc->GetSinglePage();

				$multi_params[] = [
					':ca_id'=>$ca_id,
					':sku'=>$data_response['Sku'],
					':upc'=>$data_response['UPC'],
					':title'=>$data_response['Title'],
					':brand'=>$data_response['Brand'],
					':classification'=>$data_response['Classification']
				];

			}
			
			$update_specs_query = "CALL update_specs(:ca_id,:sku,:upc,:title,:brand,:classification)";
			$update_specs_response = $db_sourcer->RunQueries( $update_specs_query, $multi_params );
			
			return $response->withJson( $update_specs_response, 200 );
		} else {
			return $response->withJson( 'blarg', 200 );
		}
		
		
		
		/*
		
		
		
		$query = "CALL update_item_sku(:sku,:ca_id)";*/
		//$query_params = [':sku'=>,':ca_id'=>$ca_id];
		
	});
	
	$this->get( '/test', function( $request, $response, $args ) use( $auth_service ){
		$user_id = $response->getHeader('User_ID')[0];
		$auth = $auth_service->Get_Auth( $user_id );
		
		$uri_base = "https://api.channeladvisor.com/v1/products?";
				
		$ca_response = \Httpful\Request::get( $uri_base )
			->expectsJson()
			->addHeaders( array( 
				'Authorization' => "Bearer " . $auth->auth_token
			) )
			->send();
		
		print_r('<pre>');
		print_r( $ca_response->body->value );
		print_r('</pre>');
	});
	
	$this->get( '/brandoverview', function( $request, $response, $args ) use( $auth_service ){

		$user_id = $response->getHeader('User_ID')[0];
		$auth = $auth_service->Get_Auth( $user_id );
		
		$params = $request->getQueryParams();

		$uri_base = 'https://api.channeladvisor.com/v1/products?$select=Cost,TotalAvailableQuantity&$filter=Brand%20eq%20' . "'" . str_replace( "^", "%20", $params['brand'] ) . "'%20and%20IsParent%20eq%20false%20and%20TotalAvailableQuantity%20gt%200";
		
		$data_svc = new CA_Data( $auth, $uri_base );
		
		$data_vol = $data_svc->GetAllPages();
		
		$return_data = [
			'TotalQty'=>0,
			'TotalVal'=>0,
			'TotalQtyPages'=>[],
			'PageVals'=>[]
		];
		
		foreach( $data_vol as $page ){
			
			$pageQty = 0;
			$pageVal = 0;
			
			foreach( $page as $product ){
				
				$pageQty += (int) $product->TotalAvailableQuantity;
				$pageVal += (float) ( (int) $product->TotalAvailableQuantity * (float) $product->Cost );
				
				$return_data['TotalQty'] += (int) $product->TotalAvailableQuantity;
				$return_data['TotalVal'] += (float) ( (int) $product->TotalAvailableQuantity * (float) $product->Cost );
				
			}
				
			$return_data['TotalQtyPages'][] = $pageQty;
			$return_data['PageVals'][] = $pageVal;
		}
		
		return $response->withJson( $return_data, 200 );
		
	});
	
	$this->get( '/products', function( $request, $response, $args ) use( $auth_service ){
		$user_id = $response->getHeader('User_ID')[0];
		$auth = $auth_service->Get_Auth( $user_id );
		
		$params = $request->getQueryParams();
		
		$return_data = [];
		
		$uri_base = "https://api.channeladvisor.com/v1/products?";
		if( isset( $params['select'] ) ){
			$uri_base .= '$select=' . $params['select'];
		}
		if( isset( $params['filter'] ) ){
			$uri_base .= '&$filter=' . str_replace( '^', '%20', $params['filter'] );
		}
		if( isset( $params['orderby'] ) ){
			$uri_base .= '&$orderby=' . $params['orderby'];
		}
		if( isset( $params['skip'] ) ){
			$uri_base .= '&$skip=' . $params['skip'];
		}
		
		$ca_response = \Httpful\Request::get( $uri_base )
			->expectsJson()
			->addHeaders( array( 
				'Authorization' => "Bearer " . $auth->auth_token
			) )
			->send()->body;
		$ca_array = (array) $ca_response;
		if( isset( $ca_array['@odata.nextLink'] ) ){
			
		}
		$next_page = $ca_array['@odata.nextLink'];
		
		
		return $response->withJson( $ca_response, 200 );
		
	});
	
	$this->get( '/authorize', function( $request, $response, $args ) use( $ca_ini ){
		
		$app_id = $ca_ini['app_id'];
		$redirect = $ca_ini['redirect'];
		
		$uri_base = "https://api.channeladvisor.com/oauth2/authorize?client_id=$app_id&response_type=code&scope=orders%20inventory&redirect_uri=$redirect&access_type=offline";
		
		$response = \Httpful\Request::get($uri_base)
			->send();
		//print_r( $response->body );
		return $response->body;
		
	});
	
	$this->get( '/redirect', function( $request, $response, $args ) use ( $ca_ini ){
		
		$params = $request->getQueryParams();
		$app_id = $ca_ini['app_id'];
		$secret = $ca_ini['secret'];
		$redirect = $ca_ini['redirect'];
		$auth_token = $params['code'];
		
		$ca_response = \Httpful\Request::post( 'https://api.channeladvisor.com/oauth2/token' )
			->addHeaders(
				[
					'Content-Type'=>'application/x-www-form-urlencoded',
					'Authorization'=>'Basic ' . base64_encode( "$app_id:$secret" )
				]
			)
			->body( "grant_type=authorization_code&code=$auth_token&redirect_uri=$redirect" )
			->expectsJson()
			->send();
			
		$ca_response_body = $ca_response->body;
		
		print_r( 'Copy the Appropriate values into your account authentication.' );
		print_r('<pre>');
		print_r( $ca_response_body );
		print_r('</pre>');
		/*print_r( $ca_response_body );
		print_r('<pre>');
		print_r( $response->getHeaders() );
		print_r('</pre>');*/
		//print_r( $response );
	});
	
	//REFRESH TOKEN
	$this->group( '/refresh_token', function() use( $ca_ini, $db_sourcer ){
		
		$this->get( '/{token}',  function( $request, $response, $args ) use ( $ca_ini, $db_sourcer ){
			
			$query = "SELECT 
								ca_refresh_tokens.User_ID,
								ca_refresh_tokens.Token 
							FROM 
								authentications INNER JOIN ca_refresh_tokens 
							ON 
								authentications.User_ID=ca_refresh_tokens.User_ID 
							WHERE authentications.Token = :token";
			$query_params = [':token'=>$args['token']];
			
			$db_return = $db_sourcer->RunQuery( $query, $query_params );
			
			if( $db_return->result == null ){
				
				$api_return = new API_Return( "false", 'No Refresh Token' );
			
				return $response->withJson( $api_return, 401 );
				
			} else {
				
				$api_return = new API_Return( "true", $db_return->result[0] );
			
				return $response->withJson( $api_return, 200 );
				
			}
			
		});
		
		$this->post( '', function( $request, $response, $args ) use ( $ca_ini, $db_sourcer ){
			
			$params = $request->getQueryParams();
			$token = $params['token'];
			
			$user_id = $response->getHeaders()['User_ID'][0];
			
			$query = "INSERT INTO
								ca_refresh_tokens (User_ID,Token)
							VALUES (:user_id,:token)";
			$query_params = [
				':user_id'=>$user_id,
				':token'=>$token
			];
			
			$db_return = $db_sourcer->RunQuery( $query, $query_params );
			
			if( $db_return->db_success == 'false' ){
				
				$api_return = new API_Return( "false", 'Something went wrong' );
			
				return $response->withJson( $api_return, 401 );
				
			} else {
				
				$api_return = new API_Return( "true", $db_return );
			
				return $response->withJson( $api_return, 200 );
				
			}
			
		});
		
	});
	
});

$app->group( '/items', function() use ( $db_sourcer ){
	
	$this->get( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		$query = "SELECT * FROM items";
		$db_result = $db_sourcer->RunQuery( $query, [] );
		
		return $response->withJson( $db_result, 200 );
		
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

$app->group( '/quick_brands', function() use( $db_sourcer ){
	
	//GET ALL QUCIK_BRANDS
	$this->get( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		$select_prep = new Select_Prepare( 'ID,User_ID,Brand_Name', $params );
		$select_fields = $select_prep->Protect_Passwords()->Get_Selects();
		
		$query = "SELECT $select_fields FROM quick_brands";
		
		$db_return = $db_sourcer->RunQuery( $query, [] );
		
		$api_return = new API_Return( "true", $db_return );

		return $response->withJson( $api_return, 200 );
		
	});
	
	//GET ALL QUICK_BRANDS By USER
	$this->get( '/user/{user_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		$select_prep = new Select_Prepare( 'ID,User_ID,Brand_Name', $params );
		$select_fields = $select_prep->Protect_Passwords()->Get_Selects();
		
		$query = "SELECT $select_fields FROM quick_brands WHERE User_ID = :user_id";
		$query_params = [':user_id'=>$args['user_id']];
		
		$db_return = $db_sourcer->RunQuery( $query, $query_params );
		
		$api_return = new API_Return( "true", $db_return );
		
		return $response->withJson( $api_return, 200 );
		
	});
	
	//POST NEW QUICK_BRAND
	$this->post( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		
		$query = "INSERT INTO quick_brands (User_ID,Brand_Name) VALUES (:user_id, :brand_name)";
		$query_params = [
			':user_id'=>$params['user_id'],
			':brand_name'=>str_replace( "^", " ", $params['brand_name'] )
		];
		
		$db_return = $db_sourcer->RunQuery( $query, $query_params );
		
		$api_return = new API_Return( "true", $db_return );
		
		return $response->withJson( $api_return, 201 );
		
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