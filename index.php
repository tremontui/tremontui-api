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
	
	$this->get( '/baseitems', function( $request, $response, $args ) use( $auth_service, $db_sourcer ){
		$user_id = $response->getHeader('User_ID')[0];
		$auth = $auth_service->Get_Auth( $user_id );

		$uri = 'https://api.channeladvisor.com/v1/products?$select=ID,Brand,Classification';
		$data_svc = new CA_Data( $auth, $uri );
		$data_vol = $data_svc->GetAllPages();
		
		return $response->withJson( $data_vol, 200 );
	});
	
	$this->get( '/updateitemsfull', function( $request, $response, $args ) use( $auth_service, $db_sourcer ){
		$time_start = microtime(true);
		
		$user_id = $response->getHeader('User_ID')[0];
		$auth = $auth_service->Get_Auth( $user_id );
		
		$select_query = "SELECT ID,Channel_Advisor_ID FROM items WHERE Live = true ORDER BY Last_Pull ASC LIMIT 25";
		$db_response = $db_sourcer->RunQuery( $select_query, [] );
		
		$products = $db_response->result;
		$last_pull_query = "UPDATE items SET Last_Pull = CURRENT_TIMESTAMP WHERE ID IN ";
		$last_pull_param = '(';
		for( $i = 0, $p_length = count( $products ); $i < $p_length; $i++){
			$p_id = $products[$i]['ID'];
			$last_pull_param .= $p_id;
			
			if( $i < $p_length - 1 ){
				$last_pull_param .= ',';
			} else if ( $i = $p_length - 1 ){
				$last_pull_param .= ')';
			}
		}
		$db_last_pull_response = $db_sourcer->RunQuery( $last_pull_query . $last_pull_param, []);
		
		$results = [];
		$data_params = [];
		$qty_params = [];
		foreach( $products as $product ){
			$ca_id = $product['Channel_Advisor_ID'];
			$uri = "https://api.channeladvisor.com/v1/products($ca_id)?" . '$select=ID,Brand,Classification,IsParent,IsInRelationship,ParentProductID,Sku,UPC,Title,Cost,RetailPrice,ReservePrice,BuyItNowPrice';
			$uri_qty = "https://api.channeladvisor.com/v1/products($ca_id)/DCQuantities";
			$data_svc = new CA_Data( $auth, $uri );
			$data_vol = $data_svc->GetSinglePage();
			$data_svc_qty = new CA_Data( $auth, $uri_qty );
			$data_vol_qty = $data_svc_qty->GetSinglePage();
			
			$params = [
				':ca_id'=>$data_vol['ID'],
				':brand'=>$data_vol['Brand'],
				':classification'=>$data_vol['Classification'],
				':sku'=>$data_vol['Sku'],
				':upc'=>$data_vol['UPC'],
				':title'=>$data_vol['Title'],
				':cost'=>$data_vol['Cost'],
				':retail'=>$data_vol['RetailPrice'],
				':reserve'=>$data_vol['ReservePrice'],
				':bin'=>$data_vol['BuyItNowPrice'],
				':is_parent'=>$data_vol['IsParent'],
				':in_relationship'=>$data_vol['IsInRelationship'],
				':parent_id'=>$data_vol['ParentProductID']
			];
			$data_params[] = $params;
			
			foreach( $data_vol_qty['value'] as $dc_item ){
					$qty_params[] = [
						':ca_id'=>$dc_item->ProductID,
						':dc_id'=>$dc_item->DistributionCenterID,
						':qty'=>$dc_item->AvailableQuantity
					];
			}
			
			$results[] = ['data'=>$data_vol,'quantities'=>$data_vol_qty];
		}
		
		$data_query = "CALL full_item_update(:ca_id,:brand,:classification,:sku,:upc,:title,:cost,:retail,:reserve,:bin,:is_parent,:in_relationship,:parent_id)";
		$db_response_data = $db_sourcer->RunQueries( $data_query, $data_params );
		
		$qty_query = "CALL update_distribution_center_quantity(:ca_id,:dc_id,:qty)";
		$db_response_qty = $db_sourcer->RunQueries( $qty_query, $qty_params );
		
		$db_results = ['data'=>$db_response_data,'qty'=>$db_response_qty];
		
		$time_end = microtime(true);
		$time = $time_end - $time_start;
		$return_data = ['db'=>$db_results,'time'=>$time,'params'=>$data_params];
		
		return $response->withJson( $return_data, 200 );
		
	});
	
	$this->get( '/updateinitializations', function( $request, $response, $args ) use( $auth_service, $db_sourcer ){
		//set_time_limit ( 7200 );
		$user_id = $response->getHeader('User_ID')[0];
		$auth = $auth_service->Get_Auth( $user_id );
		
		$db_results = [];

		/************
		//GET CA DATA
		************/
		$select = '$select=ID,Brand,Classification';
		$uri_specs = "https://api.channeladvisor.com/v1/products?$select";
		$data_svc_specs = new CA_Data( $auth, $uri_specs );
		$data_vol_specs = $data_svc_specs->GetAllPages();
		$params_specs = [];
		foreach( $data_vol_specs as $pages ){
			foreach( $pages as $product ){
				$params_specs[] = [
					':channeladvisor_id'=>$product->ID,
					':brand_name'=>$product->Brand,
					':classification_name'=>$product->Classification
				];
			}
		}
		/************
		//GET Specs
		************/
		$query_specs = "CALL item_initialization(:channeladvisor_id,:brand_name,:classification_name)";
		$db_response_specs = $db_sourcer->RunQueries( $query_specs, $params_specs );
		$db_results['specs'] = $db_response_specs;
		
		return $response->withJson( $db_results, 200 );
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

$app->group( '/brands', function() use ( $db_sourcer ){
	
	$this->get( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		$query = "SELECT ID,Brand FROM brands";
		$db_result = $db_sourcer->RunQuery( $query, [] );
		
		return $response->withJson( $db_result, 200 );
		
	});
	
	$this->get( '/{brand_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$query = "SELECT ID,Brand FROM brands WHERE ID = :brand_id";
		$query_params = [':brand_id'=>$args['brand_id']];
		$db_result = $db_sourcer->RunQuery( $query, $query_params );
		
		return $response->withJson( $db_result, 200 );
		
	});
	
	$this->get( '/by_name/{brand_name}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$query = "SELECT ID,Brand FROM brands WHERE Brand = :brand_name";
		$query_params = [':brand_name'=>$args['brand_name']];
		$db_result = $db_sourcer->RunQuery( $query, $query_params );
		
		return $response->withJson( $db_result, 200 );
		
	});
	
});

