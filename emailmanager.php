<?php
/**
 * @package cPanel E-Mail Manager
 */

/*
Plugin Name: cPanel E-Mail Manager
Plugin URI: http://www.insyncbiztech.com/
Description: cPanel E-Mail Manager is a WordPress plugin that integrates with the POP3 e-mail functionality of cPanel allowing the creation and deletion of e-mail accounts for WordPress users.
Version: 1.2
Author: InSync Business Technologies, LLC
Author URI: http://www.insyncbiztech.com/
License: GPLv2 or later
Text Domain: emailmanager
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/** MINIMUM REQUIREMENTS:
* PHP 5
* MYSQL 5
* CPANEL 11.32
*/

/** TESTED WITH:
* PHP 5.2.17
* MYSQL 5.5.33-31.1
* CPANEL 11.38.2
*/

if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

//ini_set('display_errors', 1);
//error_reporting(E_ALL);

define('EMAILMGR_SCRIPT_PATH', realpath(dirname(__FILE__)));
define('EMAILMGR_ENCRYPT_SALT', 'zEwA4a$u'); //CHANGE THIS BEFORE FIRST USE!!! 
//Note: Changing this value will result in all usernames/passwords encrypted with this salt to become invalid.

if (!class_exists('xmlapi', false)) {
	if (file_exists(EMAILMGR_SCRIPT_PATH.'/cpanelapi.php')) {
		include_once EMAILMGR_SCRIPT_PATH.'/cpanelapi.php';
	} else {
		die("The required cpanelapi.php file cannot be found.");
	}
}
if (file_exists(EMAILMGR_SCRIPT_PATH.'/advanced.php')) {
	include_once EMAILMGR_SCRIPT_PATH.'/advanced.php';
	define('EMAILMGR_STD_MODE', 0);
} else {
	define('EMAILMGR_STD_MODE', 1);
	define('EMAILMGR_CPANEL_HIDE', 0);
	define('EMAILMGR_CPANEL_GLOBAL', 0);
	define('EMAILMGR_CPANEL_USERNAME', "");
	define('EMAILMGR_CPANEL_PASSWORD', "");
	define('EMAILMGR_CPANEL_SERVER', "");
}

/*
////////////// MODULE CONFIGURATION OPTIONS //////////////
*/

register_activation_hook( __FILE__, 'emailmanager_activate' );
register_deactivation_hook( __FILE__, 'emailmanager_deactivate' );

/*
////////////// LOAD MODULE HOOKS //////////////
*/

add_action('admin_menu', 'emailmanager_menu');
add_action('delete_user', 'emailmanager_remove_user'); 
add_action('show_user_profile', 'emailmanager_user_change_password_display');
add_action('personal_options_update', 'emailmanager_user_change_password');
if (!is_multisite()) {
	add_action('wp_dashboard_setup', 'emailmanager_add_dashboard_widget');
}

/*
////////////// CORE MODULE FUNCTIONS //////////////
*/

function emailmanager_activate() {
	global $wpdb;
	if (function_exists('is_multisite') && is_multisite()) {
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			$old_blog = $wpdb->blogid;
			// Get all blog ids
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				_emailmanager_activate();
			}
			switch_to_blog($old_blog);
			return;
		}   
	} 
	_emailmanager_activate();  
}

function _emailmanager_activate() {
	global $wpdb;
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_autogen", "option_value"=>0));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_groups", "option_value"=>"Administrator"));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_domain", "option_value"=>""));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_quota", "option_value"=>250));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_webmailurl", "option_value"=>""));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_cpaneluser", "option_value"=>""));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_cpanelpass", "option_value"=>""));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_cpanelip", "option_value"=>"localhost"));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_emailmsg", "option_value"=>"Greetings {\$user},\n\nYour e-mail account {\$email} has been activated and is ready for use!\n\nTemporary Password: {\$temppw}\n\nNote: For security reasons we recommend changing your password upon first login.\n\nYou can access your e-mail account here: {\$server}\n\nThank you!"));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_aliasformat", "option_value"=>"first.last"));
	$wpdb->insert($wpdb->options, array("option_name"=>"emailmgr_sendemail", "option_value"=>0));
}

function emailmanager_deactivate() {
	global $wpdb;
	if (function_exists('is_multisite') && is_multisite()) {
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			$old_blog = $wpdb->blogid;
			// Get all blog ids
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				_emailmanager_deactivate();
			}
			switch_to_blog($old_blog);
			return;
		}   
	} 
	_emailmanager_deactivate();  
}

function _emailmanager_deactivate() {
	global $wpdb;
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_autogen"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_groups"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_domain"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_quota"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_webmailurl"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_cpaneluser"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_cpanelpass"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_cpanelip"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_emailmsg"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_aliasformat"));
	$wpdb->delete($wpdb->options, array("option_name"=>"emailmgr_sendemail"));
}

function emailmanager_menu() {
	add_users_page('E-Mail Manager', 'E-Mail Manager', 'read', 'emailmanager', 'emailmanager_output');
}

function emailmanager_create_email($user_id, $firstname, $lastname, $username, $email, $customformat = null, $customalias = null, $customquota = null) {
	//Set config options
	$emaildomain = emailmanager_config_options("emailmgr_domain");
	$emailquota = (!is_null($customquota) ? $customquota : emailmanager_config_options("emailmgr_quota"));
	$webmailurl = emailmanager_config_options("emailmgr_webmailurl");
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
	$emailmsg = emailmanager_config_options("emailmgr_emailmsg");
	$aliasformat = (!is_null($customformat) ? $customformat : emailmanager_config_options("emailmgr_aliasformat"));
	$sendemail = emailmanager_config_options("emailmgr_sendemail");
	$emailalias = emailmanager_assign_alias($firstname, $lastname, $username, $email, $customformat, $customalias);

	//ADD USER TO CPANEL
	$randpass = emailmanager_generatePassword();
	$optional['email_domain'] = $emaildomain;
	$optional['email_user'] = $emailalias;
	$optional['email_pass'] = $randpass;
	$optional['email_quota'] = $emailquota;

	$newemail = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "createemail", $optional));

	if ($newemail->data->result == 1) {
		global $wpdb;
		update_user_option( $user_id, 'emailmgr_alias', $emailalias );

		$options['user'] = ($firstname != "" ? $firstname : "User");
		$options['email'] = $emailalias . "@" . $emaildomain;
		$options['temppw'] = $randpass;
		$options['server'] = $webmailurl;

		if ($sendemail) {
			emailmanager_sendEmail($email, "E-Mail Account Activated", $emailmsg, $options);
		}
	}
}

function emailmanager_remove_user($user_id) {
	global $wpdb;
	//Set config options
	$emailalias = emailmanager_usermeta($user_id, $wpdb->prefix . "emailmgr_alias");
	emailmanager_remove_email($emailalias);
	delete_user_option( $user_id, "emailmgr_alias" );
}

function emailmanager_remove_email($emailalias) {
	//Set config options
	$emaildomain = emailmanager_config_options("emailmgr_domain");
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");

	//REMOVE USER FROM CPANEL
	$optional['email_domain'] = $emaildomain;
	$optional['email_user'] = $emailalias;

	emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "deleteemail", $optional);

	global $wpdb;
	$user_id = emailmanager_emailalias_userid($emailalias);
	delete_user_option( $user_id, "emailmgr_alias" );
}

function emailmanager_add_dashboard_widget() {
	wp_add_dashboard_widget(
                 'emailmanager_dashboard_widget',         // Widget slug.
                 'cPanel E-Mail Manager Statistics',         // Title.
                 'emailmanager_dashboard_widget_function' // Display function.
        );	
}

/*
////////////// MODULE OUTPUT //////////////
*/

function emailmanager_output() {
	global $wpdb;
	$output = "<h1>cPanel E-Mail Manager " . emailmanager_version() . "</h1>";
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
	$emaildomain = emailmanager_config_options("emailmgr_domain");
	$blogdomain = "";
	if ($cpaneluser != "" && $cpanelpass != "" && $cpanelserver != "") {
		if (function_exists('is_multisite') && is_multisite() && $_GET["action"] != "createdns") {
			$domains = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "listemaildomains"));
			$domainfound = 0;
			$blogdomain = $wpdb->get_var( $wpdb->prepare( 
				"
				SELECT domain 
				FROM $wpdb->blogs
				WHERE blog_id = %d
				", 
				get_current_blog_id()
			) );
			foreach ($domains->data as $domain) {
				if ($domain->domain == $blogdomain) {
					$domainfound = 1;
					break;
				}
			}
			if ($domainfound == 0) {
				echo '<h2 style="display:inline;">Configuration Options</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">There are currently no domains configured in cPanel for this WordPress MU site. Please contact your server administrator for assistance or click the button below to create a new DNS entry for this domain.</p>
				<p>&nbsp;</p>
				<form action="users.php?page=emailmanager&mode=config&action=createdns" name="updatedns" id="updatedns" action="" method="post">
				<input type="hidden" name="emaildomain" id="emaildomain" value="' .  $blogdomain . '">
				<p align="center"><input type="submit" value="Create DNS Entry" class="button button-primary"></p>
				</form>';
				exit;
			}
		}
	}
	if (isset( $_GET['mode'] ) && !empty( $_GET['mode'] )) {
		if ($_GET["mode"] == "list") {
			$output .= emailmanager_output_list();
		}
		if ($_GET["mode"] == "add") {
			$output .= emailmanager_output_adduser();
		}
		if ($_GET["mode"] == "mailinglist") {
			if (EMAILMGR_STD_MODE == 0) {
				$output .= emailmanager_output_mailinglist();
			} else {
				$output .= emailmanager_output_upgradeplugin();
			}
		}
		if ($_GET["mode"] == "managelist") {
			if (EMAILMGR_STD_MODE == 0) {
				$output .= emailmanager_output_managelist();
			} else {
				$output .= emailmanager_output_upgradeplugin();
			}
		}
		if ($_GET["mode"] == "upgradeplugin") {
			$output .= emailmanager_output_upgradeplugin();
		}
		if ($_GET["mode"] == "config") {
			$output .= emailmanager_output_config($blogdomain);
		}
		if ($_GET["mode"] == "linkaccounts") {
			$output .= emailmanager_output_linkexisting();
		}
	} else {
		if ($cpaneluser != "" && $cpanelpass != "" && $cpanelserver != "" && $emaildomain != "") {
			$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "diskusage"));
			if ($accountinfo->data->max != "") {
				$output .= emailmanager_output_list();
			} else {
				$output .= emailmanager_output_config($blogdomain);
			}
		} else {
			$output .= emailmanager_output_config($blogdomain);
		}
	}
