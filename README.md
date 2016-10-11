# Attachments from FTP
WordPress plugin. Watches a specified folder in your web hosting. If one or more new image files are added to the folder, 
then a new [Attachment](https://codex.wordpress.org/Attachments) will be created for each of them. 

This plugin works using the WordPress Cron API.

## Instructions
Basic! Detailed instructions will follow soon. This plugin is in development and **MIGHT CAUSE ERRORS!!!** Don't use it yet unless you're helping me to debug it.

* Download the ZIP from this GitHub repository.
* Upload it in wp-admin and activate it.
* Create a direct subfolder in ``wp-content/uploads`` (or your equivalent) which the script will watch.
* Choose this folder and the desired author in the plugin settings. (Settings » Attachments from FTP.)
* Make sure that your server is accessing your Wordpress cron path. The folder will be checked once every hour by default.
* If you want to run the check manually, you can use the [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) plugin. The hook name is ``mhm-attachment-from-ftp/check_folder``.

## Notes
* Uploaded files should contain regular EXIF data relating to capture date, as well as (optional) values for image title and image caption/description. In Adobe Lightroom, this information is edited using the fields *Title* and *Caption* (under *Metadata* » *EXIF and IPTC*).
* A successfully processed file will be moved (not copied) to a destination folder within the regular WordPress uploads structure. The folder is determined from the original date and time when the photo was taken. (``DateTimeOriginal`` in the EXIF data.)
* For example, a photo taken on 16th October 2016 will usually be moved to the folder ``wp-content/uploads/2016/10``.
* If this data is not available in the EXIF, then the file cannot be processed and it will remain in the original folder to which you uploaded it.
* Files whose names contain spaces will be automatically re-named, in order to avoid compatability issues. For example, a file ``2016.10.16 1234.jpg`` will become ``2016.10.16_1234.jpg``. This renaming happens when the file is moved.
* If there is a file with the same (case-sensitive) name in the target directory, then it will be overwritten.
* When the file has been copied to the target directory, then the plugin generates new copies of any smaller files, e.g. thumbnails, which are defined in the general [Thumbnail Sizes](https://codex.wordpress.org/Post_Thumbnails#Thumbnail_Sizes) array.
* This plugin doesn't create any additional images of its own.
* If there is already an Attachment which refers to an image in precisely the same target location, then this entry will be updated and no new Attachment will be generated. 
* A pre-existing Attachment will be updated with the *Title* and *Caption* of the new image file. Any former, manually-edited caption or title will be overwritten.

##Author
Mark Howells-Mead | www.permanenttourist.ch | Since 11th October 2016

##License
Use this code freely, widely and for free. Provision of this code provides and implies no guarantee.

Please respect the GPL v3 licence, which is available via http://www.gnu.org/licenses/gpl-3.0.html
