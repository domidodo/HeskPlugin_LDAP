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

$_SESSION['isadmin'] = true;

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

  //if (hesk_password_verify($pass, $user_row['pass'])) {
  //  if (hesk_password_needs_rehash($user_row['pass'])) {
  //    $user_row['pass'] = hesk_password_hash($pass);
  //    hesk_dbQuery("UPDATE `".$hesk_settings['db_pfix']."users` SET `pass`='".hesk_dbEscape($user_row['pass'])."' WHERE `id`=".intval($user_row['id']));
  //  }
  //}
  $dbUsers = array();  
  $res = hesk_dbQuery("SELECT id, email, ldap_dn FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."plugin_ldap` ON id=user_id WHERE id <> 1");
  
  while ($dbUser = hesk_dbFetchAssoc($res)){
    array_push($dbUsers, new DbUser($dbUser));
  } 
  
  if ($connect=ldap_connect($hesk_ldap_settings['server_host'], $hesk_ldap_settings['server_port'])) {
    ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, $hesk_ldap_settings['ldap_version']);
    ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);
  
    if ($bind=ldap_bind($connect, $hesk_ldap_settings['bind_dn'], $hesk_ldap_settings['bind_password'])) {


      $sr=ldap_search($connect, $hesk_ldap_settings['base_dn'], $hesk_ldap_settings['admin_filter'], array(
        "dn",
        "uid",
        "mail",
        "displayName",
        "userPassword",
        "nsaccountlock"
      ));
      $contacts = ldap_get_entries($connect, $sr);
    
      $acticDnList = array();
      for ($i=0; $i < $contacts["count"]; $i++)
      {
        $dn = $contacts[$i]["dn"];
        $uid = $contacts[$i]["uid"][0];
        $mail = $contacts[$i]["mail"][0];
        $displayName = $contacts[$i]["displayname"][0];
        $userPassword = hesk_password_hash(generateRandomString());
		$nsaccountlock = $contacts[$i]["nsaccountlock"][0];
		
        if(strtolower($nsaccountlock) == "true" || $nsaccountlock == 1){
	      continue;
        }
        array_push($acticDnList, $dn);

        $user = getUserByDnOrMail($dbUsers, $dn, $mail);

        // Insert User from LDAP
        if($user == null){
          hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."users` (`user`, `pass`, `isadmin`, `name`, `email`, `heskprivileges`) VALUES ('".hesk_dbEscape($uid)."', '".hesk_dbEscape($userPassword)."', '1', '".hesk_dbEscape($displayName)."', '".hesk_dbEscape($mail)."', '')");
          hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."plugin_ldap` (`user_id`, `ldap_dn`) VALUES (" . hesk_dbInsertID() . ", '".hesk_dbEscape($dn)."')");
          hesk_process_messages("Insert user ".$uid." from LDAP", 'NOREDIRECT', 'INFO');
          echo "Insert user ".$uid." from LDAP<br/>";
          continue;
        }
  
        // Connect LDAP with existing DB user
        if($user->ldap_dn == null){
          hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."plugin_ldap` (`user_id`, `ldap_dn`) VALUES (".intval($user->id).", '".hesk_dbEscape($dn)."')");
          hesk_process_messages("Connect existing user with LDAP ".$uid, 'NOREDIRECT', 'INFO');
          echo "Connect user ".$uid."<br/>";
        }
  
        // Update User in DB
        hesk_dbQuery("UPDATE `".$hesk_settings['db_pfix']."users` SET `user`='".hesk_dbEscape($uid)."', `pass`='".hesk_dbEscape($userPassword)."', `name`='".hesk_dbEscape($displayName)."', `email`='".hesk_dbEscape($mail)."' WHERE `id`=".intval($user->id));
        hesk_process_messages("Update user ".$uid, 'NOREDIRECT', 'INFO');
        echo "Update user ".$uid."<br/>";
      }
    
      for ($i=0; $i < count($dbUsers); $i++)
      {
        $dn = $dbUsers[$i]->ldap_dn;
        $id = $dbUsers[$i]->id;
        $email = $dbUsers[$i]->email;
      
        if(!in_array($dn, $acticDnList, true))
        {
          hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`=0 WHERE `owner`='".$id."' AND `status` <> '3'");
	    
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`=".$id);
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."plugin_ldap` WHERE `user_id` =".$id);
            
          /* Delete any user reply drafts */
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `owner`=".$id);
          
          // Clear users' authentication and MFA tokens
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` WHERE `user_id`=".$id);
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens` WHERE `user_id`=".$id);
          hesk_process_messages("Delete user ".$email." from DB", 'NOREDIRECT', 'INFO');
          echo "Delete user ".$email." from DB<br/>";
          
          hesk_updateAutoassignConfigs();
        }
      }
      echo "<br/><br/>Done!";
    } else {
        hesk_process_messages("Wrong LDAP password!", 'NOREDIRECT', 'ERROR');
        echo "Wrong LDAP password!";
    }
  } else {
    hesk_process_messages("No connection to LDAP-Server possible.", 'NOREDIRECT', 'ERROR');
    echo "No connection to LDAP-Server possible.";
  }

  exit();
}

function getUserByDnOrMail($dbUsers, $dn, $mail){
  if($dn != null){
    foreach ($dbUsers as $user) {
      if($user->ldap_dn ==  $dn){
        return $user;
      }
    }
  }

  if($mail != null){
    foreach ($dbUsers as $user) {
      if($user->email ==  $mail){
        return $user;
      }
    }
  }
  
  return null;
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

class DbUser {
  public $id;
  public $email;
  public $ldap_dn;

  function __construct($rs) {
    $this->id = $rs["id"];
    $this->email = $rs["email"];
    $this->ldap_dn = $rs["ldap_dn"];
  }
}

?>
