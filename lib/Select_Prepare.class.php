<?php

class Select_Prepare{
	
	/**
	 *	PROPERTIES
	 */
	private $default_select;
	private $query_params;
	
	/**
	 *	METHODS
	 */
	public function __construct( $default_select, $query_params ){
		
		$this->default_select = $default_select;
		$this->query_params = $query_params;
		
	}
	
	public function Protect_Passwords(){
		
		$params = $this->query_params;
		
		if( isset( $params['fields'] ) ){
			
			$purges = [];
			
			$select_array = explode( ",", $params['fields'] );
			
			foreach( $select_array as $key=>$value ){
				if( strpos( strtolower( $value ), 'password' ) !== false ){
					
					$purges[] = $key;
					
				}
			}
			
			foreach( $purges as $purge ){
				
				unset( $select_array[$purge] );
			}
			
			$this->query_params['fields'] = implode( ',', array_values( $select_array ));
			
		}
		
		return $this;
		
	}
	
	public function Get_Selects(){
		
		$default = $this->default_select;
		$params = $this->query_params;
		
		if( !isset( $params['fields'] ) ){
			
			return $default;
			
		} else {
			
			if( strpos( $params['fields'], 'password' ) != false ) {
				
				$api_return = new API_Return( 'false', 'Requests selecting password fields are not permitted.' );
		
				return $response->withJson( $api_return, 201 );
				
			} else if( trim( $params['fields'] ) == '*' ) {
				
				return $default;
				
			} else {
				
				return $params['fields'];
				
			}
			
		}
		
	}
	
	
	
}

?>