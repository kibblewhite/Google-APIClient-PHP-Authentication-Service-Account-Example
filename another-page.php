<?php

	ini_set( 'display_errors', 1 );
	ini_set( 'display_startup_errors', 1 );
	error_reporting( E_ALL );

	require( 'vendor/autoload.php' );
	require( 'GoogleAuthentication.php' );
	require( 'gauth-config.php' );

	$gauth = GoogleAuthentication::get_instance(
		$groups_array,
		$service_user_to_impersonate,
		$url_path_to_login
	);

	$data = $gauth->check_authentication();

	if ( $data === NULL ) {
		$auth_url = $gauth->generate_login_url();
		die( 'User can not be authenticated: <a href="' . $auth_url .'">Login</a>' );
	}

	$cookie = $_COOKIE;
	echo( print_r( compact( 'data', 'cookie' ), true ) . '<br /><br />'  . PHP_EOL );
	echo( 'This can only be viewed by logged in accounts. <a href="logout.php">Log Out</a>' );

?>