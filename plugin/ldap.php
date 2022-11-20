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

// Do we require a key if not accessed over CLI?
hesk_authorizeNonCLI();

$_SESSION['isadmin'] = true;

hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();

if(!function_exists('getLdapPluginPatchVersion')){
  do_patchFiles();
  do_initTable();
}
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

function do_patchFiles()
{
  $patchFilePath = HESK_PATH.'admin/index.php';
  $code=file_get_contents($patchFilePath);
  $code=str_replace("hesk_password_verify(\$pass, \$user_row['pass'])", "hesk_password_verify(\$pass, \$user_row['pass'], intval(\$user_row['id']))",$code);
  file_put_contents($patchFilePath, $code);

  
  $patchFilePath = HESK_PATH.'inc/admin_functions.inc.php';
  $code=file_get_contents($patchFilePath);
  $code=str_replace(" hesk_password_verify(", " local_hesk_password_verify(",$code);
  $code=str_replace(" hesk_password_needs_rehash(", " local_hesk_password_needs_rehash(",$code);
  $code=str_replace("<?php", "<?php\nrequire(HESK_PATH . 'plugin/ldap_settings.inc.php');",$code);
  file_put_contents($patchFilePath, $code);
  
  $code=file_get_contents(HESK_PATH.'plugin/ldap_patch.php');
  $code=str_replace("<?php", "",$code);
  file_put_contents($patchFilePath, $code, FILE_APPEND);
  
  return true;
}

function do_LdapSync()
{
  global $hesk_settings, $hesk_ldap_settings;

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
        $ldapUsr = $contacts[$i];
        $dn = $ldapUsr["dn"];
        $uid = $ldapUsr["uid"][0];
        $mail = $ldapUsr["mail"][0];
        $displayName = $ldapUsr["displayname"][0];
        $userPassword = hesk_password_hash(generateRandomString());
        if(array_key_exists("nsaccountlock", $ldapUsr)){
          $nsaccountlock = $ldapUsr["nsaccountlock"][0];
          if(strtolower($nsaccountlock) == "true" || $nsaccountlock == 1){
            continue;
          }
        }    
        array_push($acticDnList, $dn);

        $user = getUserByDnOrMail($dbUsers, $dn, $mail);

        // Insert User from LDAP
        if($user == null){
          hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."users` (`user`, `pass`, `isadmin`, `name`, `email`, `heskprivileges`) VALUES ('".hesk_dbEscape($uid)."', '".hesk_dbEscape($userPassword)."', '1', '".hesk_dbEscape($displayName)."', '".hesk_dbEscape($mail)."', '')");
          hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."plugin_ldap` (`user_id`, `ldap_dn`) VALUES (" . hesk_dbInsertID() . ", '".hesk_dbEscape($dn)."')");
          showDebugText("Insert user ".$uid." from LDAP", 'INFO');
          continue;
        }
  
        // Connect LDAP with existing DB user
        if($user->ldap_dn == null){
          hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."plugin_ldap` (`user_id`, `ldap_dn`) VALUES (".intval($user->id).", '".hesk_dbEscape($dn)."')");
          showDebugText("Connect user ".$uid, 'INFO');
        }
  
        // Update User in DB
        hesk_dbQuery("UPDATE `".$hesk_settings['db_pfix']."users` SET `user`='".hesk_dbEscape($uid)."',`name`='".hesk_dbEscape($displayName)."', `email`='".hesk_dbEscape($mail)."' WHERE `id`=".intval($user->id));
        showDebugText("Update user ".$uid, 'INFO');
      }
    
      for ($i=0; $i < count($dbUsers); $i++)
      {
        $dn = $dbUsers[$i]->ldap_dn;
        $id = $dbUsers[$i]->id;
        $email = $dbUsers[$i]->email;
      
        if($dn != null && !in_array($dn, $acticDnList, true))
        {
          hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`=0 WHERE `owner`='".$id."' AND `status` <> '3'");
      
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`=".$id);
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."plugin_ldap` WHERE `user_id` =".$id);
            
          /* Delete any user reply drafts */
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `owner`=".$id);
          
          // Clear users' authentication and MFA tokens
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` WHERE `user_id`=".$id);
          hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens` WHERE `user_id`=".$id);
          showDebugText("Delete user ".$email." from DB", 'INFO');
          
          hesk_updateAutoassignConfigs();
        }
      }
      echo "Done!";
    } else {
        showDebugText("Wrong LDAP password!", "ERROR", true);
    }
  } else {
    showDebugText("No connection to LDAP-Server possible.", "ERROR", true);
  }

  exit();
}

function showDebugText($txt, $logLvl, $ignoreDebugMode = false){
  global $hesk_settings;
  
  if($ignoreDebugMode || $hesk_settings['debug_mode'])
  {
    echo $txt;
    echo "<br/>";
	hesk_process_messages($txt, 'NOREDIRECT', $logLvl);
  }
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
