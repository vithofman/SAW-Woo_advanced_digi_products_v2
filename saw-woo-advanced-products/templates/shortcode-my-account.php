<?php
/**
 * Template for [saw_my_account] shortcode
 * 
 * Main My Account system template
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

// Security check - this template should only be loaded via shortcode
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SAW\WAP\Helpers\AccountHelpers;

// Get user first name for greeting
$user_first_name = $user->first_name ?: $user->display_name;
?>

<div class="saw-my-account-wrapper">
	
	<!-- Header Section -->
	<div class="saw-my-account__header">
		<div class="saw-my-account__greeting">
			<h1 class="saw-my-account__title">
				<?php echo esc_html( AccountHelpers::get_greeting( $user_first_name ) ); ?>
			</h1>
			<p class="saw-my-account__subtitle">
				<?php esc_html_e( 'Vítejte ve vašem účtu', 'saw-wap' ); ?>
			</p>
		</div>
		
		<!-- Mobile Menu Toggle (visible only on mobile) -->
		<button class="saw-my-account__mobile-toggle" id="sawAccountMobileToggle" aria-label="Toggle menu">
			<span class="saw-mobile-toggle__icon">☰</span>
			<span class="saw-mobile-toggle__text">Menu</span>
		</button>
	</div>

	<!-- Main Content Grid -->
	<div class="saw-my-account__grid">
		
		<!-- Sidebar Navigation -->
		<aside class="saw-my-account__sidebar" id="sawAccountSidebar">
			<?php
			// Include navigation partial
			$nav_template = SAW_WAP_PATH . 'templates/my-account/navigation.php';
			if ( file_exists( $nav_template ) ) {
				include $nav_template;
			} else {
				echo '<div class="saw-error">Navigation template not found</div>';
			}
			?>
		</aside>

		<!-- Main Content Area -->
		<main class="saw-my-account__content">
			
			<!-- Content Loading Indicator -->
			<div class="saw-loading-overlay" id="sawContentLoading" style="display: none;">
				<div class="saw-loading-spinner"></div>
				<p><?php esc_html_e( 'Načítání...', 'saw-wap' ); ?></p>
			</div>

			<!-- Dynamic Content Container -->
			<div class="saw-my-account__content-inner" id="sawContentArea">
				<?php
				/**
				 * Load appropriate content template based on endpoint
				 */
				
				// Map endpoints to template files
				$endpoint_templates = array(
					'dashboard'       => 'dashboard.php',
					'courses'         => 'courses.php',
					'orders'          => 'orders.php',
					'order-view'      => 'order-detail.php', // Special case for viewing single order
					'addresses'       => 'addresses.php',
					'account-details' => 'account-details.php',
					'payment-methods' => 'payment-methods.php',
					'downloads'       => 'downloads.php',
					'certificates'    => 'certificates.php',
				);

				// Check for special case: viewing single order
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
				
				if ( $order_id > 0 && $current_endpoint === 'orders' ) {
					$current_endpoint = 'order-view';
				}

				// Get template filename
				$template_file = isset( $endpoint_templates[ $current_endpoint ] ) 
					? $endpoint_templates[ $current_endpoint ] 
					: 'dashboard.php';

				// Full template path
				$template_path = SAW_WAP_PATH . 'templates/my-account/' . $template_file;

				// Load template if exists
				if ( file_exists( $template_path ) ) {
					/**
					 * Hook: saw_before_account_content
					 * 
					 * @param string $current_endpoint Current endpoint
					 * @param int    $user_id          User ID
					 */
					do_action( 'saw_before_account_content', $current_endpoint, $user_id );
					
					// Include the template
					include $template_path;
					
					/**
					 * Hook: saw_after_account_content
					 * 
					 * @param string $current_endpoint Current endpoint
					 * @param int    $user_id          User ID
					 */
					do_action( 'saw_after_account_content', $current_endpoint, $user_id );
					
				} else {
					// Template not found error
					?>
					<div class="saw-error-message">
						<h2>❌ <?php esc_html_e( 'Sekce nenalezena', 'saw-wap' ); ?></h2>
						<p><?php esc_html_e( 'Požadovaná sekce není dostupná.', 'saw-wap' ); ?></p>
						<p class="saw-error-details">
							<small>
								<?php 
								/* translators: %s: template filename */
								printf( esc_html__( 'Template: %s', 'saw-wap' ), esc_html( $template_file ) ); 
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

	</div><!-- .saw-my-account__grid -->

	<!-- Footer Section (optional) -->
	<footer class="saw-my-account__footer">
		<p class="saw-my-account__footer-text">
			<?php
			/* translators: %s: user email */
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

</div><!-- .saw-my-account-wrapper -->

<?php
/**
 * Inline JavaScript for mobile menu toggle
 * (Small script, better inline than separate file)
 */
?>
<script>
(function() {
	'use strict';
	
	// Wait for DOM ready
	document.addEventListener('DOMContentLoaded', function() {
		var toggle = document.getElementById('sawAccountMobileToggle');
		var sidebar = document.getElementById('sawAccountSidebar');
		
		if (!toggle || !sidebar) {
			return;
		}
		
		// Toggle mobile menu
		toggle.addEventListener('click', function(e) {
			e.preventDefault();
			sidebar.classList.toggle('saw-my-account__sidebar--open');
			toggle.classList.toggle('saw-mobile-toggle--active');
			
			// Update aria-expanded
			var isExpanded = sidebar.classList.contains('saw-my-account__sidebar--open');
			toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
		});
		
		// Close menu when clicking outside (mobile only)
		document.addEventListener('click', function(e) {
			if (window.innerWidth >= 768) {
				return; // Only on mobile
			}
			
			var isClickInside = sidebar.contains(e.target) || toggle.contains(e.target);
			
			if (!isClickInside && sidebar.classList.contains('saw-my-account__sidebar--open')) {
				sidebar.classList.remove('saw-my-account__sidebar--open');
				toggle.classList.remove('saw-mobile-toggle--active');
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
		
		// Close menu on window resize to desktop
		var resizeTimer;
		window.addEventListener('resize', function() {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function() {
				if (window.innerWidth >= 768) {
					sidebar.classList.remove('saw-my-account__sidebar--open');
					toggle.classList.remove('saw-mobile-toggle--active');
					toggle.setAttribute('aria-expanded', 'false');
				}
			}, 250);
		});
	});
})();
</script>
