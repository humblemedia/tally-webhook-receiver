<?php

namespace TallyWebhookReceiver;

defined( 'ABSPATH' ) || exit;

class Submission_Store {

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

	// Not race-safe for simultaneous deliveries, but acceptable for Tally's retry rate.
	// If needed, a custom table with unique index on event_id would make the database reject the second insert.
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