echo $output;
}

function emailmanager_output_config($mudomain = null) {
	global $wpdb;
	$output = '';
	if (!empty($_POST)) {
		if ($_GET["action"] == "createdns") {
			//Set config options
			$emaildomain = emailmanager_config_options("emailmgr_domain");
			$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
			$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
			$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");

			$optional['domain'] = $_POST['emaildomain'];
			$result = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "addparkeddomain", $optional));		
			if ($result->data->result == 1) {
				return '<h2 style="display:inline;">Configuration Options</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">The DNS entry for ' . $_POST['domain'] . ' has been successfully added!</p><p style="text-align:center; font-weight: bold;font-size: 16px;">Continue to <a href="users.php?page=emailmanager&mode=config">Configuration Options</a>.</p>';
				exit;
			} else {
				$error = emailmanager_resultLogging("Park Domain", (!isset($result->data->reason) ? "An unspecified error has occurred while trying to add a parked domain for " . $_POST['domain']  . "." : $result->data->reason));
				return '<h2 style="display:inline;">Configuration Options</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">' . $error . '</p><p style="text-align:center; font-weight: bold;font-size: 16px;">Please contact your server administrator for assistance.</p>';
				exit;
			}
		} else {
			//Process form submission
			//Set form results
			if ($_GET["action"] == "setcpanel") {
				$cpaneluser = (isset($_POST['cpaneluser']) ? $_POST['cpaneluser'] : "");
				$cpanelpass = (isset($_POST['cpanelpass']) ? $_POST['cpanelpass'] : "");
				$cpanelserver = (isset($_POST['cpanelserver']) ? $_POST['cpanelserver'] : "");
				if ($cpaneluser != "") {
					emailmanager_update_config("emailmgr_cpaneluser", $cpaneluser);
				}
				if ($cpanelpass != "") {
					emailmanager_update_config("emailmgr_cpanelpass", $cpanelpass);
				}
				if ($cpanelserver != "") {
					emailmanager_update_config("emailmgr_cpanelip", $cpanelserver);
				}
				$domainfound = 0;
				$blogdomain = "";
				if (function_exists('is_multisite') && is_multisite()) {
					$domains = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "listemaildomains"));
					$blogdomain = $wpdb->get_var( $wpdb->prepare( 
						"
						SELECT domain 
						FROM $wpdb->blogs
						WHERE blog_id = %d
						", 
						get_current_blog_id()
					) );
					foreach ($domains->data as $domain) {
						if ($domain->domain == $blogdomain) {
							$domainfound = 1;
							break;
						}
					}
					if ($domainfound == 0 && !empty($blogdomain)) {
						echo '<h1>cPanel E-Mail Manager</h1><h2 style="display:inline;">Configuration Options</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">There are currently no domains configured in cPanel for this WordPress MU site. Please contact your server administrator for assistance or click the button below to create a new DNS entry for this domain.</p>
						<p>&nbsp;</p>
						<form action="users.php?page=emailmanager&mode=config&action=createdns" name="updatedns" id="updatedns" action="" method="post">
						<input type="hidden" name="emaildomain" id="emaildomain" value="' .  $blogdomain . '">
						<p align="center"><input type="submit" value="Create DNS Entry" class="button button-primary"></p>
						</form>';
						exit;
					}
				}
			} else {
				$autogenerate = (isset($_POST['autogenerate']) ? $_POST['autogenerate'] : 0);
				$wordpressgroups = (isset($_POST['wordpressgroups']) ? implode(",", $_POST['wordpressgroups']) : "");
				$emaildomain = (isset($_POST['emaildomain']) ? $_POST['emaildomain'] : "");
				$webmailurl = (isset($_POST['webmailurl']) ? $_POST['webmailurl'] : "");
				if ($webmailurl == "") {
					$webmailurl = (isset($_POST['emaildomain']) ? "mail." . $_POST['emaildomain'] : "");
				}
				$emailquota = (isset($_POST['emailquota']) ? $_POST['emailquota'] : 250);	
				$cpaneluser = (isset($_POST['cpaneluser']) ? $_POST['cpaneluser'] : "");
				$cpanelpass = (isset($_POST['cpanelpass']) ? $_POST['cpanelpass'] : "");
				$cpanelserver = (isset($_POST['cpanelserver']) ? $_POST['cpanelserver'] : "");
				$emailmsg = (isset($_POST['emailmsg']) ? $_POST['emailmsg'] : "");
				$aliasformat = (isset($_POST['aliasformat']) ? $_POST['aliasformat'] : "");
				$sendemail = (isset($_POST['sendemail']) ? $_POST['sendemail'] : 0);

				//Update database
				emailmanager_update_config("emailmgr_autogen", $autogenerate);
				if ($wordpressgroups != "") {
					emailmanager_update_config("emailmgr_groups", $wordpressgroups);
				}
				emailmanager_update_config("emailmgr_domain", $emaildomain);
				emailmanager_update_config("emailmgr_quota", $emailquota);
				emailmanager_update_config("emailmgr_webmailurl", $webmailurl);
				if ($cpaneluser != "") {
					emailmanager_update_config("emailmgr_cpaneluser", $cpaneluser);
				}
				if ($cpanelpass != "") {
					emailmanager_update_config("emailmgr_cpanelpass", $cpanelpass);
				}
				if ($cpanelserver != "") {
					emailmanager_update_config("emailmgr_cpanelip", $cpanelserver);
				}
				if ($emailmsg != "") {
					emailmanager_update_config("emailmgr_emailmsg", $emailmsg);
				}
				if ($aliasformat != "") {
					emailmanager_update_config("emailmgr_aliasformat", $aliasformat);
				}
				emailmanager_update_config("emailmgr_sendemail", $sendemail);	
			}
			$output .= '<div class="updated">
			<p>Configuration options have been updated!</p>
			</div><br>';
		}
	}
	//Set config options
	$autogenerate = (emailmanager_config_options("emailmgr_autogen") ? " checked" : "");
	$wordpressgroups = emailmanager_config_options("emailmgr_groups");
	$emaildomain = emailmanager_config_options("emailmgr_domain");
	$emailquota = emailmanager_config_options("emailmgr_quota");
	$webmailurl = emailmanager_config_options("emailmgr_webmailurl");
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
	$emailmsg = emailmanager_config_options("emailmgr_emailmsg");
	$aliasformat = emailmanager_config_options("emailmgr_aliasformat");
	$sendemail = (emailmanager_config_options("emailmgr_sendemail") ? " checked" : "");

	if ($cpaneluser != "" && $cpanelpass != "" && $cpanelserver != "") {
		$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "diskusage"));
		if ($accountinfo->data->max != "") {
			$output .= '<h2 style="display:inline;">Configuration Options</h2> - <a href="users.php?page=emailmanager&mode=list">List of Accounts</a> | <a href="users.php?page=emailmanager&mode=linkaccounts">Link Existing Accounts</a> | <a href="users.php?page=emailmanager&mode=add">Add New Account</a> | <a href="users.php?page=emailmanager&mode=mailinglist">Manage Mailing Lists</a>' . (EMAILMGR_STD_MODE == 1 ? ' | <a href="users.php?page=emailmanager&mode=upgradeplugin">Upgrade Plugin</a>' : '');
			$output .= '<script type="text/javascript">
			function submitform() {
			var message = "The following fields are required:\n\n";
			if (document.getElementById("wordpressgroups").value == "") {
			  message = message + "At least one WordPress user group must be selected.\n";
			}
			if (document.getElementById("emaildomain").value == "") {
			  message = message + "E-Mail Domain.\n";
			}
			if (document.getElementById("aliasformat").value == "") {
			  message = message + "E-Mail Alias Format.\n";
			}
			if (document.getElementById("cpaneluser").value == "") {
			  message = message + "cPanel User ID.\n";
			}
			if (document.getElementById("cpanelpass").value == "") {
			  message = message + "cPanel Password.\n";
			}
			if (document.getElementById("cpanelserver").value == "") {
			  message = message + "cPanel Server IP (or localhost)\n";
			}
			if (message != "The following fields are required:\n\n") {
			  alert(message);
			  return false;
			}
			}
			function automationfields() {
			if (document.getElementById("autogenerate").checked) {
			  document.getElementById("wordpressgroups").disabled = false;
			  document.getElementById("emailquota").disabled = false;
			  document.getElementById("aliasformat").disabled = false;
			} else {
			  document.getElementById("wordpressgroups").disabled = true;
			  document.getElementById("emailquota").disabled = true;
			  document.getElementById("aliasformat").disabled = true;
			}
			}
			function emailfields() {
			if (document.getElementById("sendemail").checked) {
			  document.getElementById("emailmsg").disabled = false;
			} else {
			  document.getElementById("emailmsg").disabled = true;
			}
			}
			</script>
			<form action="users.php?page=emailmanager&mode=config" method="post" name="emailmgrconfig" id="emailmgrconfig" onsubmit="return submitform();">
			<p style="font-weight: bold; text-decoration:underline;">Automation</p>';
			if (EMAILMGR_STD_MODE == 0) {
				$output .= '<p><label for="autogenerate" style="vertical-align: top; float: left; width: 10em;">Automatically Create E-mail Accounts:</label> <input type="checkbox" name="autogenerate" id="autogenerate" style="float:left;"' . $autogenerate . ' onchange="automationfields();"><label for="autogenerate" style="vertical-align: top; float: left; width: 30em; margin-left: 1em;"><em>Automatically create e-mail account when a new user is registered.</em></label></p>';
			} else {
				$output .= '<p><label for="autogenerate" style="vertical-align: top; float: left; width: 10em;">Automatically Create E-mail Accounts:</label> <input type="checkbox" name="autogenerate" id="autogenerate" style="float:left;"' . $autogenerate . ' disabled><label for="autogenerate" style="vertical-align: top; float: left; width: 30em; margin-left: 1em;"><em>Automatically create e-mail account when a new user is registered.</em> (<a href="users.php?page=emailmanager&mode=upgradeplugin">Upgrade plugin</a> to use this feature!)</label></p>';
			}
			$output .= '<p>&nbsp;</p>
			<p><br><label for="wordpressgroups" style="vertical-align: top; float: left; width: 10em;">WordPress Groups:</label> <select name="wordpressgroups[]" id="wordpressgroups" size="6" style="float: left;" multiple>';
			if ($wordpressgroups != "") {
				$wordpressgroups = explode(",", $wordpressgroups);
				if (in_array("Administrator", $wordpressgroups)) {
					$output .= '<option value="Administrator" selected>Administrator</option>';
				} else {
					$output .= '<option value="Administrator">Administrator</option>';
				}
				if (in_array("Author", $wordpressgroups)) {
					$output .= '<option value="Author" selected>Author</option>';
				} else {
					$output .= '<option value="Author">Author</option>';
				}
				if (in_array("Contributor", $wordpressgroups)) {
					$output .= '<option value="Contributor" selected>Contributor</option>';
				} else {
					$output .= '<option value="Contributor">Contributor</option>';
				}
				if (in_array("Editor", $wordpressgroups)) {
					$output .= '<option value="Editor" selected>Editor</option>';
				} else {
					$output .= '<option value="Editor">Editor</option>';
				}
				if (in_array("Subscriber", $wordpressgroups)) {
					$output .= '<option value="Subscriber" selected>Subscriber</option>';
				} else {
					$output .= '<option value="Subscriber">Subscriber</option>';
				}
			} else {
				$output .= '<option value="Administrator">Administrator</option>';
				$output .= '<option value="Author">Author</option>';
				$output .= '<option value="Contributor">Contributor</option>';
				$output .= '<option value="Editor">Editor</option>';
				$output .= '<option value="Subscriber">Subscriber</option>';
			}
			$output .= '</select><label for="wordpressgroups" style="vertical-align: top; float: left; width: 30em; margin-left: 1em;"><em>Select the user groups eligible for automatic e-mail accounts.</em></label></p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p><label for="emailquota" style="vertical-align: top; float: left; width: 10em;">E-Mail Quota (MB):</label> <input type="text" name="emailquota" id="emailquota" size="10" value="' . $emailquota . '" style="float: left;"><label for="emailquota" style="vertical-align: top; float: left; width: 50em; margin-left: 1em;"><em>Enter \'unlimited\' for unlimited storage.</em></label></p>
			<p>&nbsp;</p>
			<p><label for="aliasformat" style="vertical-align: top; float: left; width: 10em;">E-Mail Alias Format:</label> <select name="aliasformat" id="aliasformat">';
			if ($aliasformat == "first.last") {
				$output .= '<option value="first.last" selected>First Name . Last Name</option>';
			} else {
				$output .= '<option value="first.last">First Name . Last Name</option>';
			}
			if ($aliasformat == "firstlast") {
				$output .= '<option value="firstlast" selected>First Name + Last Name</option>';
			} else {
				$output .= '<option value="firstlast">First Name + Last Name</option>';
			}
			if ($aliasformat == "firstl") {
				$output .= '<option value="firstl" selected>First Name + Last Initial</option>';
			} else {
				$output .= '<option value="firstl">First Name + Last Initial</option>';
			}
			if ($aliasformat == "username") {
				$output .= '<option value="username" selected>WordPress Username</option>';
			} else {
				$output .= '<option value="username">WordPress Username</option>';
			}
			$output .= '</select></p>
			<hr>
			<p style="font-weight: bold; text-decoration:underline;">E-Mail Settings</p>';
			if (!is_multisite()) {
				$output .= '<p><span style="vertical-align: top; float: left; width: 10em;">E-Mail Accounts:</span>';
				$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "emailcount"));
				$output .= $accountinfo->data->count . ' / ' . $accountinfo->data->max;
				$output .= '</p>
				<p><span style="vertical-align: top; float: left; width: 10em;">Disk Space Usage:</span> ';
				$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "diskusage"));
				$output .= $accountinfo->data->count . ' ' . $accountinfo->data->units . ' / ' . $accountinfo->data->max;
				$output .= '</p>';
			}
			$output .= '<p><label for="emaildomain" style="vertical-align: top; float: left; width: 10em;">E-Mail Domain:</label> <select name="emaildomain" id="emaildomain">
			<option value=""></option>';
			if ($cpaneluser != "" && $cpanelpass != "" && $cpanelserver != "") {
				$domains = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "listemaildomains"));
				foreach ($domains->data as $domain) {
					if (function_exists('is_multisite') && is_multisite()) {
						if ($domain->domain == $mudomain) {
							if ($domain->domain == $emaildomain) {
									$output .= '<option value="'. $domain->domain .'" selected>'. $domain->domain .'</option>';
							} else {
								$output .= '<option value="'. $domain->domain .'">'. $domain->domain .'</option>';
							}
						}
					} else {
						if ($domain->domain == $emaildomain) {
							$output .= '<option value="'. $domain->domain .'" selected>'. $domain->domain .'</option>';
						} else {
							$output .= '<option value="'. $domain->domain .'">'. $domain->domain .'</option>';
						}
					}
				}
			}
			$output .= '</select></p>
			<p><label for="webmailurl" style="vertical-align: top; float: left; width: 10em;">Webmail URL:</label> <input type="text" name="webmailurl" id="webmailurl" value="' . $webmailurl . '"></p>';
			if (EMAILMGR_CPANEL_HIDE == 1 || EMAILMGR_CPANEL_GLOBAL == 1) {
				//HIDE CPANEL FIELDS
			} else {
				$output .= '<hr>
				<p style="font-weight: bold; text-decoration:underline;">cPanel Server Credentials</p>
				<p><label for="cpaneluser" style="vertical-align: top; float: left; width: 10em;">cPanel User ID:</label> <input type="text" name="cpaneluser" id="cpaneluser" value="' . $cpaneluser . '"></p>
				<p><label for="cpanelpass" style="vertical-align: top; float: left; width: 10em;">cPanel Password:</label> <input type="password" name="cpanelpass" id="cpanelpass" value="' . $cpanelpass . '"></p>
				<p><label for="cpanelserver" style="vertical-align: top; float: left; width: 10em;">cPanel Server IP:</label> <input type="text" name="cpanelserver" id="cpanelserver" value="' . $cpanelserver . '" style="float: left;"><label for="cpanelserver" style="vertical-align: top; float: left; width: 50em; margin-left: 1em;"><em>Enter \'localhost\' or \'127.0.0.1\' if logging into cPanel on the same server as this WordPress install.</em></label></p>
				<p>&nbsp;<br><br></p>';
			}
			$output .= '<hr>
			<p style="font-weight: bold; text-decoration:underline;">E-Mail Notification Settings (will send to user\'s primary e-mail account)</p>';
			if (EMAILMGR_STD_MODE == 0) {
				$output .= '<p><label for="sendemail" style="vertical-align: top; float: left; width: 10em;">Enable E-Mail Notification:</label> <input type="checkbox" name="sendemail" id="sendemail"' . $sendemail . ' onchange="emailfields();"></p>';
			} else {
				$output .= '<p><label for="sendemail" style="vertical-align: top; float: left; width: 10em;">Enable E-Mail Notification:</label> <input type="checkbox" name="sendemail" id="sendemail"' . $sendemail . ' disabled> (<a href="users.php?page=emailmanager&mode=upgradeplugin">Upgrade plugin</a> to use this feature!)</p>';
			}
				$output .= '<p><br><label for="emailmsg" style="vertical-align: top; float: left; width: 10em;">E-Mail Message:</label> <textarea name="emailmsg" id="emailmsg" rows="15" cols="90" style="float: left;">' . $emailmsg . '</textarea><label for="emailmsg" style="vertical-align: top; width: 50em; margin-left: 1em;"><em>The following variables can be used:<br><br>&nbsp;&nbsp;{$user} = User\'s first name<br>&nbsp;&nbsp;{$email} = E-Mail address assigned to user<br>&nbsp;&nbsp;{$temppw} = Temporary password for webmail<br>&nbsp;&nbsp;{$server} = Webmail URL</em></label></p>
			<script type="text/javascript">
			automationfields();
			emailfields();
			</script>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p><input type="submit" value="Save Settings" class="button button-primary"></p>
			</form>
			';
		} else {
			if (EMAILMGR_CPANEL_HIDE == 1 || EMAILMGR_CPANEL_GLOBAL == 1) {
				return '<h2 style="display:inline;">Configuration Options</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">The cPanel credentials entered are not valid or a valid domain has not been selected. A connection could not be established.</p><p style="text-align:center; font-weight: bold;font-size: 16px;">Please contact your server administrator for assistance.</p>';
				exit;
			} else {
				$output .= '<div class="error">
				<p>The cPanel credentials entered are not valid or a valid domain has not been selected. A connection could not be established.</p>
				</div><br>
				<h2>Configuration Options</h2>
				<script type="text/javascript">
				function submitform() {
				var message = "The following fields are required:\n\n";
				if (document.getElementById("cpaneluser").value == "") {
				  message = message + "cPanel User ID.\n";
				}
				if (document.getElementById("cpanelpass").value == "") {
				  message = message + "cPanel Password.\n";
				}
				if (document.getElementById("cpanelserver").value == "") {
				  message = message + "cPanel Server IP (or localhost)\n";
				}
				if (message != "The following fields are required:\n\n") {
				  alert(message);
				  return false;
				}
				}
				</script>
				<form action="users.php?page=emailmanager&mode=config&action=setcpanel" method="post" name="emailmgrconfig" id="emailmgrconfig" onsubmit="return submitform();">
				<p style="font-size:14px;">Please enter your cPanel login credentials and click Save Changes to continue.</p>
				<p><label for="cpaneluser" style="vertical-align: top; float: left; width: 10em;">cPanel User ID:</label> <input type="text" name="cpaneluser" id="cpaneluser" value="' . $cpaneluser . '"></p>
				<p><label for="cpanelpass" style="vertical-align: top; float: left; width: 10em;">cPanel Password:</label> <input type="password" name="cpanelpass" id="cpanelpass" value="' . $cpanelpass . '"></p>
				<p><label for="cpanelserver" style="vertical-align: top; float: left; width: 10em;">cPanel Server IP:</label> <input type="text" name="cpanelserver" id="cpanelserver" value="' . $cpanelserver . '" style="float: left;"><label for="cpanelserver" style="vertical-align: top; float: left; width: 50em; margin-left: 1em;"><em>Enter \'localhost\' or \'127.0.0.1\' if logging into cPanel on the same server as this WordPress install.</em></label></p>
				<p>&nbsp;</p>
				<p>&nbsp;</p>
				<p><input type="submit" value="Save Settings" class="button button-primary"></p>
				</form>
				';
			}
		}
	} else {
		if (EMAILMGR_CPANEL_HIDE == 1 || EMAILMGR_CPANEL_GLOBAL == 1) {
			return '<h2 style="display:inline;">Configuration Options</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">The cPanel credentials entered are not valid or a valid domain has not been selected. A connection could not be established.</p><p style="text-align:center; font-weight: bold;font-size: 16px;">Please contact your server administrator for assistance.</p>';
				exit;
		} else {
			$output .= '<h2>Configuration Options</h2>
			<script type="text/javascript">
			function submitform() {
			var message = "The following fields are required:\n\n";
			if (document.getElementById("cpaneluser").value == "") {
			  message = message + "cPanel User ID.\n";
			}
			if (document.getElementById("cpanelpass").value == "") {
			  message = message + "cPanel Password.\n";
			}
			if (document.getElementById("cpanelserver").value == "") {
			  message = message + "cPanel Server IP (or localhost)\n";
			}
			if (message != "The following fields are required:\n\n") {
			  alert(message);
			  return false;
			}
			}
			</script>
			<form action="users.php?page=emailmanager&mode=config&action=setcpanel" method="post" name="emailmgrconfig" id="emailmgrconfig" onsubmit="return submitform();">
			<p style="font-size:14px;">Please enter your cPanel login credentials and click Save Changes to continue.</p>
			<p><label for="cpaneluser" style="vertical-align: top; float: left; width: 10em;">cPanel User ID:</label> <input type="text" name="cpaneluser" id="cpaneluser" value="' . $cpaneluser . '"></p>
			<p><label for="cpanelpass" style="vertical-align: top; float: left; width: 10em;">cPanel Password:</label> <input type="password" name="cpanelpass" id="cpanelpass" value="' . $cpanelpass . '"></p>
			<p><label for="cpanelserver" style="vertical-align: top; float: left; width: 10em;">cPanel Server IP:</label> <input type="text" name="cpanelserver" id="cpanelserver" value="' . $cpanelserver . '" style="float: left;"><label for="cpanelserver" style="vertical-align: top; float: left; width: 50em; margin-left: 1em;"><em>Enter \'localhost\' or \'127.0.0.1\' if logging into cPanel on the same server as this WordPress install.</em></label></p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p><input type="submit" value="Save Settings" class="button button-primary"></p>
			</form>
			';
		}
	}
