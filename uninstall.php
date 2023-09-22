<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/* Delete options */
$options = array(
    'wpu_override_gettext__translations'
);
foreach ($options as $opt) {
    delete_option($opt);
    delete_site_option($opt);
}
