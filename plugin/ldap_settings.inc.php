<?php
// Settings file for HESK LDAP Plugin
$hesk_ldap_settings['ldap_version']   = 3;

$hesk_ldap_settings['server_host']   = 'ldap://127.0.0.1';
$hesk_ldap_settings['server_port']   = 389;
$hesk_ldap_settings['base_dn']       = 'dc=example,dc=de';

$hesk_ldap_settings['bind_dn']       = '';
$hesk_ldap_settings['bind_password'] = '';

$hesk_ldap_settings['admin_filter']  = '(&(objectClass=user)(memberOf=cn=admins,cn=groups,cn=accounts,dc=example,dc=de))';
?>