return $output;
}

function emailmanager_output_list() {
	global $wpdb;
	$output = '';
	//PROCESS UPDATES
	if ( isset( $_GET['action'] ) && !empty( $_GET['action'] ) && isset( $_POST['uservalue'] ) && !empty( $_POST['uservalue'] ) && isset( $_POST['user'] ) && !empty( $_POST['user'] ) ) {
		if ($_GET["action"] == "changepw") {
			//Set config options
			$emaildomain = emailmanager_config_options("emailmgr_domain");
			$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
			$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
			$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");

			//REMOVE USER FROM CPANEL
			$optional['email_domain'] = $emaildomain;
			$optional['email_user'] = $_POST['user'];
			$optional['password'] = $_POST['uservalue'];

			emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "changepw", $optional);
			$output .= '<div class="updated">
			<p>The password for ' . $_POST['user'] . ' has been successfully changed!</p>
			</div><br>';
		}
		if ($_GET["action"] == "changequota") {
			//Set config options
			$emaildomain = emailmanager_config_options("emailmgr_domain");
			$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
			$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
			$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");

			//REMOVE USER FROM CPANEL
			$optional['email_domain'] = $emaildomain;
			$optional['email_user'] = $_POST['user'];
			$optional['quota'] = $_POST['uservalue'];

			emailmanager_cpanelAPI_Pro($cpaneluser, $cpanelpass, $cpanelserver, "changequota", $optional);
			$output .= '<div class="updated">
			<p>The quota for ' . $_POST['user'] . ' has been successfully changed!</p>
			</div><br>';
		}
		if ($_GET["action"] == "delete") {
			emailmanager_remove_email($_POST["user"]);
			$output .= '<div class="updated">
			<p>The e-mail account for ' . $_POST['user'] . ' has been successfully removed!</p>
			</div><br>';
		}
		if ($_GET["action"] == "add") {
			//Get submitted values
			$userid = (isset($_POST['user']) ? $_POST['user'] : "");
			$user_info = get_userdata($userid);
			$firstname = $user_info->first_name;
			$lastname = $user_info->last_name;
			$username = $user_info->user_login;
			$email = $user_info->user_email;
			$customformat = (isset($_POST['aliasformat']) ? $_POST['aliasformat'] : "");
			$customalias = (isset($_POST['emailalias']) ? $_POST['emailalias'] : "");
			$customquota = (isset($_POST['emailquota']) ? $_POST['emailquota'] : "");

			if ($customformat != "custom") {
				$customalias = "";
			}

			emailmanager_create_email($userid, $firstname, $lastname, $username, $email, $customformat, $customalias, $customquota);
			$output .= '<div class="updated">
			<p>The e-mail account for ' . $_POST['user'] . ' has been successfully added!</p>
			</div><br>';
		}
		if ($_GET["action"] == "link") {
			$cnt = 1;
			while ($cnt <= $_POST['numfields']) {
				if ($_POST['email_' . $cnt] != "" && $_POST['user_' . $cnt] != "") {
					update_user_option( $_POST['user_' . $cnt], 'emailmgr_alias', $_POST['email_' . $cnt] );
				}
				$cnt++;
			}
			$output .= '<div class="updated">
			<p>User accounts have been successfully linked!</p>
			</div><br>';
		}
	}

	//DISPLAY LIST OF ACCOUNTS
	//Set config options
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
	$emaildomain = emailmanager_config_options("emailmgr_domain");

	if ($cpaneluser != "" && $cpanelpass != "" && $cpanelserver != "" && $emaildomain != "") {
		$output .= '<h2 style="display:inline;">List of Accounts</h2> - <a href="users.php?page=emailmanager&mode=config">Configuration Options</a> | <a href="users.php?page=emailmanager&mode=linkaccounts">Link Existing Accounts</a> | <a href="users.php?page=emailmanager&mode=add">Add New Account</a> | <a href="users.php?page=emailmanager&mode=mailinglist">Manage Mailing Lists</a>';
		$optional['domain'] = $emaildomain;
		$accounts = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "getaccountlist", $optional));

		if ($accounts->data->user != "") {
			$output .= '<script type="text/javascript">
			var openMenu = 0;
			var openMenuType = "";
			function changepw(user, row) {
			var passwd = document.getElementById("passwd_" + row).value;
			var passwdstr = document.getElementById("passwdstr_" + row).value;
			var errmsg = "";
			if (passwd=="")
			  {
			  errmsg = errmsg + "You must enter a valid password.\n";
			  }
			if (passwdstr<2)
			  {
			  errmsg = errmsg + "The password entered does not meet minimum requirements.\n";
			  }
			if (errmsg=="")
			  {
			  document.getElementById("uservalue").value = passwd;
			  document.getElementById("user").value = user;
			  document.updateuser.action = location.protocol + "//" + location.hostname + location.pathname + "?page=emailmanager&mode=list" + "&action=changepw";
			  document.updateuser.submit();
			  } else {
			  alert("The password could not be updated for the following reasons:\n\n" + errmsg);
			  }
			  return false;
			}
			function changequota(user, row) {
			var quotachk=jQuery(\'input[name=quotagrp_\' + row + \']:checked\').val();
			if (quotachk=="unlimited")
			  {
			  var quota="unlimited";
			  } else {
			  var quota=document.getElementById("quota_" + row).value;
			  }
			if (isNaN(quota)==true && quota!="unlimited")
			  {
			  alert("You must enter a valid quota.");
			  return false;
			  }
			if (quota!="")
			  {
			  document.getElementById("uservalue").value = quota;
			  document.getElementById("user").value = user;
			  document.updateuser.action = location.protocol + "//" + location.hostname + location.pathname + "?page=emailmanager&mode=list" + "&action=changequota";
			  document.updateuser.submit();
			  } else {
			  alert("You must enter a valid quota.");
			  }
			  return false;
			}
			function removeacct(user) {
			var r=confirm("Are you sure you want to delete this e-mail account and all associated data?");
			if (r==true)
			  {
			  document.getElementById("user").value = user;
			  document.getElementById("uservalue").value = "na";
			  document.updateuser.action = location.protocol + "//" + location.hostname + location.pathname + "?page=emailmanager&mode=list" + "&action=delete";
			  document.updateuser.submit();
			  }
			}
			function generatepw(field, row) {
			  var length = 15,
			  charset = "abcdefghijklnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789",
			  retVal = "";
			  for (var i = 0, n = charset.length; i < length; ++i) {
			    retVal += charset.charAt(Math.floor(Math.random() * n));
			  }
			  document.getElementById(field).value = retVal;
			  chkPasswordStrength(document.getElementById(field).value, row);
			}
			function showchpasswd(row, type) {
			  hidechpasswd(openMenu);
			  hidechquota(openMenu);
			  if (openMenu != row || openMenuType != type) {
			  jQuery(\'#chpasswordtd_\' + row).show();
			  jQuery(\'#chpassword_\' + row).slideDown(\'slow\');
			  openMenu = row;
			  openMenuType = type;
			  } else {
			  openMenu = 0;
			  openMenuType = "";
			  }
			}
			function hidechpasswd(row) {
			  jQuery(\'#chpasswordtd_\' + row).hide();
			  jQuery(\'#chpassword_\' + row).hide();
			}
			function showchquota(row, type) {
			  hidechquota(openMenu);
			  hidechpasswd(openMenu);
			  if (openMenu != row || openMenuType != type) {
			  jQuery(\'#chquotatd_\' + row).show();
			  jQuery(\'#chquota_\' + row).slideDown(\'slow\');
			  openMenu = row;
			  openMenuType = type;
			  } else {
			  openMenu = 0;
			  openMenuType = "";
			  }
			}
			function hidechquota(row) {
			  jQuery(\'#chquotatd_\' + row).hide();
			  jQuery(\'#chquota_\' + row).hide();
			}
			function chkPasswordStrength(txtpass, row)
			   {
			     var desc = new Array();
			     desc[0] = "Very Weak (Password is not strong enough)";
			     desc[1] = "Weak (Password is not strong enough)";
			     desc[2] = "Better";
			     desc[3] = "Medium";
			     desc[4] = "Strong";
			     desc[5] = "Strongest";

			     document.getElementById("strenghtMsg_" + row).innerHTML = "";
			     var score   = 0;

			     //if txtpass bigger than 6 give 1 point
			     if (txtpass.length > 6) score++;

			     //if txtpass has both lower and uppercase characters give 1 point
			     if ( ( txtpass.match(/[a-z]/) ) && ( txtpass.match(/[A-Z]/) ) ) score++;

			     //if txtpass has at least one number give 1 point
			     if (txtpass.match(/\d+/)) score++;

			     //if txtpass has at least one special caracther give 1 point
			     if ( txtpass.match(/.[!,@,#,$,%,^,&,*,?,_,~,-,(,)]/) ) score++;

			     //if txtpass bigger than 12 give another 1 point
			     if (txtpass.length > 12) score++;

			     document.getElementById("strenghtMsg_" + row).innerHTML = "Strength: " + desc[score];
			     document.getElementById("passwdstr_" + row).value = score;

			     if (txtpass.length < 6)
			     {
			     document.getElementById("strenghtMsg_" + row).innerHTML = "Password Should be Minimum 6 Characters";
			     }
			   }
			</script>';
			if (!is_multisite()) {
				$output .= '<p style="text-align: center;">Active Accounts: <em>';
				$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "emailcount"));
				$output .= $accountinfo->data->count . ' / ' . $accountinfo->data->max;
				$output .= '</em>&nbsp;&nbsp;-&nbsp;&nbsp;Disk Usage: <em>';
				$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "diskusage"));
				$output .= $accountinfo->data->count . ' ' . $accountinfo->data->units . ' / ' . $accountinfo->data->max;
				$output .= '</em></p>';
			} else {
				$output .= '<p>&nbsp;</p>';
			}
			$output .= '<table class="wp-list-table widefat fixed pages" style="width:96%; margin-left:auto; margin-right:auto;">
			<thead>
			<tr>
			<th class="manage-column">User</th>
			<th class="manage-column" style="text-align:center;">E-Mail Account</th>
			<th class="manage-column" style="text-align:center;">Disk Usage</th>
			<th class="manage-column" style="text-align:center;">Actions</th>
			</tr></thead><tbody>';
			$sortarray = "";
			$cnt = 0;
			foreach ($accounts->data as $account) {
				if (emailmanager_emailalias_userid($account->user) != "") {
					$user_info = get_userdata(emailmanager_emailalias_userid($account->user));
					$sortarray[$cnt]['login'] = $account->login;
					$sortarray[$cnt]['humandiskused'] = $account->humandiskused;
					$sortarray[$cnt]['humandiskquota'] = ($account->humandiskquota == "None" ? "Unlimited" : $account->humandiskquota);
					$sortarray[$cnt]['user'] = $account->user;
					$sortarray[$cnt]['first_name'] = $user_info->first_name;
					$sortarray[$cnt]['last_name'] = $user_info->last_name;
					$sortarray[$cnt]['wplogin'] = $user_info->user_login;
					$cnt++;
				}
			}
			if ($sortarray != "") {
				$accounts = array_sort($sortarray, 'last_name', SORT_ASC);
				$cnt = 1;
				foreach ($accounts as $account) 
				{
					$output .= '<tr class="type-page status-publish hentry ' . ($cnt % 2 == 0 ? "alternate " : "") . 'iedit author-self level-0">
					<td>' . $account['last_name'] . ', ' . $account['first_name'] . ' (' . $account['wplogin'] . ')</td>
					<td style="text-align:center;">' . $account['login'] . '</td>
					<td style="text-align:center;">' . $account['humandiskused'] . ' / ' . $account['humandiskquota'] . ' </td>';
					if (EMAILMGR_STD_MODE == 0) {
						$output .= '<td style="text-align:center;"><button onclick="showchpasswd(' . $cnt . ', \'pass\');" class="button button-primary">Change Password</button>&nbsp;<button onclick="showchquota(' . $cnt . ', \'quota\');" class="button button-primary">Change Quota</button>&nbsp;<button onclick="removeacct(\'' . $account['user'] . '\');" class="button button-primary" style="background-color: #C00000;">Delete Account</button></td></tr>';
					} else {
						$output .= '<td style="text-align:center;"><button onclick="showchpasswd(' . $cnt . ');" class="button button-primary" disabled>Change Password</button>&nbsp;<button onclick="showchquota(' . $cnt . ');" class="button button-primary" disabled>Change Quota</button>&nbsp;<button onclick="removeacct(\'' . $account['user'] . '\');" class="button button-primary" style="background-color: #C00000;">Delete Account</button><br>(<a href="users.php?page=emailmanager&mode=upgradeplugin">Upgrade plugin</a> to enable all features!)</td></tr>';
					}
					$output .= '<tr class="type-page status-publish hentry ' . ($cnt % 2 == 0 ? "alternate " : "") . 'iedit author-self level-0"><td colspan="4" id="chpasswordtd_' . $cnt . '" name="chpasswordtd_' . $cnt . '" align="center" style="display:none;">
					<div id="chpassword_' . $cnt . '" name="chpassword_' . $cnt . '" style="display:none;background-color:#00CCFF;padding-top:5px;padding-bottom:5px;">
					<form name="passwdupdate_' . $cnt . '" id="passwdupdate_' . $cnt . '" method="post" onsubmit="return changepw(\'' . $account['user'] . '\', ' . $cnt . ');">
					<strong>Change Password:</strong>&nbsp;&nbsp;&nbsp;Enter New Password: <input type="text" name="passwd_' . $cnt . '" id="passwd_' . $cnt . '" onkeyup="chkPasswordStrength(this.value, ' . $cnt . ');">&nbsp;&nbsp;<input type="hidden" name="passwdstr_' . $cnt . '" id="passwdstr_' . $cnt . '"><span id="strenghtMsg_' . $cnt . '" name="strenghtMsg_' . $cnt . '"></span>&nbsp;&nbsp;
					<button onclick="generatepw(\'passwd_' . $cnt . '\', ' . $cnt . '); return false;" class="button button-primary">Generate Password</button>&nbsp;&nbsp;<input type="submit" class="button button-primary" value="Change Password"></form></div></td></tr>
					<tr class="type-page status-publish hentry ' . ($cnt % 2 == 0 ? "alternate " : "") . 'iedit author-self level-0"><td colspan="4" id="chquotatd_' . $cnt . '" name="chquotatd_' . $cnt . '" align="center" style="display:none;"><div id="chquota_' . $cnt . '" name="chquota_' . $cnt . '" style="display:none;background-color:#00CCFF;padding-top:5px;padding-bottom:5px;">
					<form name="quotaupdate_' . $cnt . '" id="quotaupdate_' . $cnt . '" method="post" onsubmit="return changequota(\'' . $account['user'] . '\', ' . $cnt . ');">
					<strong>Change Quota:</strong>&nbsp;&nbsp;&nbsp;';
					if ($account['humandiskquota'] == "Unlimited") {
						$output .= '<input type="radio" name="quotagrp_' . $cnt . '" value="custom" onclick="getElementById(\'quota_' . $cnt . '\').disabled=false;">Custom Quota: <input type="text" name="quota_' . $cnt . '" id="quota_' . $cnt . '" size="5" disabled>&nbsp;&nbsp;<input type="radio" name="quotagrp_' . $cnt . '" value="unlimited" onclick="getElementById(\'quota_' . $cnt . '\').disabled=true;" checked> Unlimited';
					} else {
						$output .= '<input type="radio" name="quotagrp_' . $cnt . '" value="custom" onclick="getElementById(\'quota_' . $cnt . '\').disabled=false;" checked>Custom Quota: <input type="text" name="quota_' . $cnt . '" id="quota_' . $cnt . '" size="5" value="' . str_replace("MB", "", $account['humandiskquota']) . '"> (in MB)&nbsp;&nbsp;<input type="radio" name="quotagrp_' . $cnt . '" value="unlimited" onclick="getElementById(\'quota_' . $cnt . '\').disabled=true;"> Unlimited';
					}
					$output .= '&nbsp;&nbsp;<input type="submit" class="button button-primary" value="Change Quota"></form></div></td></tr>';
					$cnt++;
				}
			} else {
				$output .= '<tr><td colspan="4"><p>&nbsp;</p><p style="text-align: center; font-weight: bold;font-size: 16px;">There are no accounts to display.</p><p style="text-align: center; font-size: 16px;"><a href="users.php?page=emailmanager&mode=add">Add a New Account</a></p><p>&nbsp;</p></td></tr>';
			}
			$output .= '</tbody></table>
			<form name="updateuser" id="updateuser" action="" method="post">
			<input type="hidden" name="uservalue" id="uservalue">
			<input type="hidden" name="user" id="user">
			</form>
			';
		} else {
			$output .= '<p>&nbsp;</p><p>&nbsp;</p><p style="text-align: center; font-weight: bold;font-size: 16px;">There are no accounts to display.</p><p style="text-align: center; font-size: 16px;"><a href="users.php?page=emailmanager&mode=add">Add a New Account</a></p>';
		}
	} else {
		$output .= '<h2 style="display:inline;">List of Accounts</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">To use this plugin, please enter your configuration details <a href="users.php?page=emailmanager&mode=config">here</a>.</p>';
	}
	return $output;
}

function emailmanager_output_linkexisting() {
	global $wpdb;
	$output = '';
	//DISPLAY LIST OF ACCOUNTS
	//Set config options
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
	$emaildomain = emailmanager_config_options("emailmgr_domain");

	if ($cpaneluser != "" && $cpanelpass != "" && $cpanelserver != "" && $emaildomain != "") {
		$output .= '<h2 style="display:inline;">Link Existing Accounts</h2> - <a href="users.php?page=emailmanager&mode=config">Configuration Options</a> | <a href="users.php?page=emailmanager&mode=list">List of Accounts</a> | <a href="users.php?page=emailmanager&mode=add">Add New Account</a> | <a href="users.php?page=emailmanager&mode=mailinglist">Manage Mailing Lists</a>';
		$optional['domain'] = $emaildomain;
		$accounts = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "getaccountlist", $optional));
		if ($accounts->data->user != "") {
			$output .= '<p>&nbsp;</p><form name="linkaccounts" id="linkaccounts" method="post" action="users.php?page=emailmanager&mode=list&action=link"><table class="wp-list-table widefat fixed pages" style="width:70%; margin-left:auto; margin-right:auto;">
			<thead>
			<tr>
			<th class="manage-column">WordPress User</th>
			<th class="manage-column">E-Mail Account</th>
			</tr></thead><tbody>';
			$sortarray = "";
			$cnt = 0;
			$optionlist = "";
			foreach ($accounts->data as $account) {
				$sortarray[$cnt]['login'] = $account->login;
				$sortarray[$cnt]['user'] = $account->user;
				$cnt++;
			}
			if ($sortarray != "") {
				$accounts = array_sort($sortarray, 'user', SORT_DESC);
				$cnt = 1;
				foreach ($accounts as $account) 
				{
					if (emailmanager_emailalias_userid($account['user']) == "") {
						$optionlist .= '<option value="' . $account['user'] . '">' . $account['login'] . '</option>';
						$cnt++;
					}
				}
			}
			$unassigned = emailmanager_emailalias_userid_unassigned();
			if (!empty($unassigned) && $optionlist != "") {
				$cnt = 1;
				foreach ($unassigned as $wpuser)
				{
					$output .= '<tr class="type-page status-publish hentry ' . ($cnt % 2 == 0 ? "alternate " : "") . 'iedit author-self level-0">
					<td>' . $wpuser->user_nicename . '</td><td><select name="email_' . $cnt . '" id="email_' . $cnt . '"><option value=""></option>' . $optionlist . '</select><input type="hidden" id="user_' . $cnt . '" name="user_' . $cnt . '" value="' . $wpuser->ID . '"></td>
					</tr>';
					$cnt++;
				}
				$lastrow = $cnt - 1;
				$output .= '<tr><td colspan="2"><p>&nbsp;</p><p style="text-align: center; font-weight: bold;font-size: 16px;"><input type="hidden" id="numfields" name="numfields" value="' .  $lastrow . '"><input type="hidden" name="uservalue" id="uservalue" value="na"><input type="hidden" name="user" id="user" value="na"><input type="submit" class="button button-primary" value="Update User Profiles"></td></tr>';
			} else {
				$output .= '<tr><td colspan="2"><p>&nbsp;</p><p style="text-align: center; font-weight: bold;font-size: 16px;">There are no unassigned accounts to display.</p><p>&nbsp;</p></td></tr>';
			}
			$output .= '</tbody></table></form>';
		} else {
			$output .= '<p>&nbsp;</p><p>&nbsp;</p><p style="text-align: center; font-weight: bold;font-size: 16px;">There are no unassigned cPanel accounts to display.</p><p>&nbsp;</p></p>';
		}
	} else {
		$output .= '<h2 style="display:inline;">List of Accounts</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">To use this plugin, please enter your configuration details <a href="users.php?page=emailmanager&mode=config">here</a>.</p>';
	}
	return $output;
}

function emailmanager_output_adduser() {
	global $wpdb;
	$output = '';
	//ADD NEW E-MAIL ACCOUNT
	//Set config options
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
	$emaildomain = emailmanager_config_options("emailmgr_domain");
	$emailquota = emailmanager_config_options("emailmgr_quota");
	if ($cpaneluser != "" && $cpanelpass != "" && $cpanelserver != "" && $emaildomain != "") {
		$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "diskusage"));
		if ($accountinfo->data->max != "") {
			$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "emailcount"));
			if ((int)$accountinfo->data->count < (int)$accountinfo->data->max || $accountinfo->data->max == "unlimited") {
				$accounts = emailmanager_emailalias_userid_unassigned();
				if (!empty($accounts)) {
					$output .= '<script type="text/javascript">
					function submitform() {
					var message = "The following fields are required:\n\n";
					if (document.getElementById("user").value == "") {
					  message = message + "You must select a user.\n";
					}
					if (document.getElementById("emailquota").value == "") {
					  message = message + "E-Mail Quota (in MB).\n";
					}
					if (document.getElementById("aliasformat").value == "custom" && document.getElementById("emailalias").value == "") {
					  message = message + "You must enter a valid e-mail alias.\n";
					}
					if (message != "The following fields are required:\n\n") {
					  alert(message);
					  return false;
					}
					}
					</script>
					<form action="users.php?page=emailmanager&mode=list&action=add" method="post" name="emailmgrconfig" id="emailmgrconfig" onsubmit="return submitform();">
					<h2 style="display:inline;">Add New Account</h2> - <a href="users.php?page=emailmanager&mode=config">Configuration Options</a> | <a href="users.php?page=emailmanager&mode=list">List of Accounts</a> | <a href="users.php?page=emailmanager&mode=linkaccounts">Link Existing Accounts</a> | <a href="users.php?page=emailmanager&mode=mailinglist">Manage Mailing Lists</a>';
					$output .= '<p style="font-size:14px;">Complete the form below to create a new e-mail account. Note: The temporary password will be automatically generated.</p>
					<label for="user" style="vertical-align: top; float: left; width: 10em;">Select a WordPress User:</label> <select name="user" id="user">
					<option value=""></option>';
					$sortarray = "";
					$cnt = 0;
					foreach ($accounts as $account) 
					{
						$sortarray[$cnt]['id'] = $account->user_id;
						$sortarray[$cnt]['first_name'] = emailmanager_usermeta($account->user_id, "first_name");
						$sortarray[$cnt]['last_name'] = emailmanager_usermeta($account->user_id, "last_name");
						$sortarray[$cnt]['login'] = $account->user_login;
						$cnt++;
					}
					if ($sortarray != "") {
						$accounts = array_sort($sortarray, 'last_name', SORT_ASC);
						foreach ($accounts as $account) 
						{
							$output .= '<option value="' . $account['id'] . '">' . $account['last_name'] . ', ' . $account['first_name'] . ' (' . $account['login'] . ')</option>';
						}
					}
					$output .= '</select>
					<p><label for="emailquota" style="vertical-align: top; float: left; width: 10em;">E-Mail Quota (MB):</label> <input type="text" name="emailquota" id="emailquota" size="10" value="' . $emailquota . '" style="float: left;"><label for="emailquota" style="vertical-align: top; float: left; width: 50em; margin-left: 1em;"><em>Enter \'unlimited\' for unlimited storage.</em></label></p>
					<p>&nbsp;</p>
					<p><label for="aliasformat" style="vertical-align: top; float: left; width: 10em;">E-Mail Alias Format:</label> <select name="aliasformat" id="aliasformat" onchange=\'((this.options[this.selectedIndex].value == "custom") ? document.getElementById("emailalias").disabled = false : document.getElementById("emailalias").disabled = true);\'>';
					$output .= '<option value="first.last">First Name . Last Name</option>';
					$output .= '<option value="firstlast">First Name + Last Name</option>';
					$output .= '<option value="firstl">First Name + Last Initial</option>';
					$output .= '<option value="username">WordPress Username</option>';
					$output .= '<option value="custom">Custom</option>';
					$output .= '</select></p>
					<p><label for="emailalias" style="vertical-align: top; float: left; width: 10em;">Custom E-Mail Alias:</label> <input type="text" name="emailalias" id="emailalias" value="" style="float: left;" disabled><label for="emailalias" style="vertical-align: top; float: left; width: 50em; margin-left: 1em;"><em>@ ' . $emaildomain . ' (Note: You must select the Custom option in the field above for this setting to be applied.)</em></label></p>
					<p>&nbsp;</p>
					<p>&nbsp;</p>
					<p><input type="submit" value="Save Settings" class="button button-primary"></p>
					<input type="hidden" name="uservalue" id="uservalue" value="na">
					</form>';
				} else {
					$output .= '<h2 style="display:inline;">Add New Account</h2> - <a href="users.php?page=emailmanager&mode=config">Configuration Options</a> | <a href="users.php?page=emailmanager&mode=list">List of Accounts</a> | <a href="users.php?page=emailmanager&mode=linkaccounts">Link Existing Accounts</a> | <a href="users.php?page=emailmanager&mode=mailinglist">Manage Mailing Lists</a><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">There are no unassigned WordPress user accounts.</p>';
				}
			} else {
				$output .= '<h2 style="display:inline;">Add New Account</h2> - <a href="users.php?page=emailmanager&mode=config">Configuration Options</a> | <a href="users.php?page=emailmanager&mode=list">List of Accounts</a> | <a href="users.php?page=emailmanager&mode=linkaccounts">Link Existing Accounts</a> | <a href="users.php?page=emailmanager&mode=mailinglist">Manage Mailing Lists</a><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">You have reached maximum capacity for e-mail accounts on this server.</p>
				<p style="text-align:center; font-weight: bold;font-size: 16px;">Please contact your e-mail hosting provider to increase capacity or delete an existing e-mail account to add another.</p>';
			}
		} else {
			$output .= '<h2 style="display:inline;">Add New Account</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">The cPanel credentials entered are not valid or a valid domain has not been selected. A connection could not be established.</p>
			<p style="text-align:center; font-weight: bold;font-size: 16px;">Please update your configuration settings <a href="users.php?page=emailmanager&mode=config">here</a>.</p>';
		}
	} else {
		$output .= '<h2 style="display:inline;">Add New Account</h2><p>&nbsp;</p><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">To use this plugin, please enter your configuration details <a href="users.php?page=emailmanager&mode=config">here</a>.</p>';
	}
	return $output;
}

