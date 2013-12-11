PAM module
========

###Installation
```
$> apt-get install php5-dev php-pear libpam0g
$> pecl install pam
```

####/etc/pam.d/php :
```
@include common-auth
@include common-password
auth    sufficient      /lib/security/pam_unix.so       shadow  nodelay
account sufficient      /lib/security/pam_unix.so
```

####php.ini *or* /etc/php5/conf.d/pam.ini :
```
extension = pam.so
pam.servicename = "php";
```


