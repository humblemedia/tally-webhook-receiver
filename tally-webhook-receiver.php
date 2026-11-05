<?php
/**
 * Plugin Name: Tally Webhook Receiver
 * Plugin URI: https://eldritch.ca
 * Description: Handle payloads from tally.so for WordPress REST API
 * Version: 0.1
 * Author: Andrew Fraser
 * Text Domain: tally-webhook-receiver
 * Requires at least: 7.1
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */

namespace TallyWebhookReceiver;

defined( 'ABSPATH' ) || exit;

define( 'TWR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once TWR_PLUGIN_DIR . 'includes/class-signature-verifier.php';
require_once TWR_PLUGIN_DIR . 'includes/class-submission-store.php';
require_once TWR_PLUGIN_DIR . 'includes/class-webhook-controller.php';

function bootstrap() {
	$store      = new Submission_Store();
	$controller = new TallyWebhookController( new Signature_Verifier(), $store );

	add_action( 'init', array( $store, 'register_post_type' ) );
	add_action( 'rest_api_init', array( $controller, 'register_routes' ) );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\bootstrap' );
