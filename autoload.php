<?php

function apiClassLoader( $class_name ) {
	
	$class_file = './lib/' . $class_name . '.class.php';
	if( is_readable( $class_file ) ){
		require $class_file;
	}
	
}

spl_autoload_register( "apiClassLoader" );

?>