# HeskPlugin_LDAP


    For Debian, the installation command would be apt-get install php-ldap
    For RHEL based systems, the command would be yum install php-ldap

Search for extension=php_ldap.so in php.ini file. Uncomment this line, if not present then add this line in the file and save the file.
/etc/php/[verion]/apache2/php.ini
sudo service apache2 restart