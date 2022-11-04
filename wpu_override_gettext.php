<?php
/*
Plugin Name: WPU Override gettext
Plugin URI: https://github.com/WordPressUtilities/wpu_override_gettext
Update URI: https://github.com/WordPressUtilities/wpu_override_gettext
Description: Override gettext strings
Version: 0.1.0
Author: darklg
Author URI: https://darklg.me/
Text Domain: wpu_override_gettext
Domain Path: /lang/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUOverrideGettext {
    private $plugin_version = '0.1.0';
    private $plugin_settings = array(
        'id' => 'wpu_override_gettext',
        'name' => 'WPU Override gettext'
    );
    private $messages = false;
    private $theme_id = false;

    public function __construct() {
        add_filter('plugins_loaded', array(&$this, 'plugins_loaded'));
        add_filter('gettext', array(&$this, 'translate_text'), 10, 3);
    }

    public function plugins_loaded() {
        $this->theme_id = get_stylesheet();
        # TRANSLATION
        load_plugin_textdomain('wpu_override_gettext', false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $this->plugin_description = __('Override gettext strings', 'wpu_override_gettext');
        # CUSTOM PAGE
        $admin_pages = array(
            'main' => array(
                'icon_url' => 'dashicons-admin-generic',
                'menu_name' => $this->plugin_settings['name'],
                'name' => 'Main page',
                'settings_link' => true,
                'settings_name' => __('Settings'),
                'function_content' => array(&$this,
                    'page_content__main'
                ),
                'function_action' => array(&$this,
                    'page_action__main'
                )
            )
        );
        $pages_options = array(
            'id' => $this->plugin_settings['id'],
            'level' => 'manage_options',
            'basename' => plugin_basename(__FILE__)
        );
        // Init admin page
        include dirname(__FILE__) . '/inc/WPUBaseAdminPage/WPUBaseAdminPage.php';
        $this->adminpages = new \wpu_override_gettext\WPUBaseAdminPage();
        $this->adminpages->init($pages_options, $admin_pages);
        # MESSAGES
        if (is_admin()) {
            include dirname(__FILE__) . '/inc/WPUBaseMessages/WPUBaseMessages.php';
            $this->messages = new \wpu_override_gettext\WPUBaseMessages($this->plugin_settings['id']);
        }
    }

    /* Add a message */
    public function set_message($id, $message, $group = '') {
        if (!$this->messages) {
            error_log($id . ' - ' . $message);
            return;
        }
        $this->messages->set_message($id, $message, $group);
    }

    public function page_content__main() {

        $result = $this->get_all_files(get_stylesheet_directory());
        $result += $this->get_all_files(ABSPATH . '/wp-content/mu-plugins/metaco');

        $master_strings = array();

        foreach ($result as $file) {
            $_file_content = file_get_contents($file);

            preg_match_all("/[\s=\(\.]+_[_e][\s]*\([\s]*[\'\"](.*?)[\'\"][\s]*,[\s]*[\'\"](.*?)[\'\"][\s]*\)/s", $_file_content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $i => $match) {
                    $master_strings[] = array(
                        'string' => $match,
                        'file' => str_replace(ABSPATH, '', $file),
                        'domain' => $matches[2][$i]
                    );
                }
            }
        }

        $translations = get_option('wpu_override_gettext__translations');
        if (!is_array($translations)) {
            $translations = array();
        }

        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr><th>String</th><th>Domain</th><th>Override</th></tr></thead>';
        foreach ($master_strings as $str) {
            $value = '';
            if (isset($translations[$str['string']])) {
                $value = $translations[$str['string']];
            }
            echo '<tr>';
            echo '<td><strong>' . $str['string'] . '</strong><br /><small>' . $str['file'] . '</small></td>';
            echo '<td>' . $str['domain'] . '</td>';
            echo '<td>';
            echo '<input type="hidden" name="original_string[]" value="' . esc_attr($str['string']) . '" />';
            echo '<textarea name="translated_string[]" style="width:100%" placeholder="' . esc_attr($str['string']) . '">' . esc_html($value) . '</textarea>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        submit_button();
    }

    public function page_action__main() {
        if (isset($_POST['original_string']) && is_array($_POST['original_string'])) {
            $translations = array();
            foreach ($_POST['original_string'] as $i => $value) {
                $translations[$value] = $_POST['translated_string'][$i];
            }
            update_option('wpu_override_gettext__translations', $translations);
        }
    }

    /* HELPERS */

    function get_all_files($dir) {
        $results = array();
        $files = scandir($dir);

        /* Parse all files */
        foreach ($files as $file) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $file);
            if (!is_dir($path)) {
                $extension = substr(strrchr($file, "."), 1);
                if ($extension == 'php') {
                    $results[] = $path;
                }
            } else if ($file != "." && $file != "..") {
                $results = array_merge($results, $this->get_all_files($path));
            }
        }

        return $results;
    }

    function translate_text($translated_text, $untranslated_text, $domain) {
        if ($this->theme_id !== $domain) {
            return $translated_text;
        }

        $translations = get_option('wpu_override_gettext__translations');
        if (is_array($translations) && isset($translations[$untranslated_text])) {
            return $translations[$untranslated_text];
        }

        return $translated_text;
    }
}

$WPUOverrideGettext = new WPUOverrideGettext();
