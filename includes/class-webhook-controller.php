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
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
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

	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}

		// Strict about what we use, lenient about extras, so new Tally fields don't break submissions.
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'tally_webhook',
			'type'       => 'object',
			'properties' => array(
				'eventId'   => array(
					'type'     => 'string',
					'required' => true,
				),
				'eventType' => array(
					'type'     => 'string',
					'enum'     => array( 'FORM_RESPONSE' ),
					'required' => true,
				),
				'createdAt' => array(
					'type'     => 'string',
					'required' => true,
				),
				'data'      => array(
					'type'       => 'object',
					'required'   => true,
					'properties' => array(
						'formName' => array(
							'type' => 'string',
						),
						'fields'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								// 'value' is left out on purpose. Its type depends on the field (text, checkboxes, etc.).
								'properties' => array(
									'key'   => array( 'type' => 'string' ),
									'label' => array( 'type' => 'string' ),
									'type'  => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
			),
		);

		return $this->schema;
	}

	public function create_item( $request ) {
		// TODO.
	}
}
