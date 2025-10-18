<?php
/**
 * Admin notices for token regeneration
 * 
 * Zobrazuje notices v adminu po automatické nebo manuální regeneraci tokenů.
 * 
 * @package SAW\WAP\Admin
 */

declare( strict_types=1 );

namespace SAW\WAP\Admin;

/**
 * Class RegenerateNotices
 */
class RegenerateNotices {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'admin_notices', [ self::class, 'show_regenerate_notice' ] );
	}

	/**
	 * Show admin notice after regeneration.
	 */
	public static function show_regenerate_notice(): void {
		// Pouze na edit product page
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		// Získat transient s výsledky
		$notice = get_transient( 'saw_regenerate_notice_' . get_current_user_id() );

		if ( ! $notice ) {
			return;
		}

		// Smazat transient (zobrazí se jen jednou)
		delete_transient( 'saw_regenerate_notice_' . get_current_user_id() );

		// Připravit zprávu
		$type           = isset( $notice['type'] ) ? $notice['type'] : 'info';
		$tokens_created = isset( $notice['tokens_created'] ) ? (int) $notice['tokens_created'] : 0;
		$customers      = isset( $notice['customers'] ) ? (int) $notice['customers'] : 0;
		$errors         = isset( $notice['errors'] ) ? $notice['errors'] : [];

		// CSS třída podle typu
		$css_class = 'success' === $type ? 'notice-success' : 'notice-info';

		?>
		<div class="notice <?php echo esc_attr( $css_class ); ?> is-dismissible">
			<p>
				<strong><?php esc_html_e( 'SAW-WAP: Regenerace tokenů', 'saw-wap' ); ?></strong>
			</p>

			<?php if ( $tokens_created > 0 ) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: number of tokens, 2: number of customers */
						esc_html__( '✅ Vytvořeno %1$d nových tokenů pro %2$d zákazníků.', 'saw-wap' ),
						$tokens_created,
						$customers
					);
					?>
				</p>
			<?php else : ?>
				<p>
					<?php esc_html_e( 'ℹ️ Žádné nové tokeny nebyly vytvořeny. Všichni zákazníci už mají přístup ke všem videím.', 'saw-wap' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $errors ) ) : ?>
				<p>
					<strong><?php esc_html_e( '⚠️ Některé tokeny se nepodařilo vytvořit:', 'saw-wap' ); ?></strong>
				</p>
				<ul style="list-style: disc; margin-left: 20px;">
					<?php foreach ( $errors as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}