function emailmanager_output_upgradeplugin() {
	global $wpdb;
	$output = '';
	$output .= '<h2 style="display:inline;">Upgrade Plugin</h2> - <a href="users.php?page=emailmanager&mode=config">Configuration Options</a><p>&nbsp;</p><p style="text-align:center; font-weight: bold;font-size: 16px;">To use advanced features of this plugin, please upgrade to the professional version. The professional version can be purchased from <a href="https://secure.insyncbiztech.com/clients/cart.php?gid=26" target="_blank">http://www.insyncbiztech.com</a>.</p>';
	$output .= '<p style="text-align:center; font-weight: bold;font-size: 16px;">Note: You must download the additional files required to use the professional version of this plugin before the advanced features will become active.</p>';
	return $output;
}

function emailmanager_user_change_password_display($user) {
	//Set config options
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
	$emaildomain = emailmanager_config_options("emailmgr_domain");
	$webmailurl = emailmanager_config_options("emailmgr_webmailurl");
	$optional['domain'] = $emaildomain;
	$accounts = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "getaccountlist", $optional));
	foreach ($accounts->data as $account) {
		if (emailmanager_emailalias_userid($account->user) == $user->ID) {
			$output = '<h3>E-Mail Account Information</h3>
			<table class="form-table">
			<tr>
			<th><label for="email">Your Current E-Mail Address</label></th>
			<td>';
			$output .= $account->login . ' (<a href="http://' . $webmailurl . '/login/?user=' . $account->login . '" target="_blank">Check Webmail</a>)';
			$output .= '</td>
			</tr>
			<tr>
			<th><label for="emailpass1">New E-Mail Password</label></th>
			<td>
			<input type="password" name="emailpass1" id="emailpass1" class="regular-text" size="16" value="" autocomplete="off" onkeyup="chkPasswordStrength(this.value);" />&nbsp;&nbsp;<span id="strenghtMsg" name="strenghtMsg"></span><br />
			<span class="description" for="emailpass1">If you would like to change your e-mail password, not your WordPress password, please enter it above. Otherwise leave this blank.</span>
			</td>
			</tr>
			<tr>
			<th scope="row"><label for="emailpass2">Repeat New E-Mail Password</label></th>
			<td>
			<input name="emailpass2" type="password" id="emailpass2" class="regular-text" size="16" value="" autocomplete="off" onkeyup="chkPasswordMatch(this.value);" /><input type="hidden" name="passwdstr" id="passwdstr">&nbsp;&nbsp;<span id="matchMsg" name="matchMsg"></span><br />
			<span class="description" for="emailpass2">Type your new e-mail password again.</span>
			</td>
			</tr>
			</table>
			<script type="text/javascript">
			function chkPasswordStrength(txtpass)
			   {
			     var desc = new Array();
			     desc[0] = "Very Weak (Password is not strong enough)";
			     desc[1] = "Weak (Password is not strong enough)";
			     desc[2] = "Better";
			     desc[3] = "Medium";
			     desc[4] = "Strong";
			     desc[5] = "Strongest";

			     strenghtMsg.innerHTML = "";
			     var score   = 0;

			     //if txtpass bigger than 6 give 1 point
			     if (txtpass.length > 6) score++;

			     //if txtpass has both lower and uppercase characters give 1 point
			     if ( ( txtpass.match(/[a-z]/) ) && ( txtpass.match(/[A-Z]/) ) ) score++;

			     //if txtpass has at least one number give 1 point
			     if (txtpass.match(/\d+/)) score++;

			     //if txtpass has at least one special caracther give 1 point
			     if ( txtpass.match(/.[!,@,#,$,%,^,&,*,?,_,~,-,(,)]/) ) score++;

			     //if txtpass bigger than 12 give another 1 point
			     if (txtpass.length > 12) score++;

			     strenghtMsg.innerHTML = "Strength: " + desc[score];
			     passwdstr.value = score;

			     if (txtpass.length < 6)
			     {
			     strenghtMsg.innerHTML = "Password Should be Minimum 6 Characters";
			     }
			   }
			function chkPasswordMatch(txtpass)
			   {
			     if (txtpass != document.getElementById("emailpass1").value) {
			     matchMsg.innerHTML = "Passwords do not match";
			     } else {
			     matchMsg.innerHTML = "";
			     }
			   }
			</script>';
			echo $output;
			break;
		}
	}
}

function emailmanager_user_change_password($user_id) {
	global $wpdb;
	if (current_user_can('edit_user',$user_id)) {
		$pass1 = (isset($_POST['emailpass1']) ? $_POST['emailpass1'] : "");
		$pass2 = (isset($_POST['emailpass2']) ? $_POST['emailpass2'] : "");
		$pwstrength = (isset($_POST['passwdstr']) ? $_POST['passwdstr'] : "");
		if ($pass1 != "") {
			if ($pass1 == $pass2) {
				if ($pwstrength >= 2) {
					$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
					$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
					$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
					$emaildomain = emailmanager_config_options("emailmgr_domain");
					$emailalias = emailmanager_usermeta($user_id, $wpdb->prefix . "emailmgr_alias");
					if ($cpaneluser != "" && $cpanelpass != "" && $cpanelserver != "" && $emaildomain != "") {
						$optional['email_domain'] = $emaildomain;
						$optional['email_user'] = $emailalias;
						$optional['password'] = $pass1;
						emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "changepw", $optional);
					}
				} else {
					add_filter('user_profile_update_errors', 'emailmanager_profile_upadate_password_error_strength', 10, 3);
				}
			} else {
				add_filter('user_profile_update_errors', 'emailmanager_profile_upadate_password_error_match', 10, 3);
			}
		}
	}
}

function emailmanager_profile_upadate_password_error_strength($errors, $update, $user) {
	$errors->add('email_password_error',__('The e-mail password provided does not meet minimum requirements.'));
}

function emailmanager_profile_upadate_password_error_match($errors, $update, $user) {
	$errors->add('email_password_error',__('The e-mail passwords provided do not match.'));
}

function emailmanager_dashboard_widget_function() {
	//Set config options
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
	$emaildomain = emailmanager_config_options("emailmgr_domain");
	if ($cpaneluser != "" && $cpanelpass != "" && $cpanelserver != "" && $emaildomain != "") {
		$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "diskusage"));
		if ($accountinfo->data->max != "") {
			$output = '<p>E-Mail Accounts: ';
			$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "emailcount"));
			$output .= $accountinfo->data->count . ' / ' . $accountinfo->data->max;
			$output .= '</p>
			<p>Disk Space Usage: ';
			$accountinfo = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "diskusage"));
			$output .= $accountinfo->data->count . ' ' . $accountinfo->data->units . ' / ' . $accountinfo->data->max;
			$output .= '</p>
			<p style="text-align:center;"><a href="users.php?page=emailmanager&mode=add">Add New Account</a> | <a href="users.php?page=emailmanager&mode=list">List of Accounts</a> | <a href="users.php?page=emailmanager&mode=config">Configuration Options</a></p>';
		} else {
			$output = '<p style="text-align:center;">The cPanel credentials entered are not valid or a valid domain has not been selected. A connection could not be established.</p>
			<p style="text-align:center;">Please update your configuration settings <a href="users.php?page=emailmanager&mode=config">here</a>.</p>';
		}
	} else {
			$output = '<p style="text-align:center;">To use this plugin, please enter your configuration details <a href="users.php?page=emailmanager&mode=config">here</a>.</p>';
	}
