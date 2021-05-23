# AndersBjorkland Facebook Oauth Extension

Author: Anders Björkland
Contact: contact@andersbjorkland.online

A Bolt CMS extension, the Facebook Oauth Extension allows your admin users to login to the backend with the use of 
Facebook login.

This extension is *not* a plug-and-play solution. You are required to do some configurations with Facebook Developer 
interface, as well as in the security configurations ```config/packages/security.yaml```. It 
also requires you to use same email on your user profile as you have registered with Facebook to be able to log in with 
this service.

On https://developers.facebook.com/ you will have to register an account and create a new app with the *Facebook Login* 
product. Having added this product, go into Facebook Login/Settings. Add **Valid OAuth Redirect URIs** on the form:  
* https://your-domain.com/extensions/facebook-oauth
* https://your-domain.com/extensions/facebook-oauth/check

During development, you can have the following entries: https://127.0.0.1:8000/extensions/facebook-oauth, 
https://127.0.0.1:8000/extensions/facebook-oauth/check

## Installation:

```bash
composer require andersbjorkland/facebook-oauth-extension
```

Configure authentication parameters by adding this authenticator in config/packages/security.yaml.
Do not replace the Bolt configuration.
```yaml
security:
  firewalls:
    main:
      guard:
        authenticators:
          - AndersBjorkland\FacebookOauthExtension\Security\FacebookAuthenticator
        entry_point: AndersBjorkland\FacebookOauthExtension\Security\FacebookAuthenticator
```

## The authentication flow  
The user goes to the URL ``/extensions/facebook-oauth``. This will trigger 
the method *index* in the Controller class at *AndersBjorkland\FacebookOauthExtension\Controller*.  The method will 
redirect the user to Facebook's oauth-endpoint at https://www.facebook.com/v10.0/dialog/oauth. If the user is not 
currently authenticated by Facebook it will open a dialog for the user to login with facebook and approve the extension 
to access their user profile. When the user approves access, or if the user is already authenticated with Facebook, 
Facebook will redirect the user back to the controller.
  
When the controller is hit with the redirect from Facebook, the received Request object will contain a code-parameter. 
To be sure that the code is valid and is not simply an added query parameter to the url a second request will go to 
Facebook to switch it out for an access-token. When the access-token is received, the controller will send the response 
to the route */extensions/facebook-oauth/check*. If you have configured *config/packages/security.yaml* according to the 
instructions above, this will trigger the *FacebookAuthenticator* guard.  
  
The *FacebookAuthenticator* guard will look up the email for a Facebook user with the access-token received 
in the previous step. This email is then used to fetch a User from your database. If your user has registered with same 
email as is used for their Facebook account, then the guard will authenticate the user and log them in to the Bolt 
backend.



## Running PHPStan and Easy Codings Standard

First, make sure dependencies are installed:

```
COMPOSER_MEMORY_LIMIT=-1 composer update
```

And then run ECS:

```
vendor/bin/ecs check src
```
