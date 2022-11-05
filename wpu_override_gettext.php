<?php
/*
Plugin Name: WPU Override gettext
Plugin URI: https://github.com/WordPressUtilities/wpu_override_gettext
Update URI: https://github.com/WordPressUtilities/wpu_override_gettext
Description: Override gettext strings
Version: 0.2.0
Author: darklg
Author URI: https://darklg.me/
Text Domain: wpu_override_gettext
Domain Path: /lang/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUOverrideGettext {
    private $plugin_version = '0.2.0';
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

        # ADMIN PAGE
        $admin_pages = array(
            'main' => array(
                'icon_url' => 'dashicons-translation',
                'menu_name' => $this->plugin_settings['name'],
                'name' => __('Settings', 'wpu_override_gettext'),
                'settings_link' => true,
                'settings_name' => __('Settings', 'wpu_override_gettext'),
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

        /* Parse all files in some known directories */
        $files = $this->get_all_files(get_stylesheet_directory());
        $muplugin_dir = ABSPATH . '/wp-content/mu-plugins/' . $this->theme_id;
        if (is_dir($muplugin_dir)) {
            $files += $this->get_all_files($muplugin_dir);
        }
        natsort($files);

        /* In each file, find a translation string */
        $master_strings = array();
        foreach ($files as $file) {
            $_file_content = file_get_contents($file);
            preg_match_all("/[\s=\(\.]+_[_e][\s]*\([\s]*[\'\"](.*?)[\'\"][\s]*,[\s]*[\'\"](.*?)[\'\"][\s]*\)/s", $_file_content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $i => $match) {
                    if (!isset($master_strings[$match])) {
                        $master_strings[$match] = array(
                            'string' => $match,
                            'files' => array(),
                            'domain' => $matches[2][$i]
                        );
                    }
                    $master_strings[$match]['files'][] = str_replace(ABSPATH, '', $file);
                }
            }
        }

        /* Load existing translations */
        $translations = get_option('wpu_override_gettext__translations');
        if (!is_array($translations)) {
            $translations = array();
        }

        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . __('String', 'wpu_override_gettext') . '</th>';
        echo '<th>' . __('Domain', 'wpu_override_gettext') . '</th>';
        echo '<th>' . __('Custom translation', 'wpu_override_gettext') . '</th>';
        echo '</tr></thead>';
        foreach ($master_strings as $str) {
            /* Load new translation if available */
            $new_translation = '';
            if (isset($translations[$str['string']])) {
                $new_translation = $translations[$str['string']];
            }
            echo '<tr>';
            echo '<td><strong>' . $str['string'] . '</strong><small style="display:block">' . implode('<br />', array_unique($str['files'])) . '</small></td>';
            echo '<td>' . $str['domain'] . '</td>';
            echo '<td>';
            echo '<input type="hidden" name="original_string[]" value="' . esc_attr($str['string']) . '" />';
            echo '<textarea name="translated_string[]" style="width:100%" placeholder="' . esc_attr($str['string']) . '">' . esc_html($new_translation) . '</textarea>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        submit_button(__('Save translations', 'wpu_override_gettext'));
    }

    public function page_action__main() {
        if (isset($_POST['original_string']) && is_array($_POST['original_string'])) {
            $translations = array();
            foreach ($_POST['original_string'] as $i => $value) {
                if (!$_POST['translated_string'][$i]) {
                    continue;
                }
                $translations[$value] = $_POST['translated_string'][$i];
            }
            update_option('wpu_override_gettext__translations', $translations, true);
            $this->set_message('saved_translations', __('The translations were successfully saved.', 'wpu_override_gettext'));
            return;
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
