<?php
/*
Plugin Name:        BtW Importer
Plugin URI:         https://github.com/mnasikin/btw-importer
Description:        Simple yet powerful plugin to Migrate Blogger to WordPress in one click. Import .atom from Google Takeout and the plugin will scan & download first image, replace URLs, set featured image, show live progress.
Version:            2.1.3
Author:             Nasikin
Author URI:         https://github.com/mnasikin/
License:            MIT
Domain Path:        /languages
Text Domain:        btw-importer
Network:            false
Requires PHP:       7.4
GitHub Plugin URI:  https://github.com/mnasikin/btw-importer
Primary Branch:     main
*/

// updater
if ( ! defined( 'ABSPATH' ) ) exit;

require 'updater/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$btw_updater_folder = basename(dirname(__FILE__));

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/mnasikin/btw-importer/',
    __FILE__,
    $btw_updater_folder
);
$myUpdateChecker->getVcsApi()->enableReleaseAssets();
$myUpdateChecker->setAuthentication('');
// end updater

require_once plugin_dir_path(__FILE__) . 'importer.php';
require_once plugin_dir_path(__FILE__) . 'redirect.php';
require_once plugin_dir_path(__FILE__) . 'redirect-log.php';
