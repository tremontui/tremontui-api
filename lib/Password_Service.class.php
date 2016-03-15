<?php

class Password_Service{
	
		/**
	 *	PROPERTIES
	 */
	private $password_hash;
	 
	/**
	 *	METHODS
	 */
	public function __construct(){
		
	}
	
	public function Encrpyt_Password( $raw_pass ){
		
		$this->password_hash = password_hash( $raw_pass, PASSWORD_DEFAULT );
		
	}
	
	public function Compare_Password( $input_pass ){
		
		if( $this->password_hash == null || $this->password_hash == '' ){
			
			return 'Provide a hash to compare against by using ::Store_Hash or ::Encrpyt_Password';
			
		} else {
			
			return password_verify( $input_pass, $this->password_hash );
			
		}
		
	}
	
	public function Store_Hash( $hash ){
		
		$this->password_hash = $hash;
		
	}
	
	public function Get_Hash(){
		
		return $this->password_hash;
		
	}
	
}

?>