Authenticate by Session
======
```php
(bool) authenticate_by_session( (string) $username, (string) $key, [$expires=0], [$method=NULL], [$created=FALSE])
(bool) authenticate_by_session($_SESSION['AUTH_USERNAME'], (array) $_SESSION['AUTH_KEY'][$i])
(bool) authenticate_by_session( (array) $_SESSION['AUTH_KEY'][$i])
```

After a valid [authenticate()](function.authenticate.md) the session will be given an AUTH_USERNAME and AUTH_KEY. Enabling to postulate the user is already authenticated, without the need to do a new *authenticate()* with the remote located method.

###$username
The username you are using to authenticate

###$key
A token (32x 16bit).

###$method
see [authenticate()](function.authenticate.md) for specification of $method

###$expires
The Unix timestamp ``date('U')`` untill when the key is valid.

By default every session is to be assumed to last forever (0), but local settings can force a *validation_length* giving it an expiration date.

###$created
**required** when $expires=0

To tell when the key was made.

###$_SESSION
After a valid authentication you will have a $_SESSION with AUTH_USERNAME and AUTH_KEY[][] (containing the items: key, method?, created?, expires? and username?). In it's alternate form these datablocks will be used to authenticate_by_session.