echo $output;
} 

/*
////////////// CPANEL API FUNCTIONS //////////////
*/

function emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, $action, $optional = null) {
	// CPANEL XML LOGIN
	$xmlapi = new xmlapi($cpanelserver);
	$xmlapi->set_port(2083);
	$xmlapi->set_output('xml');
	$xmlapi->password_auth($cpaneluser, $cpanelpass);
	$xmlapi->set_debug(1);

	/*
	//BEGIN XML FUNCTIONS
	*/

	//CREATE CPANEL EMAIL ACCOUNT
	if ($action == "checkcredentials") {
		return $xmlapi;
	}

	//CREATE CPANEL EMAIL ACCOUNT
	if ($action == "createemail") {
		$call = array('domain'=>$optional['email_domain'], 'email'=>$optional['email_user'], 'password'=>$optional['email_pass'], 'quota'=>$optional['email_quota']);
		return $xmlapi->api2_query($cpaneluser, "Email", "addpop", $call);
	}

	//DELETE CPANEL EMAIL ACCOUNT
	if ($action == "deleteemail") {
		$call = array('domain'=>$optional['email_domain'], 'email'=>$optional['email_user'],'flags'=>"passwd");
		$xmlapi->api2_query($cpaneluser, "Email", "delpop", $call);
	}

	//CHANGE PASSWORD
	if ($action == "changepw") {
		$call = array('domain'=>$optional['email_domain'], 'email'=>$optional['email_user'], 'password'=>$optional['password']);
		$xmlapi->api2_query($cpaneluser, "Email", "passwdpop", $call);
	}

	//ADD PARKED DOMAIN
	if ($action == "addparkeddomain") {
		$call = array('domain'=>$optional['domain']);
		return $xmlapi->api2_query($cpaneluser, "Park", "park", $call);
	}

	//LIST EMAIL DOMAINS
	if ($action == "listemaildomains") {
		return $xmlapi->api2_query($cpaneluser, "Email", "listmaildomains");
	}

	//GET EMAIL ACCOUNT DATA
	if ($action == "getaccountinfo") {
		$call = array('regex'=>$optional['email_user'] . "@");
		return $xmlapi->api2_query($cpaneluser, "Email", "listpopswithdisk", $call);
	}

	//GET ACCOUNT LIST
	if ($action == "getaccountlist") {
		$call = array('regex'=>'@'.$optional['domain']);
		return $xmlapi->api2_query($cpaneluser, "Email", "listpopswithdisk", $call);
	}

	//EMAIL ACCOUNT STATS
	if ($action == "emailcount") {
		$call = array('display'=>"emailaccounts");
		return $xmlapi->api2_query($cpaneluser, "StatsBar", "stat", $call);
	}

	//DISK SPACE INFO
	if ($action == "diskusage") {
		$call = array('display'=>"diskusage");
		return $xmlapi->api2_query($cpaneluser, "StatsBar", "stat", $call);
	}
}

