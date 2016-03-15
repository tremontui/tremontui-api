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
			
			$return = new PDO_Return( TRUE, $_pdo->fetchAll() );
			
		} else {
			
			$return = new PDO_Return( FALSE, $_pdo->errorInfo() );
			
		}
		
		return $return;
		
	}
	
	public function RunQueries( $query_string, array $params_array ){
		
		$_pdo = $this->pdo;

		$p_stmt = $_pdo->prepare( $query_string );
		
		$return_array;
		
		foreach( $params_array as $param ){
			
			if( $p_stmt->execute( $params ) ){
				
				$return_array[] = new PDO_Return( TRUE, $_pdo->fetchAll() );
				
			} else {
				
				$return_array[] = new PDO_Return( FALSE, $_pdo->errorInfo() );
				
			}
			
		}

		return $return_array;
		
	}
	
}

?>