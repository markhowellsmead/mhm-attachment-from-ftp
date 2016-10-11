# Attachments from FTP
WordPress plugin. Watches a specified folder in your web hosting. If one or more new image files are added to the folder, 
then a new [Attachment](https://codex.wordpress.org/Attachments) will be created for each of them. 

This plugin works using the WordPress Cron API.

# Instructions
Basic! Detailed instructions will follow soon. This plugin is in development and **MIGHT CAUSE ERRORS!!!** Don't use it yet unless you're helping me to debug it.

* Download the ZIP from this GitHub repository.
* Upload it in wp-admin and activate it.
* Create a direct subfolder in ``wp-content/uploads`` (or your equivalent) which the script will watch.
* Choose this folder and the desired author in the plugin settings. (Settings » Attachments from FTP.)
* Make sure that your server is accessing your Wordpress cron path. The folder will be checked once every hour by default.
* If you want to run the check manually, you can use the [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) plugin. The hook name is ``mhm-attachment-from-ftp/check_folder``.

##Author
Mark Howells-Mead | www.permanenttourist.ch | Since 11th October 2016

##License
Use this code freely, widely and for free. Provision of this code provides and implies no guarantee.

Please respect the GPL v3 licence, which is available via http://www.gnu.org/licenses/gpl-3.0.html
