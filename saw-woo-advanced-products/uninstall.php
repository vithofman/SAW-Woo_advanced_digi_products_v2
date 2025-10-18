<?php
/**
 * Uninstall handler.
 *
 * @package SAW\WAP
 */

declare( strict_types=1 );

namespace SAW\WAP;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Clean stored options. Keep financial/order metadata for compliance.
$options = [
    'sawwap_points_rate',
    'sawwap_default_access_days',
    'sawwap_max_points_discount_pct',
    'sawwap_bundles_enabled',
    'sawwap_licence_url',
    'sawwap_digital_consent_text',
    'sawwap_discount_mode',
    'sawwap_discount_rules',
    'sawwap_cache_ttl',
    'sawwap_feature_flags',
];

foreach ( $options as $option ) {
    delete_option( $option );
}
