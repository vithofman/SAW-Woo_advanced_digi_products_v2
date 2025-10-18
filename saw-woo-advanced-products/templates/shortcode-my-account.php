<?php
/**
 * Template for [saw_my_account] shortcode
 * 
 * KRITICKÉ: Tento template NESMÍ obsahovat <html>, <head>, <body> tagy!
 * Musí vrátit pouze content pro kompatibilitu s Oxygen Builder.
 * 
 * Available variables:
 * @var WP_User $user             Current WordPress user object
 * @var int     $user_id          Current user ID
 * @var string  $current_endpoint Current active tab/endpoint
 * @var array   $menu_items       Navigation menu items
 * 
 * @package SAW\WAP\Templates
 * @since 1.0.0
 */

declare(strict_types=1);

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SAW\WAP\Helpers\AccountHelpers;

// Zkontroluj že máme potřebné proměnné
if ( ! isset( $user ) || ! isset( $user_id ) || ! isset( $current_endpoint ) || ! isset( $menu_items ) ) {
	echo '<div class="saw-error">Chyba: Chybí potřebná data pro šablonu.</div>';
	return;
}

// Get user first name for greeting
$user_first_name = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
?>

<div class="saw-my-account-wrapper">
	
	<div class="saw-my-account__header">
		<div class="saw-my-account__greeting">
			<h1 class="saw-my-account__title">
				<?php echo esc_html( AccountHelpers::get_greeting( $user_first_name ) ); ?>
			</h1>
			<p class="saw-my-account__subtitle">
				<?php esc_html_e( 'Vítejte ve vašem účtu', 'saw-wap' ); ?>
			</p>
		</div>
		
		<button class="saw-my-account__mobile-toggle" id="sawAccountMobileToggle" aria-label="Toggle menu" type="button">
			<span class="saw-mobile-toggle__icon">☰</span>
			<span class="saw-mobile-toggle__text">Menu</span>
		</button>
	</div>

	<div class="saw-my-account__grid">
		
		<aside class="saw-my-account__sidebar" id="sawAccountSidebar">
			<?php
			$nav_template = SAW_WAP_PATH . 'templates/my-account/navigation.php';
			if ( file_exists( $nav_template ) ) {
				include $nav_template;
			} else {
				echo '<div class="saw-error">Navigation template not found</div>';
			}
			?>
		</aside>

		<main class="saw-my-account__content">
			<div class="saw-my-account__content-inner" id="sawContentArea">
				<?php
				$endpoint_templates = array(
					'dashboard'       => 'dashboard.php',
					'courses'         => 'courses.php',
					'orders'          => 'orders.php',
					'order-view'      => 'order-detail.php',
					'addresses'       => 'addresses.php',
					'account-details' => 'account-details.php',
					'payment-methods' => 'payment-methods.php',
					'downloads'       => 'downloads.php',
					'certificates'    => 'certificates.php',
				);

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
				
				if ( $order_id > 0 && $current_endpoint === 'orders' ) {
					$current_endpoint = 'order-view';
				}

				$template_file = isset( $endpoint_templates[ $current_endpoint ] ) 
					? $endpoint_templates[ $current_endpoint ] 
					: 'dashboard.php';

				$template_path = SAW_WAP_PATH . 'templates/my-account/' . $template_file;

				if ( file_exists( $template_path ) ) {
					do_action( 'saw_before_account_content', $current_endpoint, $user_id );
					include $template_path;
					do_action( 'saw_after_account_content', $current_endpoint, $user_id );
				} else {
					?>
					<div class="saw-error-message">
						<h2>⚠️ <?php esc_html_e( 'Sekce nenalezena', 'saw-wap' ); ?></h2>
						<p><?php esc_html_e( 'Požadovaná sekce není dostupná.', 'saw-wap' ); ?></p>
						<p class="saw-error-details">
							<small>
								<?php 
								printf( 
									esc_html__( 'Template: %s', 'saw-wap' ), 
									esc_html( $template_file ) 
								); 
								?>
							</small>
						</p>
						<a href="<?php echo esc_url( add_query_arg( 'endpoint', 'dashboard', get_permalink() ) ); ?>" class="saw-btn saw-btn--primary">
							<?php esc_html_e( '← Zpět na přehled', 'saw-wap' ); ?>
						</a>
					</div>
					<?php
				}
				?>
			</div>
		</main>

	</div>

	<footer class="saw-my-account__footer">
		<p class="saw-my-account__footer-text">
			<?php
			printf( 
				esc_html__( 'Přihlášen jako: %s', 'saw-wap' ), 
				'<strong>' . esc_html( $user->user_email ) . '</strong>' 
			);
			?>
			 | 
			<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="saw-logout-link">
				<?php esc_html_e( 'Odhlásit se', 'saw-wap' ); ?>
			</a>
		</p>
	</footer>

</div>