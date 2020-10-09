<?php

class GoogleAuthentication {

		private static $instance;

		/**
		 *	The service account user defined as `$service_user_to_impersonate` has been given permissions in Google Workspace (formally gsuite)
		 *	By going to 'Account > Admin Roles' under 'Group Admin' (https://admin.google.com/ac/roles/)
		 */
		private $service_user_to_impersonate = 'service-account@external-services.iam.gserviceaccount.com';
		private $cookie_token_key = 'google-token';

		/**
		 *	The following two files are generated from the GCP console: https://console.cloud.google.com/apis/credentials
		 *	Create a 'OAuth 2.0 Client' and 'Service Account' and you will be able to download json files.
		 *	Note:	The 'Service Account' json download file is often only avaiable duing the creation of the account,
		 *			so be sure to keep that file somewhere safe otherwise you will need to re-generate the account to get
		 *			access to that file again. You could also re-create the json file manually. Be sure to double check
		 *			that the IAM Service Account is assigned to the Group Admin role if/where required.
		 */
		private $client_authentication_configuration_filepath = __DIR__ . DIRECTORY_SEPARATOR . 'client_secret.json';
		private $service_authentication_configuration_filepath = __DIR__ . DIRECTORY_SEPARATOR . 'service_account_secret.json';
		private $client;
		private $service_account;
		private $redirect_uri;
		private $reset_uri;
		private $google_service;

		/**
		 *	Constructor for our Google Authentication Class
		 *
		 *	@param array	$groups			Array list of the Google Group we will check for the user in
		 *	@param string	$redirect_uri	Where to send the browser on authentication
		 *	@param string	$reset_uri		(Optional) This is where we send the browser back to if we need to reset the page
		 */
		public static function get_instance( $groups = array(), $service_user_to_impersonate, $redirect_uri, $reset_uri = null ) {
				if ( ! isset( self::$instance ) ) { self::$instance = new self( $groups, $service_user_to_impersonate, $redirect_uri, $reset_uri ); }
				return self::$instance;
		}

		function __construct( $groups, $service_user_to_impersonate, $redirect_uri, $reset_uri ) {

			if ( php_sapi_name() == 'cli' ) { die( 'Only run as a Common Language Infrastructure' ); }
			$this->check_groups_array = $groups;
			$this->reset_uri = empty( $reset_uri ) ? filter_var( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL ) : filter_var( $reset_uri, FILTER_SANITIZE_URL );
			$this->service_user_to_impersonate = empty( $service_user_to_impersonate ) ? $this->service_user_to_impersonate : $service_user_to_impersonate;

			/**
			 *	Here we setup the google client libary and set the authentication configuration via a filepath
			 */
			$this->client = new Google_Client();
			$this->client->setAuthConfig( $this->client_authentication_configuration_filepath );

			/**
			 *	The redirect URI can be any registered URI, but in this example we will redirect back to this same page
			 *	When a user authenticates with Google sucessfully, and upon re-direction back to this page, a query parameter 'code' is added to the URI
			 *	We will collect the 'code' query parameter later.
			 *	IMPORTANT:	The redirect should match the "Authorised Redirect URIs" defined in Google Cloud Platform under 'APIs & Services > Credentials'
			 *				Otherwise the client will recieve > Error 400: redirect_uri_mismatch
			 */
			$this->redirect_uri = empty( $redirect_uri ) ? filter_var( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL ) : filter_var( $redirect_uri, FILTER_SANITIZE_URL );
			$this->client->setRedirectUri( $this->redirect_uri );

			/**
			 *	These are two of the three basic scope avaiable.
			 *	You can define more depending on the services you wish to interact with.
			 */
			$this->client->addScope( 'profile' );
			$this->client->addScope( 'email' );

			/**
			 *	Optional: When the application makes calls to Google's API, we can identify which Application
			 *	is accessing which resource by calling this functions and setting the Application Name
			 */
			$this->client->setApplicationName( 'external-services' );

			/**
			 *	Perform the same but for a service account now for privileged Google Group Lookups
			 *	The 'Admin SDK' API has to be enabled in the GCP
			 */
			putenv( 'GOOGLE_APPLICATION_CREDENTIALS=' . $this->service_authentication_configuration_filepath );
			$this->service_account = new Google_Client();
			$this->service_account->setAuthConfig( $this->service_authentication_configuration_filepath );
			$this->service_account->useApplicationDefaultCredentials();
			$this->service_account->setSubject( $this->service_user_to_impersonate );
			$this->service_account->setApplicationName( 'external-services' );
			$this->service_account->setAccessType( 'offline' );
			$this->service_account->setScopes( array(
				Google_Service_Directory::ADMIN_DIRECTORY_GROUP_READONLY,
				Google_Service_Directory::ADMIN_DIRECTORY_GROUP_MEMBER_READONLY,
				Google_Service_Directory::ADMIN_DIRECTORY_USER_READONLY
			) );

			$this->google_service = new Google_Service_Directory( $this->service_account );

		}

