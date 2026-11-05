<?php

namespace TallyWebhookReceiver;

defined( 'ABSPATH' ) || exit;

class Webhook_Controller extends \WP_REST_Controller {

	public function __construct( private Signature_Verifier $verifier, private Submission_Store $store ) {
		$this->namespace = 'tally-webhook-receiver/v1';
		$this->rest_base = 'webhooks';
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
		// The signature covers the exact bytes Tally sent. Decoding and re-encoding the JSON changes them.
		$raw_body  = $request->get_body();
		$signature = $request->get_header( 'tally-signature' );
		if ( $this->verifier->verify( $raw_body, $signature ) ) {
			return true;
		}

		return new \WP_Error(
			'twr_invalid_signature',
			__( 'Invalid webhook signature.', 'tally-webhook-receiver' ),
			array( 'status' => 401 )
		);
	}

	public function create_item( $request ) {
		// TODO.
	}
}
