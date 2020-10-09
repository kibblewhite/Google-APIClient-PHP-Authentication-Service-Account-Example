<?php

	ini_set( 'display_errors', 1 );
	ini_set( 'display_startup_errors', 1 );
	error_reporting( E_ALL );

	require( 'vendor/autoload.php' );
	require( 'GoogleAuthentication.php' );

	$gauth = GoogleAuthentication::get_instance(
		array( '[[Your GSuite Group Directory Name Here - it can be found in the address bar when you visit gsuite group page]]' ),
		'https://' . $_SERVER['HTTP_HOST'] . '/index.php'
	);

	$gauth->logout();

	$cookie = $_COOKIE;
	echo( print_r( compact( 'cookie' ), true ) . '<br /><br />' . PHP_EOL );
	echo( 'Logged Out - Note: You will still see the old cookie values here until you refresh once more<br />' );
	echo( ' - they are no longer present on the browser and you can double check that the cookies are gone in the browser debug tools<br />' );
	echo( ' - this unfortunately, is just the nature of expired cookies) <a href="">Refresh!</a> - <a href="/">Back to Index</a>' );

?>