/*
////////////// HELPER FUNCTIONS //////////////
*/

function emailmanager_generatePassword() {
	$alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
	$pass = array();
	$alphaLength = strlen($alphabet) - 1;
	for ($i = 0; $i < 8; $i++) {
		$n = rand(0, $alphaLength);
		$pass[] = $alphabet[$n];
	}
return implode($pass);
}

function emailmanager_config_options($option_name) {
	global $wpdb;
	if ($option_name != "") {
		if (EMAILMGR_CPANEL_GLOBAL == 1) {
			if ($option_name == "emailmgr_cpaneluser") {
				return EMAILMGR_CPANEL_USERNAME;
			} elseif ($option_name == "emailmgr_cpanelpass") {
				return EMAILMGR_CPANEL_PASSWORD;
			} elseif ($option_name == "emailmgr_cpanelip") {
				return EMAILMGR_CPANEL_SERVER;
			} else {
				$result = $wpdb->get_var( $wpdb->prepare( 
					"
					SELECT option_value 
					FROM $wpdb->options 
					WHERE option_name = %s
					", 
					$option_name
				) );
				if ($option_name == "emailmgr_cpaneluser" || $option_name == "emailmgr_cpanelpass") {
					$c = new Cpanelcipher(EMAILMGR_ENCRYPT_SALT);
					$result = $c->decrypt($result);
				}
			return $result;
			}

		} else {
			$result = $wpdb->get_var( $wpdb->prepare( 
				"
				SELECT option_value 
				FROM $wpdb->options 
				WHERE option_name = %s
				", 
				$option_name
			) );
			if ($option_name == "emailmgr_cpaneluser" || $option_name == "emailmgr_cpanelpass") {
				$c = new Cpanelcipher(EMAILMGR_ENCRYPT_SALT);
				$result = $c->decrypt($result);
			}
		return $result;
		}
	}
}

