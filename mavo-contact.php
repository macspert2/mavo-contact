<?php
/**
 * Plugin Name: Mavo Contact
 * Description: Local privacy-conscious contact form for Maman Voyage.
 * Version:     1.0.0
 * Author:      Maman Voyage
 * Text Domain: mavo-contact
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'MAVO_CONTACT_VERSION', '1.0.0' );
define( 'MAVO_CONTACT_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAVO_CONTACT_URL', plugin_dir_url( __FILE__ ) );

// Public contact address: shown to visitors and used as the form recipient.
// Kept separate from admin_email so WordPress's administrative notices still
// go to the site admin mailbox.
define( 'MAVO_CONTACT_EMAIL', 'christine@mamanvoyage.com' );

require_once MAVO_CONTACT_DIR . 'includes/i18n.php';
require_once MAVO_CONTACT_DIR . 'includes/handler.php';
require_once MAVO_CONTACT_DIR . 'includes/shortcode.php';

add_action( 'plugins_loaded', static function () {
	load_plugin_textdomain( 'mavo-contact', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );
