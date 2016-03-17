<?php

class PDO_Return{
	
	/**
	 *	PROPERTIES
	 */
	public $db_success;
	public $result;
	 
	/**
	 *	METHODS
	 */
	public function __construct( $success, $result ){
		
		$this->db_success = $success;
		$this->result = $result;
		
	}
	
	
}

?>