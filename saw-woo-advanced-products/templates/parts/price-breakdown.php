<?php
/**
 * Price breakdown placeholder.
 *
 * @package SAW\WAP\Templates
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="bozp-price-breakdown">
    <span class="bozp-price-original"><?php esc_html_e( 'Original price', 'saw-wap' ); ?></span>
    <span class="bozp-price-final"><?php esc_html_e( 'Final price', 'saw-wap' ); ?></span>
</div>