$app->group( '/brands_canonical', function() use ( $db_sourcer ){
	
	$this->get( '/{brand_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$query = "SELECT ID,Brand_ID,Quantity,Value FROM brands_canonical WHERE Brand_ID = :brand_id";
		$query_params = [':brand_id'=>$args['brand_id']];
		$db_result = $db_sourcer->RunQuery( $query, $query_params );
		
		return $response->withJson( $db_result, 200 );
		
	});
	
	$this->get( '/by_name/{brand_name}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$query = "SELECT a.* FROM brands_canonical a INNER JOIN brands b ON a.Brand_ID = b.ID WHERE b.Brand = :brand_name";
		$query_params = [':brand_name'=>revert_replacers( $args['brand_name'] )];
		$db_result = $db_sourcer->RunQuery( $query, $query_params );
		
		return $response->withJson( $db_result, 200 );
		
	});
	
});

function check_replacers( $string ){
	$replacers = [" "=>"zqzszqz", "&"=>"zqzazqz"];
	$new_string = $string;
	foreach( $replacers as $find=>$replace ){
		$new_string = str_replace( $find, $replace, $new_string );
	}
	
	return $new_string;
}

function revert_replacers( $string ){
	$replacers = ["zqzszqz"=>" ", "zqzazqz"=>"&"];
	$new_string = $string;
	foreach( $replacers as $find=>$replace ){
		$new_string = str_replace( $find, $replace, $new_string );
	}
	
	return $new_string;
}

