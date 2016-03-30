<?php

class CA_Data{
	
	public $auth;
	public $base_uri;
	public $page = 0;
	public $skip_qty = 100;
	public $data_array = [];
	public $last_data;
	
	public function __construct( $auth, $base_uri ){
		
		$this->auth = $auth;
		$this->base_uri = $base_uri;
		
	}
	
	public function GetAllPages(){
		
		$this->GetPage();
		while( $this->last_data != null ){
			$this->data_array[] = $this->last_data;
			$this->PageData();
			$this->GetPage();
		}
		
		return $this->data_array;
		
	}
	
	public function GetPage(){
		$skip = $this->page * $this->skip_qty;
		$uri = $this->base_uri . '&$skip=' . $skip;
		
		$ca_response = \Httpful\Request::get( $uri )
		->expectsJson()
		->addHeaders( array( 
			'Authorization' => "Bearer " . $this->auth->auth_token
		) )
		->send()->body;
		$ca_array = (array) $ca_response;
		
		if( $ca_array['value'] == null ){
			
			$this->last_data = null;
			return [];
			
		} else {
			
			$this->last_data = $ca_array['value'];
			return $ca_array['value'];
			
		}

	}
	
	private function PageData(){
		
		$this->page += 1;
		
	}
	
}

?>