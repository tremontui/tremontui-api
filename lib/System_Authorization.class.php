<?php

class System_Authorization{
	
	public function __construct(){
		
		
		
	}
	
	public Generate_Token( $bytes ){
		
		return bin2hex( openssl_random_pseudo_bytes( $bytes ) );
		
	}
	
}

?>