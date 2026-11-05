<?php

namespace TallyWebhookReceiver;

defined( 'ABSPATH' ) || exit;

class Webhook_Controller extends \WP_REST_Controller {

	private $verifier;
	private $store;

	public function __construct( Signature_Verifier $verifier, Submission_Store $store ) {
		$this->namespace = 'tally-webhook-receiver/v1';
		$this->rest_base = 'webhooks';
		$this->verifier  = $verifier;
		$this->store     = $store;
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'callback'            => array( $this, 'create_item' ),
				),
			)
		);
	}

	public function create_item_permissions_check( $request ) {
		// Get raw body + header, ask $this->verifier.
		// Return true, or a WP_Error with status 401.
	}

	public function create_item( $request ) {
		// TODO.
	}
}
