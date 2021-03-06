<?php

class System_Authentication{
	
	private $pdo_sourcer;
	private $token;
	
	public function __construct( $pdo_sourcer ){
		
		$this->pdo_sourcer = $pdo_sourcer;
		
	}
	
	public function Generate_Token( $bytes ){
		
		$this->token = bin2hex( openssl_random_pseudo_bytes( $bytes ) );
		return $this;
		
	}
	
	public function Get_Token(){
		
		return $this->token;
		
	}
	
	public function Add_Authenticate_Tremont( $user_id ){
		
		//CONFIGS FOR TOKENS
		$token_bytes = 32;
		$duration = new DateInterval( 'PT8H' );
		$authFor = 'TREMONT';
		$now = new DateTime( 'now' );
		$expiration = gmdate( 'Y-m-d H:i:s', $now->add( $duration )->getTimestamp() );
		
		$query = "INSERT INTO authentications (User_ID,Token,Authentication,Expiration) VALUES (:user_id,:token,:authentication,:expiration)";
		$query_params = [
			':user_id'=>$user_id,
			':token'=>$this->Generate_Token( $token_bytes )->Get_Token(),
			':authentication'=>$authFor,
			':expiration'=>$expiration
		];
		
		$db_return = $this->pdo_sourcer->RunQuery( $query, $query_params );

		if( $db_return->db_success == 'true' ){
			
			$return = [
				'token'=>$this->Get_Token(),
				'expires'=>$expiration
			];
			
			return $return;
			
		}
		
	}
	
	public function Authenticate_Tremont( $input ){
		
		$query = "SELECT User_ID,Expiration FROM authentications WHERE Authentication = 'TREMONT' AND Token = :token";
		$query_params = [':token'=>$input];
		
		$db_return = $this->pdo_sourcer->RunQuery( $query, $query_params );
		
		if( $db_return->db_success == 'true' ){
			
			if( $db_return->result == null ){
				
				$return = [
					'success'=>'false',
					'result'=>'Not a valid authentication token.'
				];
				
				return $return;
				
			} else {
				
				//TEST FOR Expiration
				$now_utc = new DateTime( gmdate( 'Y-m-d H:i:s' ) );
				$expires = new DateTime( $db_return->result[0]['Expiration'] );
				
				if( $now_utc > $expires ){
					
					$return = [
						'success'=>'false',
						'result'=>'The given authentication token has expired.'
					];
					
					return $return;
					
				} else {
					
					$return = [
						'success'=>'true',
						'result'=>['User_ID'=>$db_return->result[0]['User_ID']]
					];
					
					return $return;
					
				}
				
			}
			
		}
		
	}
	
}

?>