		/**
		 *	This function will check if the user attempting to login
		 *	is a member of any of the defined groups `$check_groups_array`
		 *	https://github.com/googleapis/google-api-php-client-services/blob/master/src/Google/Service/Directory/Resource/Members.php
		 */
		public function email_check_group( $email ) {

			$optParams = array(
				'maxResults' => 200,
				'includeDerivedMembership' => true
			);

			$member_email_array = array();
			foreach( $this->check_groups_array as $check_group ) {
				$results = $this->google_service->members->listMembers( $check_group, $optParams );
				$members = $results->getMembers();
				if ( count( $members ) == 0 ) { continue; }
				foreach ( $members as $member ) { array_push( $member_email_array, $member->email ); }
			}

			return in_array( $email, $member_email_array );

		}

		/**
		 *	On pages that you know you will be dealing with return codes from
		 *	Google, you will need to validate the code by verifying a token.
		 *	There are many ways to handle this, in this example we could
		 *	re-direct the browser to any part of the site.
		 */
		public function redirect_with_valid_google_code() {

			/**
			 *	If the code query parameter is set, perform the following operations within the IF statement
			 */
			if ( isset( $_GET['code'] ) && !empty( $_GET['code'] ) ) {

					// Get the code from the query parameters
					$google_code = filter_var( $_GET['code'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );

					// Using the code, fetch our user's Access Token from Google - this basically performs a second call back to Google Authentication
					$token = $this->client->fetchAccessTokenWithAuthCode( $google_code );

					// Verify that we are actually who we say we are...
					$payload = $this->client->verifyIdToken( $token['id_token'] );

					// If $payload was empty or null, then verification failed, do not persist token
					if ( !empty( $payload ) ) {
							// To persist the token, we should save it into our session cookies, in this example, when we close the browser tab, this cookie will be cleared
							setcookie( $this->cookie_token_key, json_encode( $token ) );

							// This is for debugging purposes only to check the verfication payload - comment this out when done for security purposes
							setcookie( 'verify_token_payload', json_encode( $payload ) );
					}

					// Before the page fully loads, we should either redirect to a user logged-in only page, or back to here to display the logged-in only sections of this page
					header( 'Location: ' . $this->reset_uri );
			}

		}

		/**
		 *	Loggin out should unset the cookies by forcing the expiry on the browser
		 *	If your browser is ever experiencing issues, try sending them here...
		 */
		public function logout() {
			$token = isset( $_COOKIE[ $this->cookie_token_key ] ) ? $_COOKIE[ $this->cookie_token_key ] : null;
			$this->client->revokeToken( $token );
			setcookie( $this->cookie_token_key, NULL, time() - 3600 );
			setcookie( 'verify_token_payload', NULL, time() - 3600 );
		}

		/**
		 * Redirect the browser to the Google Authentication Page
		 */
		public function login() {
			$auth_url = filter_var( $this->client->createAuthUrl(), FILTER_SANITIZE_URL );
			header( 'Location: ' . $auth_url );
		}

		/**
		 *	Return the Google Authentication URL
		 */
		public function generate_login_url() {
			$auth_url = filter_var( $this->client->createAuthUrl(), FILTER_SANITIZE_URL );
			return $auth_url;
		}

		/**
		 *	Once you have recieved the code and the browser is authenticated, you can check at anytime if the browser is authenticated by calling this function
		 */
		public function check_authentication( $auto_login = true ) {

			if ( empty( $_COOKIE[ $this->cookie_token_key ] ) ) {
				// If there is no token, redirect the browser to the google authentication page
				$auth_url = filter_var( $this->client->createAuthUrl(), FILTER_SANITIZE_URL );
				if ( $auto_login ) { header( 'Location: ' . $auth_url ); }
				return NULL;
			}

			// Collect our token from the browser's cookies. If the `id_token` has been set to NULL, then return NULL
			$token = json_decode( $_COOKIE[ $this->cookie_token_key ], JSON_OBJECT_AS_ARRAY );
			if ( !isset( $token['id_token'] ) || empty( $token['id_token'] ) ) { return NULL; }

			// Once we have our token, we will set this access token as a variable avaiable to the current user's browser DOM during session only
			$this->client->setAccessToken( $token );
			$id_token = $this->client->getAccessToken();

			// Verify the token we collected from the client's browser, if the verification fails i.e payload is empty, then return NULL
			$payload = $this->client->verifyIdToken( $id_token['id_token'] );
			if ( empty( $payload ) ) { return NULL; }

			// Use the payload content to get the current logged in user email address and check if the user is in one of our defined Google groups
			if ( $this->email_check_group( $payload['email'] ) === FALSE ) {
				$this->client->revokeToken();
				// If we do not find our user, set the browser's cookie `id_token` to NULL
				setcookie( $this->cookie_token_key, json_encode( array( 'id_token' => NULL ) ) );
				if ( $auto_login ) { header( 'Location: ' . $this->reset_uri ); }
				return NULL;
			}			

			if ( $this->client->isAccessTokenExpired() ) {
				// If token has expired, reset the cookie, and re-visit the google authentication page
				setcookie( $this->cookie_token_key, NULL, time() - 3600 );
				$auth_url = filter_var( $this->client->createAuthUrl(), FILTER_SANITIZE_URL );
				if ( $auto_login ) { header( 'Location: ' . $auth_url ); }
				return NULL;
			}

			return $payload;

		}
}

?>