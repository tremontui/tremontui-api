<?php

class CheckToken{
	
	//private $caConfig;
	private $authHeader;
	
	public function __construct( $ca_ini, $refresh_token, $ca_auth = null ){
		
		//$this->caConfig = new CAConfiguration();
		
		//test AccessTime
		$accessToken = $this->caConfig->AccessToken;
		$accessTime = $this->caConfig->AccessTime;
		$duration = $this->caConfig->AccessDuration;
		
		$durationInterval = new DateInterval( 'PT' . $duration . 'S' );
		
		$now = new DateTime();
		
		if( $accessTime == null || $accessTime == '' || $accessToken == null || $accessToken == '' ){
			
			$refresh = new RefreshAuth( $ca_ini, $refresh_token );
				
				$authHeader = 'Bearer ' . $refresh->Refresh();
				
				$this->authHeader = $authHeader;
			
		} else {
			
			$expireTime = ( new DateTime( $accessTime ) ).add( $durationInterval );
			
			if( $now > $expireTime ){
				
				$refresh = new RefreshAuth();
				
				$authHeader = 'Bearer ' . $refresh->Refresh();
				
				$this->authHeader = $authHeader;
				
			} else {
				
				$authHeader = "Bearer $accessToken";
				
				$this->authHeader = $authHeader;
				
			}
			
		}
		
	}
	
	public function getHeader(){
		
		return $this->authHeader;
		
	}
			
}