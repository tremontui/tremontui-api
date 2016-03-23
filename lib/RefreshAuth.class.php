<?php

class RefreshAuth{
	
	private $caConfig;
	
	private $appId;
	private $refreshToken;
	private $secret;
	
	public function __construct( $ca_ini, $refresh_token ){
		
		//$this->caConfig = new CAConfiguration();
		
		$this->appId = $ca_ini['app_id'];
		$this->secret = $ca_ini['secret'];
		$this->refreshToken = $refresh_token;
		
	}
	
	public function Refresh(){
		
		$uri_base = 'https://api.channeladvisor.com/oauth2/token';
		
		$response = \Httpful\Request::post( $uri_base )
			->expectsJson()
			->addHeaders( array( 
				'Content-Type' => 'application/x-www-form-urlencoded',
				'Authorization' => 'Basic ' . base64_encode( $this->appId . ':' . $this->secret )
			) )
			->body( 'grant_type=refresh_token&refresh_token=' . $this->refreshToken )
			->send();
			
		$tokenData = $response->body;
		
		$now = new DateTime();
		
		$this->caConfig->AccessToken = $tokenData->access_token;
		$this->caConfig->AccessTime = $now->format( 'Y-m-d\TH:i:s' );
		$this->caConfig->AccessDuration =  $tokenData->expires_in;
		
		return $tokenData->access_token;
		
	}
	
}

?>