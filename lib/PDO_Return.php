<?php

class PDO_Return{
	
	/**
	 *	PROPERTIES
	 */
	public $success;
	public $result;
	 
	/**
	 *	METHODS
	 */
	public function __construct( $success, $result ){
		
		$this->successful = $success;
		$this->result = $result;
		
	}
	
	
}

?>