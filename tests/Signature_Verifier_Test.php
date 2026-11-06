<?php
/**
 * Unit tests for Signature_Verifier.
 *
 * The verifier has no WordPress dependencies, so these run with plain PHPUnit.
 * Expected signatures were computed once from the fixture and hard-coded,
 * so the test doesn't just repeat the code it's checking.
 *
 * @package TallyWebhookReceiver
 */

use PHPUnit\Framework\TestCase;
use TallyWebhookReceiver\Signature_Verifier;

require_once dirname( __DIR__ ) . '/includes/class-signature-verifier.php';

final class Signature_Verifier_Test extends TestCase {

	// A made-up secret. The real one lives only in wp-config.php.
	private const SECRET = 'test-secret';

	// base64( HMAC-SHA256( fixture, 'test-secret' ) ).
	private const VALID_SIGNATURE = 'zCqhJ2yLZ+HEAJr0IG6vP0JlQAPBCztGqKn+UalaVmo=';

	// base64( HMAC-SHA256( fixture, '' ) ). A correct signature for an empty key.
	private const EMPTY_KEY_SIGNATURE = '+RXcrg2jsc/NN2rxrsLT3MvyookfWA24EdjeX/x9Fv4=';

	private string $body;

	protected function setUp(): void {
		$this->body = file_get_contents( __DIR__ . '/fixtures/tally-payload.json' );
	}

	public function test_valid_signature_passes(): void {
		$verifier = new Signature_Verifier( self::SECRET );

		$this->assertTrue( $verifier->verify( $this->body, self::VALID_SIGNATURE ) );
	}

	public function test_wrong_signature_fails(): void {
		$verifier = new Signature_Verifier( self::SECRET );

		$this->assertFalse( $verifier->verify( $this->body, 'nope' ) );
	}

	public function test_tampered_body_fails(): void {
		$verifier = new Signature_Verifier( self::SECRET );
		$tampered = str_replace( 'Test message', 'Tampered message', $this->body );

		$this->assertFalse( $verifier->verify( $tampered, self::VALID_SIGNATURE ) );
	}

	public function test_reencoded_json_fails(): void {
		// The raw-body gotcha. Same data, different bytes, so the signature no longer matches.
		$verifier  = new Signature_Verifier( self::SECRET );
		$reencoded = json_encode( json_decode( $this->body, true ), JSON_PRETTY_PRINT );

		$this->assertFalse( $verifier->verify( $reencoded, self::VALID_SIGNATURE ) );
	}

	public function test_missing_signature_fails_without_error(): void {
		// A missing header arrives as null. It must return false, not throw a TypeError.
		$verifier = new Signature_Verifier( self::SECRET );

		$this->assertFalse( $verifier->verify( $this->body, null ) );
		$this->assertFalse( $verifier->verify( $this->body, '' ) );
	}

	public function test_empty_secret_fails_closed(): void {
		// Even a mathematically correct signature is rejected when no secret is configured.
		$verifier = new Signature_Verifier( '' );

		$this->assertFalse( $verifier->verify( $this->body, self::EMPTY_KEY_SIGNATURE ) );
	}
}
