<?php
/**
 * Checkout digital consent placeholder.
 *
 * @package SAW\WAP\Templates
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="bozp-digital-consent">
    <label>
        <input type="checkbox" name="sawwap_digital_consent" value="1" required />
        <?php esc_html_e( 'I agree to instant access to digital content.', 'saw-wap' ); ?>
    </label>
</div>
