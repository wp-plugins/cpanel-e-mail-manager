=== cPanel E-Mail Manager ===
Contributors: (insyncbiztech)
Plugin Name: cPanel E-Mail Manager
Plugin URI: https://secure.insyncbiztech.com/clients/cart.php?gid=26
Tags: email, cpanel, hosting, list, management
Author URI: http://www.insyncbiztech.com
Author: InSync Business Technologies, LLC
Donate link: https://secure.insyncbiztech.com/clients/cart.php?pid=13
Requires at least: 3.0.1
Stable tag: trunk
Tested up to: 4.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use this plugin to manage e-mail accounts and mailing lists hosted on cPanel from the WordPress interface.

== Description ==
The WordPress cPanel E-Mail Manager plugin allows for the administration of cPanel e-mail accounts from the WordPress administration interface. 

How It Works

The WordPress cPanel E-Mail Manager plugin accomplishes the following:

* Create and remove cPanel e-mail accounts for WordPress users
* Link existing WordPress users with existing cPanel e-mail accounts
* Manage cPanel e-mail account quotas and passwords (Pro Version)
* Create and remove cPanel mailing lists (Pro Version)
* Send new e-mail account notifications to WordPress users (Pro Version) 
* Set global cPanel server credentials for WordPress Multisite (MU) setups with the professional version option (Pro Version)
* Compatible with BuddyPress new user registration (Pro Version)

Benefits

The WordPress cPanel E-Mail Manager plugin allows WordPress site administrators to offer site users with e-mail accounts linked to their WordPress profile and managed within the WordPress interface. 

== Installation ==
Option 1 - Search the WordPress Plugin Directory: 1). Login to the WordPress admin interface and navigate to Plugins -> Add New. 2). Use the search box and enter the term [cPanel E-Mail Manager]. 3). Click on the Install Now button next to the plugin details.

Option 2 - WordPress Upload: 1). Save the cPanel E-Mail Manager plugin zip file to your desktop. 2). Login to the WordPress admin interface and navigate to Plugins -> Add New. 3). Click on the Upload Plugin button at the top of the page and select the cPanel E-Mail Manager plugin zip file on your desktop. 4). Click the Install Now button to install the plugin.

Option 3 - Manual Upload: 1). Save the cPanel E-Mail Manager plugin zip file to your desktop. 2). Launch your FTP client and login to the WordPress directory. 3). Go to the plugins folder (/wp-content/plugins/) and upload the entire folder [cpanel-e-mail-manager] located in the cPanel E-Mail Manager zip file.

Additional configuration instructions are available by downloading the manual here: 
https://secure.insyncbiztech.com/clients/dl.php?type=d&id=9

== Frequently Asked Questions ==
For support using this product, please choose from one of the following:

= Submit a Support Ticket =
https://secure.insyncbiztech.com/clients/submitticket.php

= View F.A.Q.s =
https://secure.insyncbiztech.com/clients/knowledgebase.php

= Follow us on Facebook =
https://www.facebook.com/insyncbiztech

== Changelog ==
= 1.2 =
* Initial public release.
= 1.2.1 =
* Bug fix for mailing list membership display.
= 1.2.2 =
* Added ability to modify password on Add User page.
= 1.2.3 =
* Increased complexity of cPanel passwords generated for accounts auto-created with WordPress.
* Moved encryption salt to database to prevent overriding on updates.
* Note: Upgrading to v1.2.3 will require you to re-enter your cPanel credentials.
= 1.2.4 =
* Added ability to create and manage e-mail accounts not linked to current WordPress users.
* Minor bug fixes.
= 1.2.5 =
* Corrected security bug where non-admins could view plugin settings.
* Added control for choosing to delete e-mail accounts when a linked WordPress user is removed. (Previous versions did this automatically)
* Added capability for matching WordPress and cPanel e-mail account passwords during new user registrations. (Pro Version Only)
* Added capability to display password fields for new user registrations (client-side) allowing users to create their own password. (Pro Version Only)
* Added capability for disabling the WordPress New User generated e-mails. (Pro Version Only)
* Minor bug fixes.
= 1.2.6 =
* Corrected a bug with some installations using anonymous PHP functions.
= 1.2.7 =
* Split plugin core files and professional version features into separate plugins.
* Added special characters to password generator for stronger passwords.
* Minor bug fixes.
= 1.2.8 =
* Added front-end password update capability for BuddyPress installations.
* Enforced password strength settings on front-end password update.
= 1.2.9 =
* Added ability to set e-mail domain to other than the assigned site in an MU environment.

== Upgrade Notice ==
In order to get the most our of this plugin, please upgrade to the professional version at https://secure.insyncbiztech.com/clients/cart.php?gid=26.

== Screenshots ==
1. cPanel Server Credentials
1. cPanel E-Mail Manager Configuration Interface