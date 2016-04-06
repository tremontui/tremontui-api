<?php

class CA_Data{
	
	public $auth;
	public $base_uri;
	public $next_link;
	public $data_array = [];
	public $last_data;
	
	public function __construct( $auth, $base_uri ){
		
		$this->auth = $auth;
		$this->base_uri = $base_uri;
		$this->next_link = $base_uri;
		
	}
	
	public function GetSinglePage(){
		$uri = $this->base_uri;
		
		$ca_response = \Httpful\Request::get( $uri )
		->expectsJson()
		->addHeaders( array( 
			'Authorization' => "Bearer " . $this->auth->auth_token
		) )
		->send()->body;
		$ca_array = (array) $ca_response;
		
		return $ca_array;
	}
	
	public function GetAllPages(){
		
		$this->GetPage();
		$this->data_array[] = $this->last_data;
		while( $this->next_link != null ){
			$this->GetPage();
			$this->data_array[] = $this->last_data;
		}
		
		return $this->data_array;
		
	}
	
	public function GetPage(){
		$uri = $this->next_link;
		
		$ca_response = \Httpful\Request::get( $uri )
		->expectsJson()
		->addHeaders( array( 
			'Authorization' => "Bearer " . $this->auth->auth_token
		) )
		->send()->body;
		$ca_array = (array) $ca_response;
		
		if( isset( $ca_array['@odata.nextLink'] ) ){
			
			$this->next_link = $ca_array['@odata.nextLink'];
			
		} else {
			
			$this->next_link = null;
			
		}
		
		if( $ca_array['value'] == null ){
			
			$this->last_data = null;
			//return [];
			
		} else {
			
			$this->last_data = $ca_array['value'];
			//return $ca_array['value'];
			
		}

	}
	
}

?>