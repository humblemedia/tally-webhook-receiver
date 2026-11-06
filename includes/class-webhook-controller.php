<?php

namespace TallyWebhookReceiver;

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoint for Tally webhooks at /wp-json/tally-webhook-receiver/v1/webhooks.
 *
 * Request order: WordPress validates against the schema, then the permission check verifies
 * the signature, then create_item() saves. Because validation runs first, a malformed payload
 * gets a 400 before the signature is checked.
 */
class Webhook_Controller extends \WP_REST_Controller {

	/**
	 * @param Signature_Verifier $verifier Checks the Tally-Signature header.
	 * @param Submission_Store   $store    Saves submissions and finds duplicates.
	 */
	public function __construct( private Signature_Verifier $verifier, private Submission_Store $store ) {
		$this->namespace = 'tally-webhook-receiver/v1';
		$this->rest_base = 'webhooks';
	}

	/**
	 * Registers the POST route.
	 *
	 * WordPress only parses the body as JSON when Content-Type is application/json.
	 */
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

	/**
	 * Allows the request only if the Tally signature is valid.
	 *
	 * @param \WP_REST_Request $request Full request data.
	 * @return true|\WP_Error
	 */
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

	/**
	 * Describes a valid Tally payload. Cached on $this->schema, as core controllers do.
	 *
	 * @return array
	 */
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

	/**
	 * Saves a new submission, or reports a duplicate.
	 *
	 * @param \WP_REST_Request $request Full request data.
	 * @return \WP_REST_Response|\WP_Error 201 with the new ID, 200 for a duplicate, or an error.
	 */
	public function create_item( $request ) {
		// Safe because the signature and schema have both already passed.
		$payload = $request->get_json_params();

		// Return 2xx, not 409. Providers retry on any non-2xx, so an error code causes more retries.
		if ( $this->store->exists( $payload['eventId'] ) ) {
			return new \WP_REST_Response( array( 'duplicate' => true ), 200 );
		}

		$post_id = $this->store->save( $payload );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		/**
		 * Fires after a new Tally submission is saved.
		 *
		 * Heavy work belongs in Action Scheduler or WP-Cron so the endpoint answers fast.
		 *
		 * @param int   $post_id The new tally_submission post ID.
		 * @param array $payload The validated webhook payload.
		 */
		do_action( 'twr_submission_received', $post_id, $payload );

		return new \WP_REST_Response( array( 'id' => $post_id ), 201 );
	}
}
