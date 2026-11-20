<?php
/**
 * Plugin Name:       Laborde Mobilia
 * Description:       Connects the Laborde Marset site to the Mobilia Gestión API. Currently provides a read-only connection test in Tools → Mobilia.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Wise Creative Agency
 * Text Domain:       laborde-mobilia
 * License:           GPL-2.0-or-later
 *
 * Credentials are read from constants defined in wp-config.php:
 *   MOBILIA_CLIENT_ID, MOBILIA_CLIENT_SECRET
 *   MOBILIA_BASE_URL (optional, defaults to https://api.mobiliagestion.es/api/v1)
 *
 * @package Laborde\Mobilia
 */

defined( 'ABSPATH' ) || exit;

define( 'LABORDE_MOBILIA_VERSION', '0.1.0' );
define( 'LABORDE_MOBILIA_DIR', plugin_dir_path( __FILE__ ) );

require_once LABORDE_MOBILIA_DIR . 'includes/class-client.php';
require_once LABORDE_MOBILIA_DIR . 'includes/class-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		if ( is_admin() ) {
			( new Laborde\Mobilia\Admin() )->register();
		}
	}
);
