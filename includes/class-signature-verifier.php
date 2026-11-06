<?php

namespace TallyWebhookReceiver;

// NO ABSPATH guard. Class declaration only. Must load outside WordPress for unit tests.

/**
 * Checks Tally's HMAC-SHA256 webhook signature.
 *
 * Kept free of WordPress functions so it can be unit tested on its own.
 */
class Signature_Verifier {

	/**
	 * @param string $secret Shared signing secret. An empty secret rejects everything.
	 */
	public function __construct( private readonly string $secret ) {}

	/**
	 * Verifies a request body against its Tally-Signature header.
	 *
	 * @param string      $raw_body  The request body exactly as received.
	 * @param string|null $signature The Tally-Signature header, or null if missing.
	 * @return bool True only if the signature matches.
	 */
	public function verify( string $raw_body, ?string $signature ): bool {
		if ( '' === $this->secret || empty( $signature ) ) {
			return false;
		}

		$expected = base64_encode( hash_hmac( 'sha256', $raw_body, $this->secret, true ) );

		return hash_equals( $expected, $signature );
	}
}
