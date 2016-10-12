# Attachments from FTP
WordPress plugin. Watches a specified folder in your web hosting. If one or more new image files are added to the folder, then a new [Attachment](https://codex.wordpress.org/Attachments) will be created for each of them. 

This plugin works using the WordPress Cron API.

## Instructions
This plugin is in development and **MIGHT CAUSE ERRORS!!!** Don't use it yet unless you're helping me to debug it.

Detailed instructions will be added as development progresses. The first official version of the plugin will be available directly from the WordPress plugin directory as soon as it is released.

As this plugin works with existing WordPress data in the database, and moves files around on the server, make sure that you make a backup of your site and files before using it. Test it with a couple of files before you commit to using it for large numbers of files.

## Installation and activation
* Download the ZIP from this GitHub repository.
* Upload the folder ``mhm-attachment-from-ftp`` to your plugin directory and activate the plugin in the WordPress Admin area.
* Create a direct subfolder in ``wp-content/uploads`` (or your equivalent) which the script will watch.
* Select this folder and the desired author in the plugin settings. (*Settings » Attachments from FTP*.)
* The plugin's function will be run once every hour by default. It works best if you ensure that a cron task is running on your server at least once per hour. [More information about WordPress cron](https://developer.wordpress.org/plugins/cron/).
* If you want to run the check manually, you can use the [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) plugin. The hook name is ``mhm-attachment-from-ftp/check_folder``.

## Plugin options
* **Source folder**: the folder on the server which will be watched by the cron task for new image files.
* **Post author**: the author in the website to whom new Attachments will be attributed.
* **Number of files to process**: how many images to process in a single batch. (See plugin options for futher details.)
* **Do not overwrite existing titles or descriptions**: if an Attachment already exists for the new file, then don't overwrite the title, the caption or the description when updating with the new file. Default: OFF. (Title and description will be overwritten, even if the new title and description are empty.)

## Notes
* The total number of files processed in a single run is limited. (See *Number of files to process* in the plugin options.)
* Uploaded files must contain regular EXIF data relating to capture date.
* The uploaded file may optionally - ideally - contain values for image title and image caption/description. In Adobe Lightroom, this information is edited using the fields *Title* and *Caption* (in the *EXIF and IPTC* view of the *Metadata* panel).
* Older images - i.e. photos taken first - will be processed first.
* A successfully processed file will be moved (not copied) to a destination folder within the regular WordPress uploads structure. The folder is determined from the original date and time when the photo was taken. (``DateTimeOriginal`` in the EXIF data.)
* For example, a photo taken on 16th October 2016 will usually be moved to the folder ``wp-content/uploads/2016/10``.
* If this data is not available in the EXIF, then the file cannot be processed and it will remain in the original folder to which you uploaded it.
* Files whose names contain spaces will be automatically re-named, in order to avoid compatability issues. For example, a file ``2016.10.16 1234.jpg`` will become ``2016.10.16_1234.jpg``. This renaming happens when the file is moved.
* If there is a file with the same (case-sensitive) name in the target directory, then it will be overwritten.
* When the file has been copied to the target directory, the plugin generates new copies of any smaller files - e.g. thumbnails - which are defined in the general [Thumbnail Sizes](https://codex.wordpress.org/Post_Thumbnails#Thumbnail_Sizes) array.
* This plugin doesn't create any additional images of its own.
* If there is already an Attachment which refers to an image in precisely the same target location, then this entry will be updated and no new Attachment will be generated.
* A pre-existing Attachment will be updated with the *Title* and *Caption* of the new image file. Any former, manually-edited caption or title will be overwritten unless the plugin option *Do not overwrite* is selected.

##Author
Mark Howells-Mead | www.permanenttourist.ch | Since 11th October 2016

##License
Use this code freely, widely and for free. Provision of this code provides and implies no guarantee.

Please respect the GPL v3 licence, which is available via http://www.gnu.org/licenses/gpl-3.0.html
