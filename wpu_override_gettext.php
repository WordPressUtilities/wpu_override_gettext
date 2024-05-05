<?php
defined('ABSPATH') || die;
/*
Plugin Name: WPU Override gettext
Plugin URI: https://github.com/WordPressUtilities/wpu_override_gettext
Update URI: https://github.com/WordPressUtilities/wpu_override_gettext
Description: Override gettext strings
Version: 0.7.1
Author: darklg
Author URI: https://darklg.me/
Text Domain: wpu_override_gettext
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUOverrideGettext {
    public $wpubasefilecache;
    public $settings_update;
    public $plugin_description;
    public $adminpages;
    private $plugin_version = '0.7.1';
    private $plugin_settings = array(
        'id' => 'wpu_override_gettext',
        'name' => 'WPU Override gettext'
    );
    private $messages = false;
    private $text_domains = false;

    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
        add_action('gettext', array(&$this, 'translate_text'), 10, 3);
        add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
    }

    public function plugins_loaded() {
        $this->text_domains = apply_filters('wpu_override_gettext__text_domains', array(get_stylesheet()));

        # TRANSLATION
        if (!load_plugin_textdomain('wpu_override_gettext', false, dirname(plugin_basename(__FILE__)) . '/lang/')) {
            load_muplugin_textdomain('wpu_override_gettext', dirname(plugin_basename(__FILE__)) . '/lang/');
        }
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
            'level' => 'delete_users',
            'basename' => plugin_basename(__FILE__)
        );
        require_once __DIR__ . '/inc/WPUBaseAdminPage/WPUBaseAdminPage.php';
        $this->adminpages = new \wpu_override_gettext\WPUBaseAdminPage();
        $this->adminpages->init($pages_options, $admin_pages);

        # File Cache
        require_once __DIR__ . '/inc/WPUBaseFileCache/WPUBaseFileCache.php';
        $this->wpubasefilecache = new \wpu_override_gettext\WPUBaseFileCache('wpu_override_gettext');

        # Base Update
        require_once __DIR__ . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpu_override_gettext\WPUBaseUpdate(
            'WordPressUtilities',
            'wpu_override_gettext',
            $this->plugin_version);

        # MESSAGES
        if (is_admin()) {
            require_once __DIR__ . '/inc/WPUBaseMessages/WPUBaseMessages.php';
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
        $directories = array(
            get_stylesheet_directory()
        );
        foreach ($this->text_domains as $text_domain) {
            $directories[] = ABSPATH . '/wp-content/mu-plugins/' . $text_domain;
        }
        $directories = apply_filters('wpu_override_gettext__directories', $directories);
        $files = array();
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $new_files = $this->get_all_files($dir);
            $files = array_merge($new_files, $files);
        }
        $files = apply_filters('wpu_override_gettext__files', $files);
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
        $translations = $this->get_fixed_translations();

        echo '<p>';
        echo '<label for="wpu_override_gettext__filter_results">' . __('Filter strings', 'wpu_override_gettext') . '</label> ';
        echo '<input name="wpu_override_gettext__filter_results" id="wpu_override_gettext__filter_results" type="text" />';
        echo '</p>';
        echo '<table id="wp-list-table--wpu_override_gettext" class="wp-list-table--wpu_override_gettext wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . __('String', 'wpu_override_gettext') . '</th>';
        echo '<th>' . __('Domain', 'wpu_override_gettext') . '</th>';
        echo '<th>' . __('Custom translation', 'wpu_override_gettext') . '</th>';
        echo '</tr></thead>';
        foreach ($master_strings as $str) {
            if (!isset($str['domain']) || !in_array($str['domain'], $this->text_domains)) {
                continue;
            }

            /* Load new translation if available */
            $new_translation = $this->get_string_translation($str['string'], '');
            $old_translation = __($str['string'], $str['domain']);
            $files_present = array_unique($str['files']);
            $filter_text = array_merge($files_present, array($str['string'], $new_translation, $old_translation));
            $filter_text = strtolower(strip_tags(implode(' ', array_unique(array_filter($filter_text)))));

            echo '<tr data-visible="1" data-filter-text="' . esc_attr($filter_text) . '">';
            echo '<td><strong>' . esc_html($str['string']) . '</strong><small style="display:block">' . implode('<br />', $files_present) . '</small></td>';
            echo '<td>' . $str['domain'] . '</td>';
            echo '<td>';
            echo '<input type="hidden" name="original_string[]" value="' . esc_attr($str['string']) . '" />';
            echo '<textarea name="translated_string[]" style="width:100%" placeholder="' . esc_attr($old_translation) . '">' . esc_html($new_translation) . '</textarea>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        submit_button(__('Save translations', 'wpu_override_gettext'));
    }

    public function page_action__main() {
        if (!isset($_POST['original_string']) || !is_array($_POST['original_string'])) {
            return;
        }
        $translations = array();
        foreach ($_POST['original_string'] as $i => $value) {
            if (!$_POST['translated_string'][$i]) {
                continue;
            }
            $translations[$value] = $_POST['translated_string'][$i];
        }
        $translations = apply_filters('wpu_override_gettext__translations__before_save', $translations);
        update_option('wpu_override_gettext__translations', $translations, false);
        $this->wpubasefilecache->set_cache('translations', $translations);
        $this->set_message('saved_translations', __('The translations were successfully saved.', 'wpu_override_gettext'), 'notice');

    }

    /* ----------------------------------------------------------
      Assets
    ---------------------------------------------------------- */

    function admin_enqueue_scripts() {

        /* Back script */
        wp_register_script('wpu_override_gettext_back_script', plugins_url('assets/back.js', __FILE__), array(), $this->plugin_version, true);
        wp_enqueue_script('wpu_override_gettext_back_script');

        /* Back Style */
        wp_register_style('wpu_override_gettext_back_style', plugins_url('assets/back.css', __FILE__), array(), $this->plugin_version);
        wp_enqueue_style('wpu_override_gettext_back_style');
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    function get_fixed_translations() {

        $translations = $this->wpubasefilecache->get_cache('translations', 24 * 60 * 60);
        if (!$translations) {
            $translations = get_option('wpu_override_gettext__translations');
            $this->wpubasefilecache->set_cache('translations', $translations);
        }
        $translations = apply_filters('wpu_override_gettext__translations__before_display', $translations);

        if (!is_array($translations)) {
            return array();
        }
        $translations_fixed = array();
        foreach ($translations as $key => $translation) {
            $translations_fixed[base64_encode(html_entity_decode($key))] = $translation;
        }

        return $translations_fixed;
    }

    function get_string_translation($string, $new_translation) {
        $translations = $this->get_fixed_translations();
        if (!is_array($translations) || empty($translations)) {
            return $new_translation;
        }

        $key_string = base64_encode(html_entity_decode($string));
        if (isset($translations[$key_string])) {
            return $translations[$key_string];
        }
        return $new_translation;
    }

    function get_all_files($dir) {
        $excluded_dirs = array('node_modules', 'vendor');
        $dir_name = basename($dir);
        if (in_array($dir_name, $excluded_dirs)) {
            return array();
        }

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
        if (!is_array($this->text_domains) || !in_array($domain, $this->text_domains) || (is_admin() && isset($_GET['page']) && $_GET['page'] == 'wpu_override_gettext-main')) {
            return $translated_text;
        }

        return $this->get_string_translation($untranslated_text, $translated_text);
    }
}

$WPUOverrideGettext = new WPUOverrideGettext();
