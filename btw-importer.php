<?php
/*
Plugin Name:        [Beta] BtW Importer - Free Blogger/Blogspot Migration
Plugin URI:         https://github.com/mnasikin/btw-importer
Description:        Simple yet powerful plugin to Migrate Blogger to WordPress in one click for free. Import .atom from Google Takeout and the plugin will migrate your content.
Version:            4.3.3
Author:             M. Nasikin
Author URI:         https://github.com/mnasikin/
License:            GPLv3
License URI:        https://www.gnu.org/licenses/gpl-3.0.html
Domain Path:        /languages
Text Domain:        btw-importer
Requires PHP:       8.1
GitHub Plugin URI:  https://github.com/mnasikin/btw-importer
Primary Branch:     main
*/
if (!defined('ABSPATH'))
    exit;

// updater
require 'updater/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$btw_importer_updater_folder = basename(dirname(__FILE__));

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/mnasikin/btw-importer/',
    __FILE__,
    $btw_importer_updater_folder
);
$myUpdateChecker->getVcsApi()->enableReleaseAssets();
$myUpdateChecker->setAuthentication('');
// end updater

function btw_importer_include_files()
{
    require_once plugin_dir_path(__FILE__) . 'block-converter.php';
    require_once plugin_dir_path(__FILE__) . 'importer.php';
    require_once plugin_dir_path(__FILE__) . 'redirect.php';
    require_once plugin_dir_path(__FILE__) . 'redirect-log.php';
}
add_action('plugins_loaded', 'btw_importer_include_files');
