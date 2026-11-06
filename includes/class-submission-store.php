<?php

namespace TallyWebhookReceiver;

defined( 'ABSPATH' ) || exit;

/**
 * Stores submissions as a private custom post type.
 *
 * All storage decisions live here, so moving to a custom table later changes only this class.
 */
class Submission_Store {

	/**
	 * Registers the tally_submission post type. Hooked to init, so it runs on every request.
	 */
	public function register_post_type() {
		// Doesn't need a rewrite flush because there are no public URLs.
		$args = array(
			'label'        => __( 'Tally Submissions', 'tally-webhook-receiver' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
			'supports'     => array( 'title' ),
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		);

		register_post_type( 'tally_submission', $args );
	}

	/**
	 * Checks whether an event has already been saved.
	 *
	 * Not race-safe for simultaneous deliveries, but acceptable for Tally's retry rate.
	 * If needed, a custom table with unique index on event_id would make the database reject the second insert.
	 *
	 * @param string $event_id Tally's eventId.
	 * @return bool
	 */
	public function exists( string $event_id ): bool {
		$args = array(
			'post_type'      => 'tally_submission',
			// By default WP only finds published posts.
			'post_status'    => 'any',
			'meta_key'       => '_twr_event_id',
			'meta_value'     => $event_id,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		);

		$store_query = new \WP_Query( $args );

		return ! empty( $store_query->posts );
	}

	/**
	 * Saves a submission, with its event ID and the full payload as post meta.
	 *
	 * @param array $payload The validated webhook payload.
	 * @return int|\WP_Error The new post ID, or WP_Error on failure.
	 */
	public function save( array $payload ): int|\WP_Error {
		$args = array(
			'post_type'   => 'tally_submission',
			'post_title'  => sprintf( '%s, %s', $payload['data']['formName'] ?? 'Tally', $payload['createdAt'] ),
			'post_status' => 'publish',
			'meta_input'  => array(
				'_twr_event_id' => $payload['eventId'],
				// WordPress unslashes meta on save, which would strip JSON's backslashes.
				'_twr_payload'  => wp_slash( wp_json_encode( $payload ) ),
			),
		);

		return wp_insert_post( $args, true );
	}
}
