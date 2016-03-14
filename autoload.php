<?php

function apiClassLoader( $class_name ) {
	
	$class_file = './lib/' . $class_name . '.php';
	if( is_readable( $class_file ) ){
		require $class_file;
	}
	
}

spl_autoload_register( "apiClassLoader" );

?>