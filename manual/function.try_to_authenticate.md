Try to Authenticate
==============
```php
(bool) try_to_authenticate()
```

This method provides automatic detection of the ways a user can provide its credentials. In short, it does ``authenticate($_POST['username'], $_POST['password'])`` and in cases of the [http-module](module.http.md) it also tries to ``authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])``. As a method it is forced to be WITHOUT arguments.
