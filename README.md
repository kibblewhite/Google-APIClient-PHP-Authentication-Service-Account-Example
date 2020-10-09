# Google-APIClient-PHP-Authentication-Service-Account-Example
Using Google's API Client (google/apiclient) for both OAuth 2.0 and Service Account Authentication
This simple project demostrates how to allow users to access a web service or resource, only if they are in a member within a GSuite Group/Directory.

Remember to add your IAM Service Account to the correct Google Workspace Roles.
By going to 'Account > Admin Roles' under 'Group Admin' (https://admin.google.com/ac/roles/)

The 'Admin SDK' API also has to be enabled in the GCP project.
This is classed as an external service, so you should make the OAuth Consent Screen settings for external use.

Besure to correctly create and configure a OAuth 2.0 Client and a Service Account. There's loads of online tutorials on YouTube explaining how.
Set the Authorised Redirect URIs to where ever you plan to redirect to after authentication is completed. Settings are in the gauth-config.php file.

Comments in the code will give you hints into how it all works. Happy reading.
This code was uploaded to share with all and maybe even help a few people, but it comes with absolutely no support, warranty or guarantee.
