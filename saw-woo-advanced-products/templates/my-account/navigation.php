<?php
/**
 * My Account Navigation Template
 * 
 * Sidebar navigation menu for My Account system
 * 
 * Available variables from parent scope:
 * @var array $menu_items Navigation items from MyAccount::get_menu_items()
 * 
 * @package SAW\WAP\Templates
 * @since 1.0.0
 */

declare(strict_types=1);

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure $menu_items is available
if ( ! isset( $menu_items ) || ! is_array( $menu_items ) ) {
	echo '<div class="saw-error">Menu items not provided</div>';
	return;
}
?>

<nav class="saw-my-account-nav" role="navigation" aria-label="<?php esc_attr_e( 'Hlavní navigace účtu', 'saw-wap' ); ?>">
	<ul class="saw-nav">
		<?php foreach ( $menu_items as $key => $item ) : ?>
			
			<?php
			// Separator before logout
			if ( $key === 'logout' ) {
				echo '<li class="saw-nav__separator"></li>';
			}
			
			// Determine item classes
			$item_classes = array( 'saw-nav__item' );
			
			if ( ! empty( $item['active'] ) ) {
				$item_classes[] = 'saw-nav__item--active';
			}
			
			if ( $key === 'logout' ) {
				$item_classes[] = 'saw-nav__item--logout';
			}
			
			// Check if item has special styling needs
			if ( $key === 'certificates' ) {
				$item_classes[] = 'saw-nav__item--secondary';
			}
			?>
			
			<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
				<a href="<?php echo esc_url( $item['url'] ); ?>" 
				   class="saw-nav__link"
				   <?php if ( ! empty( $item['active'] ) ) : ?>
				   aria-current="page"
				   <?php endif; ?>>
					
					<!-- Icon -->
					<span class="saw-nav__icon" aria-hidden="true">
						<?php echo esc_html( $item['icon'] ); ?>
					</span>
					
					<!-- Text -->
					<span class="saw-nav__text">
						<?php echo esc_html( $item['title'] ); ?>
					</span>
					
					<!-- Active indicator (visual only) -->
					<?php if ( ! empty( $item['active'] ) ) : ?>
						<span class="saw-nav__indicator" aria-hidden="true"></span>
					<?php endif; ?>
					
				</a>
			</li>
			
		<?php endforeach; ?>
	</ul>
</nav>

<?php
/**
 * Optional: Add "Help" section at bottom
 * Uncomment if you want additional links
 */
/*
?>
<div class="saw-nav-footer">
	<div class="saw-nav-footer__section">
		<p class="saw-nav-footer__title">
			<?php esc_html_e( 'Potřebujete pomoc?', 'saw-wap' ); ?>
		</p>
		<ul class="saw-nav-footer__links">
			<li>
				<a href="<?php echo esc_url( home_url( '/faq' ) ); ?>">
					<?php esc_html_e( '❓ Časté otázky', 'saw-wap' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( home_url( '/kontakt' ) ); ?>">
					<?php esc_html_e( '📧 Kontaktujte nás', 'saw-wap' ); ?>
				</a>
			</li>
		</ul>
	</div>
</div>
<?php
*/
?>