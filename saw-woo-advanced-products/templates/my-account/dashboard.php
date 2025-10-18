<?php
/**
 * My Account Dashboard Template
 * 
 * Overview page showing stats, quick actions, and recent activity
 * 
 * Available variables from parent scope:
 * @var WP_User $user    Current user object
 * @var int     $user_id Current user ID
 * 
 * @package SAW\WAP\Templates
 * @since 1.0.0
 */

declare(strict_types=1);

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SAW\WAP\Core\MyAccount;
use SAW\WAP\Helpers\AccountHelpers;

// Get dashboard data
$stats = MyAccount::get_dashboard_stats( $user_id );
$courses = MyAccount::get_user_courses( $user_id );
$recent_orders = MyAccount::get_user_orders( $user_id, 5 ); // Last 5 orders

// Find course in progress (next to continue)
$course_in_progress = null;
foreach ( $courses as $course ) {
	if ( $course['is_active'] && ! $course['is_completed'] && $course['progress_percent'] > 0 ) {
		$course_in_progress = $course;
		break;
	}
}

// If no course in progress, find first active not started
if ( ! $course_in_progress ) {
	foreach ( $courses as $course ) {
		if ( $course['is_active'] && ! $course['is_completed'] ) {
			$course_in_progress = $course;
			break;
		}
	}
}
?>

