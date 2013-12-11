Authenticate
============
```php
(bool) authenticate( (string) $username, (string) $password, [(string) $method=TRUE] )
```

``authenticate()`` will return TRUE or FALSE on the validation of the credentials $username and $password

###$method
By default it is set to TRUE, which will authenticate the credentials against all (configured) available methods.

You could use:

- [PAM](module-pam.md) (authenticate against the local accounts on linux)
- [HTTP](module-http.md) (to use the <em>401</em> input of the authentication request)
- ...
