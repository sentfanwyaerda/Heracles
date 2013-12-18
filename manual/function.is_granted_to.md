Is Granted To
=========
```php
(bool) is_granted_to((string) $priviledge, [$object=FALSE], [$method=TRUE])
```

This method checks if the user is granted the priviledge to apply an action regarding a specific object, or in general (when not specified).

###$priviledge
A well known action within the namespace of *$method*, like *read*, *write*, *create*, *delete* of files, or within the namespace of the (object) *$object*.

###$object
- **(string) $object**; a reference to the particular object, like a path to a file or an id of the object. $method is required to be specified or be derivable.
- **(object) $object**: the $priviledge is relative to the object itself. The object provides ``$object->get_id()`` and ``$object->is_granted_to($priviledge)``. Also $method is considered to be irrelevant.
- **$object=FALSE**: the $priviledge is global/unspecified

###$method
see [Authenticate()](function.authenticate.md)'s section about $method

##within the [PAM](module.pam.md) module:
```php
(bool) is_granted_to([read,write,execute], $path, "pam")
```

PAM is the way you authenticate on your unix-server. As such **is_granted_to** will check if the user has priviledges to read/write/execute the file. When the file does not exist, it checks its directory for the rights to *create* (write) the file, also in the case of *delete* (write+execute).

This will also need to mirror the 'user in groups' part of providing priviledges.
