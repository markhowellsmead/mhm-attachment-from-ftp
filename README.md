# Attachments from FTP
WordPress plugin. Watches a specified folder in your web hosting. If one or more new image files are added to the folder, then a new [Attachment](https://codex.wordpress.org/Attachments) will be created for each of them.

This plugin works using the WordPress Cron API.

## Instructions
This plugin has not yet reached version 1.0 and is not available from the WordPress plugin directory. Use it at your own risk.

As this plugin works with existing WordPress data in the database, and moves files around on the server, make sure that you make a backup of your site and files before using it. Test it with a couple of files before you commit to using it for large numbers of files.

## Installation and activation
* Download the ZIP from this GitHub repository.
* Upload the folder ``mhm-attachment-from-ftp`` to your plugin directory and activate the plugin in the WordPress Admin area.
* Create a direct subfolder in ``wp-content/uploads`` (or your equivalent) which the script will watch.
* Select this folder and the desired author in the plugin settings. (*Settings » Attachments from FTP*.)
* On activation, the plugin schedules a WordPress cron event which runs **every minute**. It works best if you ensure that a cron task is running on your server at least as often as you need the folder to be checked. [More information about WordPress cron](https://developer.wordpress.org/plugins/cron/).
* If you want to run the check manually, you can use the [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) plugin. The hook name is ``mhm_attachment_from_ftp_check_folder``.
* Nothing is processed until both a source folder and a post author have been selected.

## Plugin options
* **Source folder**: the folder on the server which will be watched by the cron task for new image files.
* **Post author**: the author in the website to whom new Attachments will be attributed.
* **Number of files to process**: how many images to process in a single batch (10, 25, 50 or 100). Each file requires thumbnails to be generated, so keep this number low unless you have a high-performance server.
* **Do not overwrite existing titles or descriptions**: if an Attachment already exists for the new file, then don't overwrite the title, the caption or the description when updating with the new file. Default: OFF. (Title and description will be overwritten, even if the new title and description are empty.)
* **Notify when an error occurs**: the site administrator receives an email about errors during the import process.
* **Notify on success**: the site administrator receives an email when files have been imported successfully.
* **Notify on regular**: the site administrator receives an email about all events of the import process (errors and everything else which is written to the log files).

Notifications are sent to the site administrator email address (*Settings » General*). So that a problem which repeats every minute cannot flood your inbox, the events are collected and sent together in a single digest email, **at most once per hour**. The first event is emailed immediately. Identical events within the digest are summarised, with a count and an example. Events are always written to the log files, whether or not they are emailed.

## Notes
* Files in subfolders of your selected folder are processed too. ``.DS_Store`` files and files which are not an allowed file type (JPEG, GIF, PNG, BMP and TIFF by default) are ignored.
* The total number of files processed in a single run is limited. (See *Number of files to process* in the plugin options.) Files are processed in the order in which the photos were taken (``DateTimeOriginal``), oldest first.
* Files larger than 10 MB are not processed and remain in the original folder.
* Uploaded files must contain regular EXIF data relating to capture date.
* The uploaded file may optionally - ideally - contain values for image title and image caption/description. In Adobe Lightroom, this information is edited using the fields *Title* and *Caption* (in the *EXIF and IPTC* view of the *Metadata* panel).
* A successfully processed file will be moved (not copied) to a destination folder within the regular WordPress uploads structure. The folder is determined from the original date and time when the photo was taken. (``DateTimeOriginal`` in the EXIF data.)
* For example, a photo taken on 16th October 2016 will usually be moved to the folder ``wp-content/uploads/2016/10``.
* If this data is not available in the EXIF, then the file cannot be processed and it will remain in the original folder to which you uploaded it.
* Files whose names contain spaces will be automatically re-named, in order to avoid compatability issues. For example, a file ``2016.10.16 1234.jpg`` will become ``2016.10.16_1234.jpg``. This renaming happens as soon as the plugin looks at the file, even if the file is not processed afterwards.
* If there is a file with the same (case-sensitive) name in the target directory, then it will be overwritten.
* When the file has been copied to the target directory, the plugin generates new copies of any smaller files - e.g. thumbnails - which are defined in the general [Thumbnail Sizes](https://codex.wordpress.org/Post_Thumbnails#Thumbnail_Sizes) array.
* This plugin doesn't create any additional images of its own.
* If there is already an Attachment which refers to an image in precisely the same target location, then this entry will be updated and no new Attachment will be generated.
* The IPTC *Object Name* (``2#005``) becomes the Attachment title and the IPTC caption (``2#120``) becomes its description and caption. The title is also used as the alternative text, if the Attachment does not already have one.
* IPTC keywords are stored in the attachment metadata (``image_meta['keywords']``) by WordPress core when the attachment is generated. This plugin does not add or change them. It reads the keywords from the file itself only to show them in the *Media » For import* list, and to pass them on as ``post_tags`` in the data used by the ``mhm-attachment-from-ftp/finished`` action. The IPTC category is added to the attachment metadata as ``image_meta['category']``.
* If the file contains GPS data, the coordinates are converted to decimal values and passed on with the other import data. They are not stored by this plugin.
* *Media » For import* lists the files which are currently waiting in the source folder, with a preview, title, keywords, GPS coordinates, camera and file size.
* The plugin appends to two log files in ``wp-content``: ``mhm_attachment_from_ftp.success.log`` (imports and updates) and ``mhm_attachment_from_ftp.error.log`` (files which could not be processed, and configuration problems). They can be read in *Media » Import log*.
* *Media » Import log* shows the entries of both log files, newest first, 100 per page, on the tabs *Errors* and *Success*. Long details can be expanded. The page is available to users who can manage options. The files are read from the end, so that even a large log file loads quickly. The log files are not trimmed automatically.
* A pre-existing Attachment will be updated with the *Title* and *Caption* of the new image file. Any former, manually-edited caption or title will be overwritten unless the plugin option *Do not overwrite* is selected.

## Detailed process description
Once the plugin is active and the plugin options are set, then the plugin uses WordPress' internal “cron” process to check a folder on your web server every minute. If there are any images in the folder, then these are destined to become Attachment entries, which you can view in the *Media* section of WordPress admin and use in your Posts or Pages.

So that a file can be correctly processed, the [EXIF data](https://photographylife.com/what-is-exif-data) needs to contain the original capture date and time of the photo. If you're uploading a normal photo, then this information is available. If you edit the photo in an image editing programme like Photoshop or Lightroom, then make sure that the export function doesn't remove the EXIF data. If you've given the photo a title and description in your image editing programme, this will be automatically added to the Attachment entry in WordPress.

If you upload a photo which you've already added, for example after re-editing, with a new title or description, then the previous entry will be updated and the original file will be replaced. WordPress automatically creates smaller versions of each image, for use as a smaller version or as a thumbnail.

## Actions and filters
### Actions
``mhm-attachment-from-ftp/no_files`` fires when there are no files in the folder.
* Arguments:
	* Source folder (string)

``mhm-attachment-from-ftp/no_file_date`` fires when the selected file has no creation date available in its EXIF data.
* Arguments:
	* File path (string)
	* EXIF data (array)

``mhm-attachment-from-ftp/no_valid_entries`` fires when there are no valid files in the folder.
* Arguments:
	* Folder path (string)
	* Files (array)

``mhm-attachment-from-ftp/finished`` fires when the process has completely finished.
* Arguments:
	* Entries which were prepared for processing (array)
	* Number of files which were processed (integer)

``mhm-attachment-from-ftp/source-folder-undefined`` fires when the source folder is not defined.
* Arguments:
	* Source folder (string)

``mhm-attachment-from-ftp/post-author-undefined`` fires when the post author has not been correctly defined.
* Arguments:
	* Source folder (string)

``mhm-attachment-from-ftp/source-folder-unavailable`` fires when the indicated source folder is not available on the server.
* Arguments:
	* Folder path (string)

``mhm-attachment-from-ftp/filetype-not-allowed`` fires when a file has a MIME type which is not in the ``allowed_file_types`` array.
* Arguments:
	* File path (string)
	* The MIME type of the file (string)
	* The array of allowed file types. (array)

``mhm-attachment-from-ftp/target_folder_missing`` fires when the target directory, to which a file is to be moved, does not exist.
* Arguments:
	* Folder path (string)

``mhm-attachment-from-ftp/file_moved`` fires after a file is successfully moved.
* Arguments:
	* Source path (string)
	* Target path (string)

``mhm-attachment-from-ftp/file_not_moved`` fires after a file cannot be successfully moved.
* Arguments:
	* Source path (string)
	* Target path (string)

``mhm-attachment-from-ftp/title_description_overwritten`` fires when the title and description of a pre-existing Attachment have been replaced.
* Arguments:
	* Attachment ID (integer)
	* New Attachment data (array)

``mhm-attachment-from-ftp/attachment_updated`` fires when an Attachment has been updated in the database.
* Arguments:
	* Attachment ID (integer)

``mhm-attachment-from-ftp/attachment_created`` fires when an Attachment has been created in the database.
* Arguments:
	* Attachment ID (integer)

``mhm-attachment-from-ftp/updated_attachment_metadata`` fires when the metadata from the Attachment has been updated in the database.
* Arguments:
	* Attachment ID (integer)
	* File path (string)
	* Generated attachment metadata (array)

``mhm-attachment-from-ftp/too_big`` fires if a source file is too large. The current limit is 10Mb. This is in order to avoid massive files killing server resources.
* Arguments:
	* File path (string)
	* File size descriptor (string)

### Filters
``mhm-attachment-from-ftp/files-in-folder``
* Arguments:
	* Files in the folder (array)

``mhm-attachment-from-ftp/allowed-file-types``
* Arguments:
	* Array of allowed file types (array)

## REST API
The plugin adds the post fields ``video_ref`` and ``location`` to the REST API for posts. When a post is saved through the REST API with ``meta.tags`` and/or ``meta.categories``, these values are assigned to the post as tags and categories.

## Author
Mark Howells-Mead | www.permanenttourist.ch | Since 11th October 2016

## License
Use this code freely, widely and for free. Provision of this code provides and implies no guarantee.

Please respect the GPL v3 licence, which is available via http://www.gnu.org/licenses/gpl-3.0.html

## Changelog

### 0.9.0
* New: *Media » Import log* shows the plugin's error and success logs, newest first, paginated with 100 entries per page.
* New: The email notifications (*Notify when an error occurs*, *Notify on success*, *Notify on regular*) are available again, and now work. Emails are sent to the site administrator as a digest, at most once per hour, with identical events summarised.

### 0.8.10
* Fix: The batch of files is now processed oldest first, by capture date. Previously the order depended on the image titles.
* Fix: Two photos with exactly the same capture time are now both processed. Previously one of them replaced the other.
* Fix: The custom cron interval ``minute`` no longer replaces all other cron schedules registered by WordPress and other plugins.
* Fix: The REST API field ``location`` returned the value of ``video_ref``.
* Removed: The *Notify when an error occurs*, *Notify on success* and *Notify on regular* options. They were saved but never used, and no emails were ever sent.

### 0.8.9
* Documentation: The README now matches the behaviour of the plugin. Corrections: the cron task runs every minute (not hourly) and the hook name is ``mhm_attachment_from_ftp_check_folder``; subfolders are processed; files are not guaranteed to be processed oldest first; the *Notify…* options are not currently used; the arguments of three actions. Added the file size limit, the *For import* page, log files, how titles, keywords and GPS data are handled, and the REST fields.

### 0.8.8
* The plugin's repository is now hosted on GitHub: https://github.com/markhowellsmead/mhm-attachment-from-ftp (previously Bitbucket). The Plugin URI has been updated.

### 0.8.7
* Fix: Stop overwriting the `keywords` value which WordPress core stores in the attachment's `image_meta`. Core already extracts the IPTC keywords; the plugin's own copy was unprocessed (no re-indexing or encoding handling) and could raise an undefined index warning for files with an IPTC category but no keywords.
