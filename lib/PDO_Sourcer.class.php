<?php

class PDO_Sourcer{
	
	/**
	 *	PROPERTIES
	 */
	private $pdo;
	 
	/**
	 *	METHODS
	 */
	public function __construct( $conn_string, $username, $password ){
		
		$this->pdo = new PDO( $conn_string, $username, $password );
		
	}
	
	public function RunQuery( $query_string, array $params ){
		
		$_pdo = $this->pdo;

		$p_stmt = $_pdo->prepare( $query_string );
		
		$return;
		
		if( $p_stmt->execute( $params ) ){
			
			$return = new PDO_Return( 'true', $p_stmt->fetchAll(PDO::FETCH_ASSOC) );
			
		} else {
			
			$return = new PDO_Return( 'false', $p_stmt->errorInfo() );
			
		}
		
		return $return;
		
	}
	
	public function RunQueries( $query_string, array $params_array ){
		
		$_pdo = $this->pdo;

		$p_stmt = $_pdo->prepare( $query_string );
		
		$return_array;
		
		foreach( $params_array as $param ){
			
			if( $p_stmt->execute( $param ) ){
				
				$return_array[] = new PDO_Return( 'true', $p_stmt->fetchAll(PDO::FETCH_ASSOC) );
				
			} else {
				
				$return_array[] = new PDO_Return( 'false', $p_stmt->errorInfo() );
				
			}
			
		}

		return $return_array;
		
	}
	
}

?>