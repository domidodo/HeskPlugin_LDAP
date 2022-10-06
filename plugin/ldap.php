<?php
/**
 *
 * This file is a unofficial part of HESK - PHP Help Desk Software.
 *
 * PHP Help Desk Software:
 * https://www.hesk.com
 *
 */

define('IN_SCRIPT',1);
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'plugin/ldap_settings.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();

do_initTable();
do_LdapSync();

exit();


/*** START FUNCTIONS ***/
function do_initTable()
{
  global $hesk_settings;
  
hesk_dbQuery("
CREATE TABLE IF NOT EXISTS `".hesk_dbEscape($hesk_settings['db_pfix'])."plugin_ldap` (
  `user_id` mediumint(8) unsigned NOT NULL,
  `ldap_dn` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user_id`, `ldap_dn`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

}

function do_LdapSync()
{
  global $hesk_settings, $hesk_ldap_settings, $hesklang;
    
  if ($connect=ldap_connect($hesk_ldap_settings['server_host'], $hesk_ldap_settings['server_port'])) {
      ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, $hesk_ldap_settings['ldap_version']);
      ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);

      if ($bind=ldap_bind($connect, $hesk_ldap_settings['bind_dn'], $hesk_ldap_settings['bind_password'])) {

        $sr=ldap_search($connect, $hesk_ldap_settings['base_dn'], $hesk_ldap_settings['admin_filter'], array("mail"));
        $contacts = ldap_get_entries($connect, $sr);

        for ($i=0; $i < $contacts["count"]; $i++)
        {
          echo $contacts[$i]["mail"][0];
          echo "<br/>";
        }
      } else {
          echo "Wrong password!";
      }
  } else {
      echo "No connection to LDAP-Server possible.";
  }

  exit();
}
?>
