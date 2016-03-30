<?php

class CA_Auth_Service{
	
	private $db_sourcer;
	private $ca_ini;
	
	public function __construct( $db_sourcer, $ca_ini ){
		
		$this->db_sourcer = $db_sourcer;
		$this->ca_ini = $ca_ini;
		
	}
	
	public function Get_Auth( $user_id ){
		
		$query = "SELECT Token FROM ca_refresh_tokens WHERE User_ID = :user_id";
		$query_params = [':user_id'=>$user_id];
		$db_result = $this->db_sourcer->RunQuery( $query, $query_params );
		
		$refresh_token = $db_result->result[0]['Token'];

		$last_auth = $this->Check_Last_Auth( $user_id );
		if( $last_auth->auth_token == null || $last_auth->auth_token == '' ){
			
			//REQUEST NEW TOKEN
			$auth = $this->Request_Auth( $refresh_token );
			$this->Update_Auth( $user_id, $auth->auth_token, $auth->expiration );
			return $auth;
			
		} else {
			
			//CHECK FOR EXPIRATION
			$now = new DateTime( gmdate( 'Y-m-d H:i:s' ) );
			$expiration = new DateTime( $last_auth->expiration );
			//print_r( $now->format('Y-m-d H:i:s') . ' vs ' . $expiration->format('Y-m-d H:i:s') );
			if( $now > $expiration ){
				//EXPIRED
				$auth = $this->Request_Auth( $refresh_token );
				$this->Update_Auth( $user_id, $auth->auth_token, $auth->expiration );
				return $auth;
				
			} else {
				
				return $last_auth;
				
			}
					
		}
		
	}
	
	private function Update_Auth( $user_id, $token, $expiration ){
		
		$query = "INSERT INTO authentications (User_ID,Token,Authentication,Expiration) VALUES (:user_id,:token,'CHANNEL ADVISOR',:expiration)";
		$query_params = [
			':user_id'=>$user_id,
			':token'=>$token,
			':expiration'=>$expiration
		];
		
		$this->db_sourcer->RunQuery( $query, $query_params );
		
	}
	
	private function Request_Auth( $refresh_token ){
		
		$uri_base = 'https://api.channeladvisor.com/oauth2/token';
		//print_r( $this->ca_ini );
		$encode = base64_encode( $this->ca_ini['app_id'] . ':' . $this->ca_ini['secret'] );
		//print_r($encode);
		$response = \Httpful\Request::post( $uri_base )
			->expectsJson()
			->addHeaders( array( 
				'Content-Type' => 'application/x-www-form-urlencoded',
				'Authorization' => 'Basic ' . $encode
			) )
			->body( 'grant_type=refresh_token&refresh_token=' . $refresh_token )
			->send();
			
		$tokenData = $response->body;
		
		//print_r( $tokenData );
		
		$now = new DateTime();
		$duration = new DateInterval( 'PT' . $tokenData->expires_in . 'S' );
		$expiration = $now->add( $duration ); 
		
		$ca_auth = new CA_Auth();
		$ca_auth->auth_token = $tokenData->access_token;
		$ca_auth->expiration = gmdate( 'Y-m-d H:i:s', $expiration->getTimestamp() );
		
		return $ca_auth;
		
	}
	
	private function Check_Last_Auth( $user_id ){
		
		$query = "SELECT Token,Expiration FROM authentications WHERE Authentication = 'CHANNEL ADVISOR' AND User_ID = :user_id ORDER BY Expiration DESC LIMIT 1";
		$query_params = [':user_id'=>$user_id];
		
		$db_result = $this->db_sourcer->RunQuery( $query, $query_params );
		
		if( $db_result->result == null ){
			
			return new CA_Auth();
			
		} else {
			
			$result = $db_result->result[0];
			
			$ca_auth = new CA_Auth();
			$ca_auth->auth_token = $result['Token'];
			$ca_auth->expiration = $result['Expiration'];
			
			return $ca_auth;
			
		}
		
	}
	
}

?>