<div class="saw-dashboard">
	
	<!-- Page Header -->
	<header class="saw-dashboard__header">
		<h1 class="saw-dashboard__title">
			<?php esc_html_e( '📊 Přehled', 'saw-wap' ); ?>
		</h1>
		<p class="saw-dashboard__subtitle">
			<?php esc_html_e( 'Rychlý přehled vašich kurzů a aktivit', 'saw-wap' ); ?>
		</p>
	</header>

	<!-- Statistics Cards -->
	<div class="saw-dashboard__stats">
		
		<!-- Active Courses Card -->
		<div class="saw-stat-card saw-stat-card--primary">
			<div class="saw-stat-card__icon">📚</div>
			<div class="saw-stat-card__content">
				<div class="saw-stat-card__value">
					<?php echo (int) $stats['active_courses']; ?>
				</div>
				<div class="saw-stat-card__label">
					<?php esc_html_e( 'Aktivní kurzy', 'saw-wap' ); ?>
				</div>
			</div>
			<a href="<?php echo esc_url( add_query_arg( 'endpoint', 'courses', get_permalink() ) ); ?>" 
			   class="saw-stat-card__link" 
			   aria-label="<?php esc_attr_e( 'Zobrazit aktivní kurzy', 'saw-wap' ); ?>">
				→
			</a>
		</div>

		<!-- Completed Courses Card -->
		<div class="saw-stat-card saw-stat-card--success">
			<div class="saw-stat-card__icon">✓</div>
			<div class="saw-stat-card__content">
				<div class="saw-stat-card__value">
					<?php echo (int) $stats['completed_courses']; ?>
				</div>
				<div class="saw-stat-card__label">
					<?php esc_html_e( 'Dokončené kurzy', 'saw-wap' ); ?>
				</div>
			</div>
			<a href="<?php echo esc_url( add_query_arg( 'endpoint', 'courses', get_permalink() ) ); ?>" 
			   class="saw-stat-card__link" 
			   aria-label="<?php esc_attr_e( 'Zobrazit dokončené kurzy', 'saw-wap' ); ?>">
				→
			</a>
		</div>

		<!-- Total Orders Card -->
		<div class="saw-stat-card saw-stat-card--info">
			<div class="saw-stat-card__icon">📦</div>
			<div class="saw-stat-card__content">
				<div class="saw-stat-card__value">
					<?php echo (int) $stats['total_orders']; ?>
				</div>
				<div class="saw-stat-card__label">
					<?php esc_html_e( 'Objednávky', 'saw-wap' ); ?>
				</div>
			</div>
			<a href="<?php echo esc_url( add_query_arg( 'endpoint', 'orders', get_permalink() ) ); ?>" 
			   class="saw-stat-card__link" 
			   aria-label="<?php esc_attr_e( 'Zobrazit objednávky', 'saw-wap' ); ?>">
				→
			</a>
		</div>

		<!-- Expiring Soon Card -->
		<div class="saw-stat-card <?php echo ( $stats['expiring_soon'] > 0 ) ? 'saw-stat-card--warning' : 'saw-stat-card--neutral'; ?>">
			<div class="saw-stat-card__icon">⚠️</div>
			<div class="saw-stat-card__content">
				<div class="saw-stat-card__value">
					<?php echo (int) $stats['expiring_soon']; ?>
				</div>
				<div class="saw-stat-card__label">
					<?php esc_html_e( 'Brzy vyprší', 'saw-wap' ); ?>
				</div>
			</div>
			<?php if ( $stats['expiring_soon'] > 0 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'endpoint', 'courses', get_permalink() ) ); ?>" 
				   class="saw-stat-card__link" 
				   aria-label="<?php esc_attr_e( 'Zobrazit kurzy co brzy vyprší', 'saw-wap' ); ?>">
					→
				</a>
			<?php endif; ?>
		</div>

	</div><!-- .saw-dashboard__stats -->

	<!-- Quick Actions Section -->
	<section class="saw-dashboard__section">
		<h2 class="saw-dashboard__section-title">
			<?php esc_html_e( '🚀 Rychlé akce', 'saw-wap' ); ?>
		</h2>
		
		<div class="saw-quick-actions">
			
			<?php if ( $course_in_progress ) : ?>
				<!-- Continue Course Button -->
				<?php
				$next_video = $course_in_progress['next_video'];
				$continue_url = $next_video 
					? add_query_arg( 'token', $next_video->access_token, home_url( '/watch/' ) )
					: add_query_arg( 'endpoint', 'courses', get_permalink() );
				?>
				<a href="<?php echo esc_url( $continue_url ); ?>" 
				   class="saw-quick-action saw-quick-action--primary">
					<span class="saw-quick-action__icon">▶️</span>
					<span class="saw-quick-action__content">
						<span class="saw-quick-action__title">
							<?php esc_html_e( 'Pokračovat v kurzu', 'saw-wap' ); ?>
						</span>
						<span class="saw-quick-action__desc">
							<?php echo esc_html( $course_in_progress['title'] ); ?>
							<br>
							<small><?php echo (int) $course_in_progress['progress_percent']; ?>% dokončeno</small>
						</span>
					</span>
				</a>
			<?php endif; ?>

			<!-- Browse Courses -->
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" 
			   class="saw-quick-action saw-quick-action--secondary">
				<span class="saw-quick-action__icon">🛒</span>
				<span class="saw-quick-action__content">
					<span class="saw-quick-action__title">
						<?php esc_html_e( 'Procházet kurzy', 'saw-wap' ); ?>
					</span>
					<span class="saw-quick-action__desc">
						<?php esc_html_e( 'Objevte naše další kurzy BOZP a PO', 'saw-wap' ); ?>
					</span>
				</span>
			</a>

			<!-- My Courses -->
			<a href="<?php echo esc_url( add_query_arg( 'endpoint', 'courses', get_permalink() ) ); ?>" 
			   class="saw-quick-action saw-quick-action--secondary">
				<span class="saw-quick-action__icon">🎓</span>
				<span class="saw-quick-action__content">
					<span class="saw-quick-action__title">
						<?php esc_html_e( 'Moje kurzy', 'saw-wap' ); ?>
					</span>
					<span class="saw-quick-action__desc">
						<?php 
						/* translators: %d: number of courses */
						printf( 
							esc_html__( 'Máte %d aktivních kurzů', 'saw-wap' ), 
							(int) $stats['active_courses'] 
						); 
						?>
					</span>
				</span>
			</a>

			<!-- Certificates (if has completed courses) -->
			<?php if ( $stats['completed_courses'] > 0 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'endpoint', 'certificates', get_permalink() ) ); ?>" 
				   class="saw-quick-action saw-quick-action--secondary">
					<span class="saw-quick-action__icon">📜</span>
					<span class="saw-quick-action__content">
						<span class="saw-quick-action__title">
							<?php esc_html_e( 'Certifikáty', 'saw-wap' ); ?>
						</span>
						<span class="saw-quick-action__desc">
							<?php 
							/* translators: %d: number of certificates */
							printf( 
								esc_html__( '%d certifikátů k dispozici', 'saw-wap' ), 
								(int) $stats['completed_courses'] 
							); 
							?>
						</span>
					</span>
				</a>
			<?php endif; ?>

		</div><!-- .saw-quick-actions -->
	</section>

	<!-- Recent Activity Section -->
	<section class="saw-dashboard__section">
		<h2 class="saw-dashboard__section-title">
			<?php esc_html_e( '📈 Poslední aktivita', 'saw-wap' ); ?>
		</h2>
		
		<?php if ( ! empty( $recent_orders ) || ! empty( $courses ) ) : ?>
			
			<div class="saw-activity-feed">
				
				<?php
				// Combine recent orders and course progress into activity feed
				$activities = array();
				
				// Add recent orders
				foreach ( $recent_orders as $order ) {
					$activities[] = array(
						'type'      => 'order',
						'timestamp' => strtotime( $order->get_date_created() ),
						'title'     => sprintf( 
							/* translators: %s: order number */
							__( 'Nová objednávka #%s', 'saw-wap' ), 
							$order->get_order_number() 
						),
						'desc'      => AccountHelpers::format_order_status( $order->get_status() ) . ' · ' . 
									   wc_price( $order->get_total() ),
						'url'       => add_query_arg( 
							array( 
								'endpoint' => 'orders', 
								'order_id' => $order->get_id() 
							), 
							get_permalink() 
						),
						'icon'      => '📦',
					);
				}
				
				// Add completed courses (find last 5 completed videos)
				foreach ( $courses as $course ) {
					if ( $course['completed_videos'] > 0 ) {
						$activities[] = array(
							'type'      => 'course_progress',
							'timestamp' => time(), // TODO: get actual completion timestamp
							'title'     => sprintf( 
								/* translators: %s: course title */
								__( 'Pokrok v kurzu: %s', 'saw-wap' ), 
								$course['title'] 
							),
							'desc'      => sprintf( 
								/* translators: 1: completed videos, 2: total videos, 3: percentage */
								__( '%1$d/%2$d lekcí dokončeno (%3$d%%)', 'saw-wap' ), 
								$course['completed_videos'],
								$course['total_videos'],
								(int) $course['progress_percent']
							),
							'url'       => add_query_arg( 'endpoint', 'courses', get_permalink() ),
							'icon'      => '🎓',
						);
					}
				}
				
				// Sort by timestamp (newest first)
				usort( $activities, function( $a, $b ) {
					return $b['timestamp'] - $a['timestamp'];
				});
				
				// Limit to 5 most recent
				$activities = array_slice( $activities, 0, 5 );
				?>
				
				<?php if ( ! empty( $activities ) ) : ?>
					<ul class="saw-activity-list">
						<?php foreach ( $activities as $activity ) : ?>
							<li class="saw-activity-item">
								<a href="<?php echo esc_url( $activity['url'] ); ?>" 
								   class="saw-activity-link">
									<span class="saw-activity-icon">
										<?php echo esc_html( $activity['icon'] ); ?>
									</span>
									<span class="saw-activity-content">
										<span class="saw-activity-title">
											<?php echo esc_html( $activity['title'] ); ?>
										</span>
										<span class="saw-activity-desc">
											<?php echo wp_kses_post( $activity['desc'] ); ?>
										</span>
										<span class="saw-activity-time">
											<?php echo esc_html( AccountHelpers::get_relative_time( date( 'Y-m-d H:i:s', $activity['timestamp'] ) ) ); ?>
										</span>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<?php
					echo AccountHelpers::get_empty_state(
						'📋',
						__( 'Zatím žádná aktivita', 'saw-wap' ),
						__( 'Začněte kurz nebo proveďte nákup a vaše aktivita se zobrazí zde.', 'saw-wap' ),
						add_query_arg( 'endpoint', 'courses', get_permalink() ),
						__( 'Zobrazit kurzy', 'saw-wap' )
					);
					?>
				<?php endif; ?>
				
			</div><!-- .saw-activity-feed -->
			
		<?php else : ?>
			
			<!-- Empty State -->
			<?php
			echo AccountHelpers::get_empty_state(
				'🎉',
				__( 'Vítejte v SAW Academy!', 'saw-wap' ),
				__( 'Začněte svou první objednávku a získejte přístup k našim kurzům BOZP a PO.', 'saw-wap' ),
				wc_get_page_permalink( 'shop' ),
				__( 'Procházet kurzy', 'saw-wap' )
			);
			?>
			
		<?php endif; ?>
		
	</section><!-- Recent Activity -->

</div><!-- .saw-dashboard -->