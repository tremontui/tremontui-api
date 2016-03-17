<?php

class API_Return{
	
	/**
	 *	PROPERTIES
	 */
	public $api_success;
	public $result;
	 
	/**
	 *	METHODS
	 */
	public function __construct( $success, $result ){
		
		$this->api_success = $success;
		$this->result = $result;
		
	}
	
	
}

?>