<?php

	ini_set( 'display_errors', 1 );
	ini_set( 'display_startup_errors', 1 );
	error_reporting( E_ALL );

	/**
	 *	First step, if you haven't already, is to include our
	 *	libary built by composer using the autoload.php file
	 *	The command used was:
	 *	`composer require google/apiclient`
	 */
	require( 'vendor/autoload.php' );

	/**
	 *	Include the Google Authentication Class file
	 */
	require( 'GoogleAuthentication.php' );

	/**
	 *	Init GoogleAuthentication
	 *
	 *	@param array	$groups			Array list of the Google Group we will check for the user in
	 *	@param string	$redirect_uri	Where to send the browser on authentication
	 */
	$gauth = GoogleAuthentication::get_instance(
		array( '[[Your GSuite Group Directory Name Here]]' ),
		'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']
	);

	/**
	 *	Notes for these functions are within the class file.
	 *	Please read them thoroughly, thanks.
	 */
	$gauth->redirect_with_valid_google_code();
	$data = $gauth->check_authentication( true );
	
	if ( $data === NULL ) {
		$auth_url = $gauth->generate_login_url();
		die( 'User can not be authenticated: <a href="' . $auth_url .'">Login</a>' );
	}

	$cookie = $_COOKIE;
	echo( print_r( compact( 'data', 'cookie' ), true ) . '<br /><br />'  . PHP_EOL );

	echo( '<a href="another-page.php">Another Page</a>' );

?>