function emailmanager_usermeta($userid, $meta_key) {
	if ($meta_key != "" && $userid != "") {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( 
			"
			SELECT meta_value 
			FROM $wpdb->usermeta 
			WHERE user_id = %d AND meta_key = %s
			", 
			$userid, $meta_key
		) );
		return $result;
	}
}

function emailmanager_user_email($userid) {
	if ($userid != "") {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( 
			"
			SELECT user_email
			FROM $wpdb->users 
			WHERE id = %d
			", 
			$userid
		) );
		return $result;
	}
}

function emailmanager_emailalias_userid($emailalias = "") {
	global $wpdb;
	$result = $wpdb->get_var( $wpdb->prepare( 
		"
		SELECT user_id 
		FROM $wpdb->usermeta 
		WHERE meta_key = %s AND meta_value = %s
		", 
		$wpdb->prefix . 'emailmgr_alias', $emailalias
	) );
	return $result;
}

function emailmanager_emailalias_userid_assigned() {
	global $wpdb;
	$result = $wpdb->get_results( 
		"
		SELECT ID, user_id, user_nicename, user_login
		FROM $wpdb->usermeta
		INNER JOIN $wpdb->users ON user_id = ID
		WHERE meta_key = $wpdb->prefix . 'emailmgr_alias' AND meta_value <> '' 
		"
	);
	return $result;
}

