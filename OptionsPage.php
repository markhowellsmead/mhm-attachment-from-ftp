<?php

namespace MHM\WordPress\AttachmentFromFtp;

class OptionsPage
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'createAdminMenu'));
    }

    public function createAdminMenu()
    {
        add_submenu_page('options-general.php', __('Attachments from FTP', 'mhm_attachment_from_ftp'), __('Attachments from FTP', 'mhm_attachment_from_ftp'), 'manage_options', 'mhm_attachment_from_ftp', array($this, 'settingsPage'));
        add_action('admin_init', array($this, 'registerPluginSettings'));
    }

    public function registerPluginSettings()
    {
        register_setting('mhm_attachment_from_ftp', 'mhm_attachment_from_ftp');
    }

    public function settingsPage()
    {
        echo '<div class="wrap">
            <h1>'._x('Attachments from FTP', 'Admin page title', 'mhm_attachment_from_ftp').'</h1>
            <form method="post" action="options.php">
            ';

        settings_fields('mhm_attachment_from_ftp');
        $options = get_option('mhm_attachment_from_ftp');

        echo '<table class="form-table">
            <tr>
                <th scope="row">'.__('Source folder', 'mhm_attachment_from_ftp').'</th>
                <td>
                    '.$this->foldersAsSelect($this->getBaseFilePath(), 'mhm_attachment_from_ftp[source_folder]', esc_attr($options['source_folder'])).'
                    <p class="description">'.__('This is a list of all the direct subfolders in your WordPress uploads folder.', 'mhm_attachment_from_ftp').'</p>
                    <p class="description">'.__('Whichever folder is selected here will be watched by this plugin for new additions.', 'mhm_attachment_from_ftp').'</p>
                    <p class="description">'.__('If the folder you want to use is not in the list, make sure that you have created it on the server and that the correct access rights (0755) are set, so that WordPress can read it.', 'mhm_attachment_from_ftp').'</p>
                </td>
            </tr>
            <tr>
                <th scope="row">'.__('Post author', 'mhm_attachment_from_ftp').'</th>
                <td>
                    '.wp_dropdown_users(array(
                        'echo' => false,
                        'selected' => (int) $options['author_id'],
                        'name' => 'mhm_attachment_from_ftp[author_id]',
                        'show_option_none' => __('None', 'mhm_attachment_from_ftp'),
                    )).'
                    <p class="description">'.__('To which author should new attachments be attributed?', 'mhm_attachment_from_ftp').'</p>
                </td>
            </tr>
            <tr>
                <th scope="row">'.__('Number of files to process', 'mhm_attachment_from_ftp').'</th>
                <td>
                    <select name="mhm_attachment_from_ftp[files_per_batch]" id="files_per_batch">
                        <option value="10"'.selected((int) $options['files_per_batch']).'>10</option>
                        <option value="25"'.selected((int) $options['files_per_batch']).'>25</option>
                        <option value="50"'.selected((int) $options['files_per_batch']).'>50</option>
                        <option value="100"'.selected((int) $options['files_per_batch']).'>100</option>
                    </select>
                    <p class="description">'.__('The maximum number of files to process in a single batch. This number should be kept reasonably low unless you have a high-performance server.', 'mhm_attachment_from_ftp').'</p>
                    <p class="description">'.__('The plugin re-generates thumbnails in several sizes each time it adds or updates an Attachment, which can require a little performance from the server.', 'mhm_attachment_from_ftp').'</p>
                    <p class="description">'.__('If WordPress tries to process too many files in one go, then your server may experience memory issues.', 'mhm_attachment_from_ftp').'</p>
                </td>
            </tr>
            <tr>
                <th scope="row">'.__('Do not overwrite existing titles or descriptions', 'mhm_attachment_from_ftp').'</th>
                <td>
                    <p class="description">
                        <input type="checkbox" name="mhm_attachment_from_ftp[no_overwrite_title_description]" value="1" '.checked((bool) $options['no_overwrite_title_description'], true, false).'>
                        '.__('If this checkbox is selected, then titles and descriptions in existing Attachments will not be overwritten when importing newer versions of existing files.', 'mhm_attachment_from_ftp').'
                    </p>
                </td>
            </tr>
        </table>';

        submit_button();

        echo '</form>
        </div>';
    }

    public function getBaseFilePath()
    {
        $upload_dir = wp_upload_dir();

        return $upload_dir['basedir'];
    }

    public function foldersAsSelect($path, $fieldname = '', $currentValue = null, $includeParentFolder = false)
    {
        if (!is_dir($path)) {
            return '';
        }

        $rootname = basename($path);

        if ($includeParentFolder) {
            $prefixvalue_subfolder = $rootname.DIRECTORY_SEPARATOR;
            $prefixlabel_subfolder = $rootname.DIRECTORY_SEPARATOR;
        } else {
            $prefixvalue_subfolder = '';
            $prefixlabel_subfolder = '';
        }

        $folders = array();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS), // Get the directories, excluding . and .. entries.
            \RecursiveIteratorIterator::SELF_FIRST, // Start with the parent folder and then look at its children.
            \RecursiveIteratorIterator::CATCH_GET_CHILD // Don't break if access rights to a folder in the structure are forbidden.
        );

        // Do not include subfolders below the first level.
        $iterator->setMaxDepth(0);

        foreach ($iterator as $itref) {
            if ($itref->isDir()) {
                $folders[$prefixvalue_subfolder.$itref->getFilename()] = $prefixlabel_subfolder.$itref->getFilename();
            }
        }

        uksort($folders, 'strnatcasecmp');

        if ($includeParentFolder) {
            // The first entry in the list is the parent folder, which has been used as the starting point for this function.
            $folders = array_merge(
                array($rootname => $rootname),
                $folders
            );
        }

        $options = array();

        foreach ($folders as $key => $label) {
            $options[] = '<option value="'.$key.'"'.selected($currentValue, $key, false).'>'.$label.'</option>';
        }

        return '<select name="'.$fieldname.'"><option value="">'.__('Select', 'frp_dataroom').'</option>'.implode('', $options).'</select>';
    }
}

new OptionsPage();