$app->group( '/items', function() use ( $db_sourcer ){
	
	$this->get( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		$query = "SELECT Channel_Advisor_ID FROM items";
		$db_result = $db_sourcer->RunQuery( $query, [] );
		
		return $response->withJson( $db_result, 200 );
		
	});
	
	$this->get( '/count', function( $request, $response, $args ) use( $db_sourcer ){
		
		$query = "SELECT COUNT(Channel_Advisor_ID) AS Count FROM items";
		$db_result = $db_sourcer->RunQuery( $query, [] );
		
		return $response->withJson( $db_result, 200 );
		
	});
	
	$this->post( '/initialize_bundle', function( $request, $response, $args ) use( $db_sourcer ){
		$time_start = microtime(true);
		$params = json_decode( stripcslashes($request->getBody()->getContents()), true);
		
		$query = "CALL item_initialization(:ca_id,:brand,:classification)";
		$query_params = [];
		foreach( $params as $product ){
			$query_params[] = [
				':ca_id'=>$product['ID'],
				':brand'=>$product['Brand'],
				':classification'=>$product['Classification']
			];
		}
		
		$db_result = $db_sourcer->RunQueries( $query, $query_params );
		$time_end = microtime(true);
		$time = $time_end - $time_start;
		$return_data = ['db'=>$db_result,'time'=>$time];
		
		return $response->withJson( $return_data, 200 );
	});
	
	$this->post( '', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		
		/*$query = "CALL item_initialization(:ca_id,:brand,:classification)";
		$query_params = [
			':ca_id'=>$params['ca_id'],
			':brand'=>$params['brand'],
			':classification'=>$params['classification']
		];
		
		$db_result = $db_sourcer->RunQuery( $query, $query_params );*/
		
		return $response->withJson( $params, 200 );
	});
	
	$this->patch( '/{ca_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		
		$query = "CALL full_item_update(:ca_id,:brand,:classification.:sku,:UPC,:Title,:Cost,:Retail,:Reserve,:BIN)";
		$query_params = [
			':ca_id'=>$args['ca_id'],
			':brand'=>$params['brand'],
			':sku'=>$params['sku'],
			':UPC'=>$params['UPC'],
			':Title'=>$params['Title'],
			':Cost'=>$params['Cost'],
			':Retail'=>$params['Retail'],
			':Reserve'=>$params['Reserve'],
			':BIN'=>$params['BIN']
		];
		
		$db_result = $db_sourcer->RunQuery( $query, $query_params );
		
		return $response->withJson( $db_result, 200 );
	});
	
	$this->patch( '/quantity/{ca_id}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		
		$query = "CALL update_distribution_center_quantity(:ca_id,:brand,:classification)";
		$query_params = [
			':ca_id'=>$args['ca_id'],
			':brand'=>$params['brand'],
			':sku'=>$params['sku']
		];
		
		$db_result = $db_sourcer->RunQuery( $query, $query_params );
		
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
			':brand_name'=>revert_replacers( $params['brand_name'] )
		];
		
		$db_return = $db_sourcer->RunQuery( $query, $query_params );
		
		$api_return = new API_Return( "true", $db_return );
		
		return $response->withJson( $api_return, 201 );
		
	});
	
	//DELETE QUICK_BRAND
	$this->post( '/delete/{brand_name}', function( $request, $response, $args ) use( $db_sourcer ){
		
		$params = $request->getQueryParams();
		
		$return = [$params,$args];
		
		$query = "DELETE FROM quick_brands WHERE User_ID = :user_id AND Brand_Name = :brand_name";
		$query_params = [
			':user_id'=>$params['user_id'],
			':brand_name'=>revert_replacers( $args['brand_name'] )
		];
		
		$db_return = $db_sourcer->RunQuery( $query, $query_params );
		
		$api_return = new API_Return( "true", $db_return );
		
		return $response->withJson( $query, 201 );
		
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