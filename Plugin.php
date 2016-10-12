<?php
/*
Plugin Name: Attachments from FTP
Description: Watches a specified folder in your web hosting. If a new image file is added, then a new Attachment Post will be created. This plugin uses the WordPress Cron API.
Plugin URI: #
Text Domain: mhm-attachment-from-ftp
Author: Mark Howells-Mead
Author URI: https://permanenttourist.ch/
Version: 0.2
*/

namespace MHM\WordPress\AttachmentFromFtp;

use Wp_Query;

class Plugin
{
    public $version = '0.1';
    public $wpversion = '4.5';
    public $frequency = 'hourly'; // Fixed. A filter is coming soon to allow customization.
    private $sourceFolder = '';
    private $author_id = -1;
    private $allowed_file_types = array();

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activation'));
        register_deactivation_hook(__FILE__, array($this, 'deactivation'));

        if (is_admin()) {
            require_once 'OptionsPage.php';
            new OptionsPage();
        }
        $this->setThings();

        add_action('admin_init', array($this, 'checkVersion'));
        add_action('mhm-attachment-from-ftp/check_folder', array($this, 'checkFolder'));
        add_filter('wp_read_image_metadata', array($this, 'additionalImageMeta'), 10, 3);
    }

    private function debug($message, $method = __METHOD__)
    {
        if (gettype($message) !== 'string') {
            $message = print_r($message, 1);
        }
        error_log($message);
    }

    public function activation()
    {
        $this->checkVersion();

        if (!wp_next_scheduled('mhm-attachment-from-ftp/check_folder')) {
            wp_schedule_event(time(), $this->frequency, 'mhm-attachment-from-ftp/check_folder');
        }
        $this->setThings();
    }

    public function deactivation()
    {
        wp_clear_scheduled_hook('mhm-attachment-from-ftp/check_folder');
    }

    public function checkVersion()
    {
        // Check that this plugin is compatible with the current version of WordPress
        if (!$this->compatibleVersion()) {
            if (is_plugin_active('mhm-attachment-from-ftp')) {
                deactivate_plugins('mhm-attachment-from-ftp');
                add_action('admin_notices', array($this, 'disabledNotice'));
                if (isset($_GET['activate'])) {
                    unset($_GET['activate']);
                }
            }
        }
    }

    public function disabledNotice()
    {
        $message = sprintf(
            __('The plugin “%1$s” requires WordPress %2$s or higher!', 'mhm-attachment-from-ftp'),
            _x('Automatically publish photos', 'The name of the plugin', 'mhm-attachment-from-ftp'),
            $this->wpversion
        );

        printf(
            '<div class="notice notice-error is-dismissible"><p>%1$s</p></div>',
            $message
        );
    }

    private function compatibleVersion()
    {
        if (version_compare($GLOBALS['wp_version'], $this->wpversion, '<')) {
            return false;
        }

        return true;
    }

    private function sanitizeFileName($file)
    {
        $path_original = $file->getPathName();
        $filtered_filename = preg_replace('~\s~', '_', $file->getFileName());
        $filtered_path = str_replace($file->getFilename(), $filtered_filename, $path_original);

        rename($path_original, $filtered_path);

        return $filtered_path;
    }

    /**
     * The main function, which is called by the cron task registered by
     * mhm-attachment-from-ftp/check_folder. If all is well, no text is
     * output (and therefore no message will appear).
     */
    public function checkFolder()
    {
        $this->setThings();

        if (empty($this->sourceFolder) || !$this->author_id) {
            return;
        }

        $files = $this->getFiles();

        if (empty($files)) {
            do_action('mhm-attachment-from-ftp/no_files', $this->sourceFolder);

            return;
        }

        $entries = array();

        foreach ($files as $file) {
            $file_path = $this->sanitizeFileName($file);

            $exif = $this->buildEXIFArray($file_path, false);

            if (!$exif['DateTimeOriginal']) {
                /*
                 * Only images where the original capture date can be identified
                 * can currently be processed.
                 */
                do_action('mhm-attachment-from-ftp/no_file_date', $filepath, $exif);
                continue;
            }

            $target_folder = $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads'.date('/Y/m/', strtotime($exif['DateTimeOriginal']));

            $entries[strtotime($exif['DateTime'])] = array(
                'post_author' => $this->author_id,
                'post_type' => $this->post_type,
                'post_title' => (string) $exif['iptc']['graphic_name'],
                'post_content' => (string) $exif['iptc']['caption'],
                'post_tags' => $exif['iptc']['keywords'],
                'file_date' => $exif['DateTimeOriginal'],
                'source_path' => $file_path,
                'target_path' => $target_folder.$file->getFileName(),
                'target_folder' => $target_folder,
                'file_name' => $file->getFileName(),
                'post_meta' => array(),
            );
        }

        if (empty($entries)) {
            do_action('mhm-attachment-from-ftp/no_valid_entries', $this->basePath, $files);
            exit;
        }

        // Handle older photos first, sorted by EXIF DateTime parameter.
        sort($entries);

        $processed_entries = 0;

        foreach ($entries as $entry) {
            $stored = (bool) $this->storeImage($entry);

            if ($stored) {
                // Create or update existing Attachment Posts and generate thumbnails
                $attachment_id = $this->handleAttachment($entry);
                ++$number_of_entries;
            }
        }

        do_action('mhm-attachment-from-ftp/finished', $entries, $processed_entries);
    }

    /**
     * Display a message in wp-admin to advise the user that the source folder
     * has not been selected in the plugin options.
     */
    private function sourceFolderUndefined()
    {
        add_action('admin_notices', function () {
            $class = 'notice notice-error';
            $message = sprintf(
                __('Please %1$s for the plugin “%2$s”.', 'mhm-attachment-from-ftp'),
                sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('options-general.php?page=mhm_attachment_from_ftp'),
                    __('select a source folder', 'mhm-attachment-from-ftp')
                ),
                __('Attachments from FTP', 'mhm-attachment-from-ftp')
            );

            printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
        });
        do_action('mhm-attachment-from-ftp/source-folder-undefined', $this->sourceFolder);
    }

    /**
     * Display a message in wp-admin to advise the user that the post author
     * has not been selected in the plugin options.
     */
    private function postAuthorUndefined()
    {
        add_action('admin_notices', function () {
            $class = 'notice notice-error';
            $message = sprintf(
                __('Please %1$s for the plugin “%2$s”.', 'mhm-attachment-from-ftp'),
                sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('options-general.php?page=mhm_attachment_from_ftp'),
                    __('select the post author', 'mhm-attachment-from-ftp')
                ),
                __('Attachments from FTP', 'mhm-attachment-from-ftp')
            );

            printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
        });
        do_action('mhm-attachment-from-ftp/post-author-undefined', $this->sourceFolder);
    }

    private function setAllowedFileTypes()
    {
        $this->allowed_file_types = apply_filters('mhm-attachment-from-ftp/allowed-file-types', array(
            'image/jpeg',
            'image/gif',
            'image/png',
            'image/bmp',
            'image/tiff',
        ));
    }

    private function setThings()
    {
        $this->setPostAuthor();
        $this->setAllowedFileTypes();
        $this->setSourceFolder();
    }

    /**
     * Get the source folder value from the plugin options.
     */
    public function setSourceFolder()
    {
        $options = get_option('mhm_attachment_from_ftp');
        $upload_dir = wp_upload_dir();
        $sourceFolder = esc_attr($options['source_folder']);

        if (!$sourceFolder) {
            $this->sourceFolderUndefined();
        } else {
            $this->sourceFolder = trailingslashit($upload_dir['basedir']).esc_attr($options['source_folder']);

            if (!is_dir($this->sourceFolder)) {
                @mkdir($this->sourceFolder, 0755, true);
                if (is_dir($this->sourceFolder)) {
                    do_action('mhm-attachment-from-ftp/source-folder-unavailable', $this->sourceFolder);
                }
            }
        }
    }

    /**
     * Get the post author value from the plugin options.
     */
    public function setPostAuthor()
    {
        $options = get_option('mhm_attachment_from_ftp');
        $this->author_id = (int) $options['author_id'];

        if (!$this->author_id) {
            $this->postAuthorUndefined();
        }
    }

    /**
     * Get a list of files from the source folder.
     *
     * @return array A list of the files which are available to be processed.
     */
    private function getFiles()
    {
        if (!is_dir($this->sourceFolder)) {
            return false;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->sourceFolder), \RecursiveIteratorIterator::CHILD_FIRST);
        $out = array();
        foreach ($iterator as $path) {
            $filetype = wp_check_filetype($path);
            if (in_array($filetype['type'], $this->allowed_file_types)) {
                $out[] = $path;
            } else {
                do_action('mhm-attachment-from-ftp/filetype-not-allowed', $path, $filetype['type'], $this->allowed_file_types);
            }
        }

        return apply_filters('mhm-attachment-from-ftp/files-in-folder', $out);
    }

    /**
     * Move a file from one folder to another on the server.
     *
     * @param string $currentpath     The fully-qualified path of the file's current location.
     * @param string $destinationpath The fully-qualified path of the file's new location, including the file name.
     *
     * @return bool True if successful, false if not.
     */
    private function moveFile($currentpath, $destinationpath)
    {
        if (!is_file($currentpath)) {
            return false;
        }

        return @rename($currentpath, $destinationpath) ? $destinationpath : false;
    }

    /**
     * Extend the basic meta data stored in the database with additional values.
     *
     * @param array  $meta            The array of meta data which WordPress usually stores in the database.
     * @param string $file            The fully-qualified path to the file being processed.
     * @param int    $sourceImageType The MIME type of the file being processed, in binary integer format.
     *
     * @return array The processed and extended meta data.
     */
    public function additionalImageMeta($meta, $file, $sourceImageType)
    {
        $image_file_types = apply_filters(
            'wp_read_image_metadata_types',
            array(IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM)
        );

        if (in_array($sourceImageType, $image_file_types) && function_exists('iptcparse')) {
            getimagesize($file, $info);
            $iptc = iptcparse($info['APP13']);
            if ($iptc) {
                $meta['category'] = $iptc['2#015'];
                $meta['keywords'] = $iptc['2#025'];
            }
        }

        return $meta;
    }

    /**
     * Moves the file from the original location to the target location.
     * If a file in the target location already exists, it will be overwritten.
     *
     * @param array $post_data The image data build in checkFolder()
     *
     * @return bool True on success, false on fail.
     */
    public function storeImage($post_data)
    {
        /*
         * Make sure that the target folder exists.
         */
        if (!is_dir($post_data['target_folder'])) {
            @mkdir($post_data['target_folder'], 0755, true);
            if (!is_dir($post_data['target_folder'])) {
                do_action('mhm-attachment-from-ftp/target_folder_missing', $post_data['target_folder']);

                return false;
            }
        }
        $file_moved = @rename($post_data['source_path'], $post_data['target_path']);
        if ($file_moved) {
            do_action('mhm-attachment-from-ftp/file_moved', $post_data['source_path'], $post_data['target_path']);
        } else {
            do_action('mhm-attachment-from-ftp/file_not_moved', $post_data['source_path'], $post_data['target_path']);
        }

        return $file_moved;
    }

    /**
     * Helper function which “cleans” the entries in $array by passing them
     * through the function $function.
     *
     * @param string $function The name of the function to use when “cleaning”
     *                         the array.
     * @param array  $array    The array to be cleaned.
     *
     * @return array The cleaned array.
     */
    public function arrayMapRecursive($function, $array)
    {
        $newArr = array();

        foreach ($array as $key => $value) {
            $newArr[ $key ] = (is_array($value) ? $this->arrayMapRecursive($function, $value) : (is_array($function) ? call_user_func_array($function, $value) : $function($value)));
        }

        return $newArr;
    }

    public function sanitizeData(&$data)
    {
        $data = $this->arrayMapRecursive('strip_tags', $data);
    }

    /**
     * Get the Attachment which matches the specified file path.
     * If no matching Attachment is found, the function returns 0.
     *
     * @param string $path The fully-qualified path to the file.
     *
     * @return int The ID of the Attachment, or 0 if none is found.
     */
    private function getAttachmentId($path)
    {
        $attachment_id = 0;
        $dir = wp_upload_dir();

        // Is the file somewhere in the in uploads directory?
        if (false !== strpos($path, $dir['basedir'].'/')) {
            $file = basename($path);
            $query_args = array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'fields' => 'ids',
                'meta_query' => array(
                    array(
                        'value' => $file,
                        'compare' => 'LIKE',
                        'key' => '_wp_attachment_metadata',
                    ),
                ),
            );
            $query = new WP_Query($query_args);
            if ($query->have_posts()) {
                foreach ($query->posts as $post_id) {
                    $meta = wp_get_attachment_metadata($post_id);
                    $original_file = basename($meta['file']);
                    $cropped_image_files = wp_list_pluck($meta['sizes'], 'file');
                    if ($original_file === $file || in_array($file, $cropped_image_files)) {
                        $attachment_id = $post_id;
                        break;
                    }
                }
            }
        }

        return $attachment_id;
    }

    /**
     * Add or update a database Post entry of type Attachment.
     *
     * @param array $post_data An associative array containing all the data to be stored.
     *
     * @return int The ID of the new (or pre-existing) Post.
     */
    public function handleAttachment($post_data)
    {
        $target_path = $post_data['target_path'];

        $upload_dir = wp_upload_dir();

        $attachment_id = $this->getAttachmentId($target_path);

        if ($attachment_id) {
            /*
             * Entry exists. Update it and re-generate thumbnails. Title and description are only upated if the
             * appropriate blocking option “no_overwrite_title_description” is not activated in the plugin options.
             */
            $options = get_option('mhm_attachment_from_ftp');

            if (!(bool) $options['no_overwrite_title_description']) {
                $wp_filetype = wp_check_filetype(basename($target_path), null);
                $info = pathinfo($target_path);
                $attachment = array(
                    'ID' => $attachment_id,
                    'post_author' => $post_data['post_author'],
                    'post_content' => $post_data['post_content'],
                    'post_excerpt' => $post_data['post_content'],
                    'post_mime_type' => $wp_filetype['type'],
                    'post_name' => $info['filename'],
                    'post_status' => 'inherit',
                    'post_title' => $post_data['post_title'],
                );
                $attachment_id = wp_update_post($attachment);
                do_action('mhm-attachment-from-ftp/title_description_overwritten', $attachment_id, $attachment);
            }
            do_action('mhm-attachment-from-ftp/attachment_updated', $attachment_id);
            $this->thumbnailsAndMeta($attachment_id, $target_path);
        } else {
            /*
             * Create new attachment entry and generate thumbnails.
             */
            $wp_filetype = wp_check_filetype(basename($target_path), null);
            $info = pathinfo($target_path);
            $attachment = array(
                'post_author' => $post_data['post_author'],
                'post_content' => $post_data['post_content'],
                'post_excerpt' => $post_data['post_content'],
                'post_mime_type' => $wp_filetype['type'],
                'post_name' => $info['filename'],
                'post_status' => 'inherit',
                'post_title' => $post_data['post_title'],
            );
            $attachment_id = wp_insert_attachment($attachment, $target_path);
            do_action('mhm-attachment-from-ftp/attachment_created', $attachment_id);
            $this->thumbnailsAndMeta($attachment_id, $target_path);
        }

       /*
         * Add the image's title attribute to the alt text field of the Attachment.
         * This only happens if there is no pre-existing alt text stored for the image.
         */
        if (!get_post_meta($attachment_id, '_wp_attachment_image_alt', true)) {
            add_post_meta($attachment_id, '_wp_attachment_image_alt', $post_data['post_title']);
        }

        return $attachment_id;
    }

    /**
     * Update metadata entries in the database and generate
     * thumbnails from the new, parsed image file.
     *
     * @param int    $post_id The ID of the Attachment post
     * @param string $path    The path to the new image file.
     */
    public function thumbnailsAndMeta($post_id, $path)
    {
        require_once ABSPATH.'wp-admin/includes/image.php';

        // This generates the thumbnail file/s.
        $attach_data = wp_generate_attachment_metadata($post_id, $path);

        wp_update_attachment_metadata($post_id, $attach_data);

        do_action('mhm-attachment-from-ftp/updated_attachment_metadata', $post_id, $path);
    }

    /**
     * Convert GPS DMS (degrees, minutes, seconds) to decimal format
     * (longitude/latitude).
     *
     * @param int $deg Degrees
     * @param int $min Minutes
     * @param int $sec Seconds
     *
     * @return int The converted decimal-format value.
     */
    private static function DMStoDEC($deg, $min, $sec)
    {
        return $deg + ((($min * 60) + ($sec)) / 3600);
    }

    /**
     * Read the EXIF/IPTC data from a file on the file system and
     * return it as an associative array.
     *
     * @param string $source_path     The fully-qualified path to the file.
     * @param bool   $onlyWithGPSData Should the associative array be left
     *                                empty if there is no GPS meta data
     *                                available in the source file?
     *
     * @return array The array containing the parsed EXIF/IPTC data
     */
    public function buildEXIFArray($source_path, $onlyWithGPSData = false)
    {
        $exif = @exif_read_data($source_path, 'ANY_TAG');

        if (!$exif || ($onlyWithGPSData && (!isset($exif['GPSLongitude']) || !isset($exif['GPSLongitude'])))) {
            return false;
        }

        /*
        Example of the values in the file's EXIF data:

        [GPSLatitudeRef] => N
        [GPSLatitude] => Array
        (
            [0] => 57/1
            [1] => 31/1
            [2] => 21334/521
        )

        [GPSLongitudeRef] => W
        [GPSLongitude] => Array
        (
            [0] => 4/1
            [1] => 16/1
            [2] => 27387/1352
        )
        */

        $GPS = array();

        if (isset($exif['GPSLatitude'])) {
            $GPS['lat']['deg'] = explode('/', $exif['GPSLatitude'][0]);
            $GPS['lat']['deg'] = $GPS['lat']['deg'][0] / $GPS['lat']['deg'][1];
            $GPS['lat']['min'] = explode('/', $exif['GPSLatitude'][1]);
            $GPS['lat']['min'] = $GPS['lat']['min'][0] / $GPS['lat']['min'][1];
            $GPS['lat']['sec'] = explode('/', $exif['GPSLatitude'][2]);
            $GPS['lat']['sec'] = $GPS['lat']['sec'][1] !== 0 ? floatval($GPS['lat']['sec'][0]) / floatval($GPS['lat']['sec'][1]) : 0;

            $exif['GPSLatitudeDecimal'] = self::DMStoDEC($GPS['lat']['deg'], $GPS['lat']['min'], $GPS['lat']['sec']);
            if ($exif['GPSLatitudeRef'] == 'S') {
                $exif['GPSLatitudeDecimal'] = 0 - $exif['GPSLatitudeDecimal'];
            }
        } else {
            $exif['GPSLatitudeDecimal'] = null;
            $exif['GPSLatitudeRef'] = null;
        }

        if (isset($exif['GPSLongitude'])) {
            $GPS['lon']['deg'] = explode('/', $exif['GPSLongitude'][0]);
            $GPS['lon']['deg'] = $GPS['lon']['deg'][0] / $GPS['lon']['deg'][1];
            $GPS['lon']['min'] = explode('/', $exif['GPSLongitude'][1]);
            $GPS['lon']['min'] = $GPS['lon']['min'][0] / $GPS['lon']['min'][1];
            $GPS['lon']['sec'] = explode('/', $exif['GPSLongitude'][2]);
            $GPS['lon']['sec'] = $GPS['lon']['sec'][1] !== 0 ? floatval($GPS['lon']['sec'][0]) / floatval($GPS['lon']['sec'][1]) : 0;

            $exif['GPSLongitudeDecimal'] = $this->DMStoDEC($GPS['lon']['deg'], $GPS['lon']['min'], $GPS['lon']['sec']);
            if ($exif['GPSLongitudeRef'] == 'W') {
                $exif['GPSLongitudeDecimal'] = 0 - $exif['GPSLongitudeDecimal'];
            }
        } else {
            $exif['GPSLongitudeDecimal'] = null;
            $exif['GPSLongitudeRef'] = null;
        }

        if ($exif['GPSLatitudeDecimal'] && $exif['GPSLongitudeDecimal']) {
            $exif['GPSCalculatedDecimal'] = $exif['GPSLatitudeDecimal'].','.$exif['GPSLongitudeDecimal'];
        } else {
            $exif['GPSCalculatedDecimal'] = null;
        }

        $size = @getimagesize($source_path, $info);
        if ($size && isset($info['APP13'])) {
            $iptc = iptcparse($info['APP13']);

            if (is_array($iptc)) {
                $exif['iptc']['caption'] = isset($iptc['2#120']) ? $iptc['2#120'][0] : '';
                $exif['iptc']['graphic_name'] = isset($iptc['2#005']) ? $iptc['2#005'][0] : '';
                $exif['iptc']['urgency'] = isset($iptc['2#010']) ? $iptc['2#010'][0] : '';
                $exif['iptc']['category'] = @$iptc['2#015'][0];

                 // supp_categories sometimes contains multiple entries!
                $exif['iptc']['supp_categories'] = @$iptc['2#020'][0];
                $exif['iptc']['spec_instr'] = @$iptc['2#040'][0];
                $exif['iptc']['creation_date'] = @$iptc['2#055'][0];
                $exif['iptc']['photog'] = @$iptc['2#080'][0];
                $exif['iptc']['credit_byline_title'] = @$iptc['2#085'][0];
                $exif['iptc']['city'] = @$iptc['2#090'][0];
                $exif['iptc']['state'] = @$iptc['2#095'][0];
                $exif['iptc']['country'] = @$iptc['2#101'][0];
                $exif['iptc']['otr'] = @$iptc['2#103'][0];
                $exif['iptc']['headline'] = @$iptc['2#105'][0];
                $exif['iptc']['source'] = @$iptc['2#110'][0];
                $exif['iptc']['photo_source'] = @$iptc['2#115'][0];
                $exif['iptc']['caption'] = @$iptc['2#120'][0];

                $exif['iptc']['keywords'] = @$iptc['2#025'];
            }
        }

        return $exif;
    }
}

new Plugin();