function emailmanager_emailalias_userid_unassigned() {
	global $wpdb;
	$key = $wpdb->prefix . 'emailmgr_alias';
	$result = $wpdb->get_results( 
		"
		SELECT ID, user_id, user_nicename, user_login
		FROM $wpdb->usermeta tbl1
		INNER JOIN $wpdb->users ON user_id = ID
		WHERE (meta_key = '" . $key . "' AND meta_value = '') OR (NOT EXISTS (
		SELECT user_id 
		FROM $wpdb->usermeta tbl2
		WHERE meta_key = '" . $key . "' AND user_id =  ID)) GROUP BY user_id
		"
	);
	return $result;
}

function emailmanager_update_config($option_name, $option_value) {
	if ($option_name != "") {
		global $wpdb;
		if ($option_name == "emailmgr_cpaneluser" || $option_name == "emailmgr_cpanelpass") {
			$c = new Cpanelcipher(EMAILMGR_ENCRYPT_SALT);
			$option_value = $c->encrypt($option_value);
		}
		$wpdb->query( $wpdb->prepare( 
			"
			UPDATE $wpdb->options 
			SET option_value = %s
			WHERE option_name = %s
			",
			$option_value, $option_name 
		) );
	}
}

function emailmanager_assign_alias($firstname, $lastname, $username, $email, $customformat = null, $customalias = null) {
	//Set config options
	$autogenerate = (emailmanager_config_options("emailmgr_autogen") ? " checked" : "");
	$wordpressgroups = emailmanager_config_options("emailmgr_groups");
	$emaildomain = emailmanager_config_options("emailmgr_domain");
	$webmailurl = emailmanager_config_options("emailmgr_webmailurl");
	$cpaneluser = emailmanager_config_options("emailmgr_cpaneluser");
	$cpanelpass = emailmanager_config_options("emailmgr_cpanelpass");
	$cpanelserver = emailmanager_config_options("emailmgr_cpanelip");
	$emailmsg = emailmanager_config_options("emailmgr_emailmsg");
	$aliasformat = (!is_null($customformat) ? $customformat : emailmanager_config_options("emailmgr_aliasformat"));
	$sendemail = emailmanager_config_options("emailmgr_sendemail");

	if (!is_null($customalias) && $customalias != "") {
		$alias = $customalias;
	} else {
		$alias = "";
		if ($firstname != "" && $lastname != "") {
			if ($aliasformat == "first.last") {
				$alias = $firstname . "." . $lastname;
			} elseif ($aliasformat == "firstlast") {
				$alias = $firstname . $lastname;
			} elseif ($aliasformat == "firstl") {
				$alias = $firstname . substr($lastname, 0, 1);
			}
		}
		if ($username != "") {
			if ($aliasformat == "username") {
				$alias = $username;
			}
		}
		if ($alias == "") {
			$subalias = explode("@", $email);
			$alias = $subalias[0];
		}
	}
	if ($alias != "") {
		$cnt = 1;
		$optional['email_domain'] = emailmanager_config_options("emailmgr_domain");
		$optional['email_user'] = $alias;
		$aliascheck = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "getaccountinfo", $optional));
		$aliascheck = $aliascheck->data->user;

		while ($aliascheck != "") {
			$alias = $alias . "." . $cnt;
			$optional['email_domain'] = emailmanager_config_options("emailmgr_domain");
			$optional['email_user'] = $alias;
			$aliascheck = simplexml_load_string(emailmanager_cpanelAPI($cpaneluser, $cpanelpass, $cpanelserver, "getaccountinfo", $optional));
			$aliascheck = $aliascheck->data->user;
			$cnt++;
		}
	}
return $alias;
}

function array_sort($array, $on, $order=SORT_ASC)
{
	$new_array = array();
	$sortable_array = array();

	if (count($array) > 0) {
		foreach ($array as $k => $v) {
			if (is_array($v)) {
				foreach ($v as $k2 => $v2) {
					if ($k2 == $on) {
						$sortable_array[$k] = $v2;
					}
				}
			} else {
				$sortable_array[$k] = $v;
			}
		}

		switch ($order) {
			case SORT_ASC:
			asort($sortable_array);
			break;
			case SORT_DESC:
			arsort($sortable_array);
			break;
		}

		foreach ($sortable_array as $k => $v) {
			$new_array[$k] = $array[$k];
		}
	}

return $new_array;
}

function emailmanager_version() {
	if (EMAILMGR_STD_MODE == 1) {
		return "Standard";
	} else {
		return "Pro";
	}
}

/*
////////////// ENCRYPTION/DECRYPTION //////////////
*/

class Cpanelcipher
{
	private $securekey;
	private $iv_size;

	function __construct($textkey)
	{
		$this->iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
		$this->securekey = hash('sha256', $textkey, TRUE);
    	}

    	function encrypt($input)
    	{
		if ($input != "") {
        			$iv = mcrypt_create_iv($this->iv_size);
	        		return base64_encode($iv . mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->securekey, $input, MCRYPT_MODE_CBC, $iv));
		}
	}

	function decrypt($input)
	{
		if ($input != "") {
			$input = base64_decode($input);
			$iv = substr($input, 0, $this->iv_size);
			$cipher = substr($input, $this->iv_size);
			return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->securekey, $cipher, MCRYPT_MODE_CBC, $iv));
		}
	}
}

/*
////////////// ERROR LOGGING //////////////
*/

function emailmanager_resultLogging($action, $response = null) {
	if (!$response) {
		$response = "No error reported.";
	}
	$response = "Plugin Action: " . $action . " - " . $response;
	//OUTPUT TO WORDPRESS LOG FILE
	if (defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log("cPanel E-Mail Manager " . $response);
	}
//OUTPUT TO MODULE
return $response;
}
?>