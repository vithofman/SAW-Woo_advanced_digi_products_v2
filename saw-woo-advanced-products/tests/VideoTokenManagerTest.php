<?php
/**
 * Unit tests pro VideoTokenManager.
 *
 * Pro spuštění:
 * vendor/bin/phpunit tests/VideoTokenManagerTest.php
 *
 * @package SAW\WAP\Tests
 */

declare( strict_types=1 );

namespace SAW\WAP\Tests;

use SAW\WAP\Core\VideoTokenManager;
use WP_UnitTestCase;

/**
 * Class VideoTokenManagerTest
 */
class VideoTokenManagerTest extends WP_UnitTestCase {

	/**
	 * Instance VideoTokenManager.
	 *
	 * @var VideoTokenManager
	 */
	private VideoTokenManager $manager;

	/**
	 * Setup před každým testem.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->manager = new VideoTokenManager();
	}

	/**
	 * Test: generateToken vrací 64-znakový hex string.
	 */
	public function test_generate_token_returns_64_char_hex_string(): void {
		$token = $this->manager->generateToken( 1, 100, 0, 500 );

		$this->assertIsString( $token );
		$this->assertEquals( 64, strlen( $token ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $token );
	}

	/**
	 * Test: validateToken vrací objekt s přístupovými daty.
	 */
	public function test_validate_token_returns_access_object(): void {
		$token  = $this->manager->generateToken( 1, 100, 0, 500 );
		$access = $this->manager->validateToken( $token );

		$this->assertIsObject( $access );
		$this->assertEquals( 1, $access->user_id );
		$this->assertEquals( 100, $access->product_id );
		$this->assertEquals( 0, $access->video_index );
		$this->assertEquals( 500, $access->order_id );
		$this->assertEquals( 1, $access->is_active );
	}

	/**
	 * Test: validateToken vrací null pro neexistující token.
	 */
	public function test_validate_nonexistent_token_returns_null(): void {
		$fake_token = str_repeat( 'a', 64 );
		$access     = $this->manager->validateToken( $fake_token );

		$this->assertNull( $access );
	}

	/**
	 * Test: validateToken vrací null pro špatný formát tokenu.
	 */
	public function test_validate_invalid_format_token_returns_null(): void {
		$access = $this->manager->validateToken( 'invalid-token-123' );
		$this->assertNull( $access );
	}

	/**
	 * Test: revokeToken deaktivuje token.
	 */
	public function test_revoke_token_deactivates_access(): void {
		$token = $this->manager->generateToken( 1, 100, 0, 500 );

		// Token je validní
		$access = $this->manager->validateToken( $token );
		$this->assertIsObject( $access );

		// Revoke
		$revoked = $this->manager->revokeToken( $token );
		$this->assertTrue( $revoked );

		// Token už není validní
		$access_after = $this->manager->validateToken( $token );
		$this->assertNull( $access_after );
	}

	/**
	 * Test: extendAccess prodlouží expiraci.
	 */
	public function test_extend_access_updates_expiration(): void {
		$token = $this->manager->generateToken( 1, 100, 0, 500, 7 ); // 7 dní

		// Získat původní expiraci
		global $wpdb;
		$table_name       = $wpdb->prefix . 'saw_video_access_tokens';
		$original_expires = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT access_expires FROM {$table_name} WHERE access_token = %s",
				$token
			)
		);

		// Prodloužit o 30 dní
		$extended = $this->manager->extendAccess( $token, 30 );
		$this->assertTrue( $extended );

		// Zkontrolovat novou expiraci
		$new_expires = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT access_expires FROM {$table_name} WHERE access_token = %s",
				$token
			)
		);

		$this->assertNotEquals( $original_expires, $new_expires );

		// Rozdíl by měl být přibližně 30 dní
		$original_timestamp = strtotime( $original_expires );
		$new_timestamp      = strtotime( $new_expires );
		$diff_days          = ( $new_timestamp - $original_timestamp ) / DAY_IN_SECONDS;

		$this->assertEquals( 30, round( $diff_days ) );
	}

	/**
	 * Test: getUserProductTokens vrací všechny tokeny uživatele.
	 */
	public function test_get_user_product_tokens_returns_all_tokens(): void {
		// Vygenerovat 3 tokeny pro stejného uživatele a produkt
		$this->manager->generateToken( 1, 100, 0, 500 );
		$this->manager->generateToken( 1, 100, 1, 500 );
		$this->manager->generateToken( 1, 100, 2, 500 );

		// Získat všechny tokeny
		$tokens = $this->manager->getUserProductTokens( 1, 100 );

		$this->assertIsArray( $tokens );
		$this->assertCount( 3, $tokens );

		// Zkontrolovat že jsou seřazeny podle video_index
		$this->assertEquals( 0, $tokens[0]->video_index );
		$this->assertEquals( 1, $tokens[1]->video_index );
		$this->assertEquals( 2, $tokens[2]->video_index );
	}

	/**
	 * Test: access_count se zvyšuje při validaci.
	 */
	public function test_access_count_increments_on_validation(): void {
		$token = $this->manager->generateToken( 1, 100, 0, 500 );

		// První validace
		$access1 = $this->manager->validateToken( $token );
		$this->assertEquals( 1, $access1->access_count );

		// Druhá validace
		$access2 = $this->manager->validateToken( $token );
		$this->assertEquals( 2, $access2->access_count );

		// Třetí validace
		$access3 = $this->manager->validateToken( $token );
		$this->assertEquals( 3, $access3->access_count );
	}

	/**
	 * Test: generateToken s invalid parameters vyhodí exception.
	 */
	public function test_generate_token_with_invalid_params_throws_exception(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid parameters for token generation' );

		$this->manager->generateToken( 0, 100, 0, 500 ); // user_id = 0 je invalid
	}

	/**
	 * Teardown po každém testu.
	 */
	public function tearDown(): void {
		// Vyčistit testovací data
		global $wpdb;
		$table_name = $wpdb->prefix . 'saw_video_access_tokens';
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		parent::tearDown();
	}
}