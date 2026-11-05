<?php

namespace TallyWebhookReceiver;

// NO ABSPATH guard. Class declaration only. Must load outside WordPress for unit tests.

class Signature_Verifier {

	public function __construct( private readonly string $secret ) {}

	public function verify( string $raw_body, ?string $signature ): bool {
		if ( '' === $this->secret || empty( $signature ) ) {
			return false;
		}

		$expected = base64_encode( hash_hmac( 'sha256', $raw_body, $this->secret, true ) );

		return hash_equals( $expected, $signature );
	}
}
