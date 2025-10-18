<?php
/**
 * Admin notices pro regeneraci tokenů
 *
 * @package SAW\WAP\Admin
 */

declare(strict_types=1);

namespace SAW\WAP\Admin;

if (!defined('ABSPATH')) {
	exit;
}

class RegenerateNotices {

	/**
	 * Initialize hooks
	 */
	public static function init(): void {
		// Registruj hook na admin_notices s nízkou prioritou
		add_action('admin_notices', [__CLASS__, 'show_regenerate_notice'], 10);
		
		// DEBUG log že se třída inicializovala
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SAW-WAP [DEBUG] RegenerateNotices: Class initialized, hook registered');
		}
	}

	/**
	 * Zobrazit admin notice po regeneraci
	 */
	public static function show_regenerate_notice(): void {
		// DEBUG: Log že se metoda volá
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SAW-WAP [DEBUG] RegenerateNotices: show_regenerate_notice() called');
		}
		
		// Získat transient KEY
		$transient_key = 'saw_regenerate_notice_' . get_current_user_id();
		
		// DEBUG: Log transient key
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SAW-WAP [DEBUG] RegenerateNotices: Looking for transient: ' . $transient_key);
		}
		
		// Získat transient
		$notice = get_transient($transient_key);
		
		// DEBUG: Log transient result
		if (defined('WP_DEBUG') && WP_DEBUG) {
			if ($notice) {
				error_log('SAW-WAP [DEBUG] RegenerateNotices: Transient FOUND - ' . wp_json_encode($notice));
			} else {
				error_log('SAW-WAP [DEBUG] RegenerateNotices: Transient NOT FOUND');
			}
		}
		
		// Pokud není transient, return
		if (!$notice) {
			return;
		}
		
		// Smazat transient (zobrazí se jen jednou)
		delete_transient($transient_key);
		
		// DEBUG: Log že mažeme transient
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SAW-WAP [DEBUG] RegenerateNotices: Transient deleted, displaying notice');
		}
		
		// Připravit data
		$type = isset($notice['type']) ? $notice['type'] : 'info';
		$tokens_created = isset($notice['tokens_created']) ? (int)$notice['tokens_created'] : 0;
		$customers = isset($notice['customers']) ? (int)$notice['customers'] : 0;
		$errors = isset($notice['errors']) ? $notice['errors'] : [];
		
		// CSS třída
		$css_class = 'success' === $type ? 'notice-success' : 'notice-info';
		
		?>
		<div class="notice <?php echo esc_attr($css_class); ?> is-dismissible">
			<p><strong>🔄 SAW-WAP: Regenerace tokenů</strong></p>
			
			<?php if ($tokens_created > 0) : ?>
				<p>
					✅ Vytvořeno <strong><?php echo esc_html($tokens_created); ?></strong> nových tokenů 
					pro <strong><?php echo esc_html($customers); ?></strong> zákazníků.
				</p>
			<?php else : ?>
				<p>
					ℹ️ Žádné nové tokeny nebyly vytvořeny. Všichni zákazníci už mají přístup ke všem videím.
				</p>
			<?php endif; ?>
			
			<?php if (!empty($errors)) : ?>
				<p><strong>⚠️ Některé tokeny se nepodařilo vytvořit:</strong></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<?php foreach ($errors as $error) : ?>
						<li><?php echo esc_html($error); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}