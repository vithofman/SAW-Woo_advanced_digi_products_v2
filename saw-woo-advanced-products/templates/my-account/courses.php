<?php
/**
 * My Account Courses Template
 * 
 * Displays user's courses with progress, filters, and search
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
use SAW\WAP\Helpers\VideoHelpers;

// Get user courses
$courses = MyAccount::get_user_courses( $user_id );

// Filter courses by status (from URL parameter)
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all';

// Apply filter
$filtered_courses = array();
foreach ( $courses as $course ) {
	$include = false;
	
	switch ( $filter ) {
		case 'active':
			$include = $course['is_active'] && ! $course['is_completed'];
			break;
		case 'completed':
			$include = $course['is_completed'];
			break;
		case 'expired':
			$include = $course['is_expired'];
			break;
		case 'all':
		default:
			$include = true;
			break;
	}
	
	if ( $include ) {
		$filtered_courses[] = $course;
	}
}

// Count by status for filter buttons
$count_active = count( array_filter( $courses, function( $c ) {
	return $c['is_active'] && ! $c['is_completed'];
} ) );

$count_completed = count( array_filter( $courses, function( $c ) {
	return $c['is_completed'];
} ) );

$count_expired = count( array_filter( $courses, function( $c ) {
	return $c['is_expired'];
} ) );
?>

<div class="saw-courses">
	
	<!-- Page Header -->
	<header class="saw-courses__header">
		<h1 class="saw-courses__title">
			<?php esc_html_e( '🎓 Moje kurzy', 'saw-wap' ); ?>
		</h1>
		<p class="saw-courses__subtitle">
			<?php 
			/* translators: %d: number of courses */
			printf( 
				esc_html__( 'Máte přístup k %d kurzům', 'saw-wap' ), 
				count( $courses ) 
			); 
			?>
		</p>
	</header>

	<?php if ( ! empty( $courses ) ) : ?>

		<!-- Filters & Search Bar -->
		<div class="saw-courses__toolbar">
			
			<!-- Filter Buttons -->
			<div class="saw-courses__filters">
				<a href="<?php echo esc_url( remove_query_arg( 'filter' ) ); ?>" 
				   class="saw-filter-btn <?php echo ( $filter === 'all' ) ? 'saw-filter-btn--active' : ''; ?>">
					<?php esc_html_e( 'Vše', 'saw-wap' ); ?>
					<span class="saw-filter-btn__count"><?php echo count( $courses ); ?></span>
				</a>
				
				<a href="<?php echo esc_url( add_query_arg( 'filter', 'active' ) ); ?>" 
				   class="saw-filter-btn <?php echo ( $filter === 'active' ) ? 'saw-filter-btn--active' : ''; ?>">
					<?php esc_html_e( 'Aktivní', 'saw-wap' ); ?>
					<span class="saw-filter-btn__count"><?php echo $count_active; ?></span>
				</a>
				
				<a href="<?php echo esc_url( add_query_arg( 'filter', 'completed' ) ); ?>" 
				   class="saw-filter-btn <?php echo ( $filter === 'completed' ) ? 'saw-filter-btn--active' : ''; ?>">
					<?php esc_html_e( 'Dokončené', 'saw-wap' ); ?>
					<span class="saw-filter-btn__count"><?php echo $count_completed; ?></span>
				</a>
				
				<?php if ( $count_expired > 0 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'filter', 'expired' ) ); ?>" 
					   class="saw-filter-btn <?php echo ( $filter === 'expired' ) ? 'saw-filter-btn--active' : ''; ?>">
						<?php esc_html_e( 'Vypršelé', 'saw-wap' ); ?>
						<span class="saw-filter-btn__count"><?php echo $count_expired; ?></span>
					</a>
				<?php endif; ?>
			</div>

			<!-- Search Box (JavaScript handled) -->
			<div class="saw-courses__search">
				<input type="text" 
				       class="saw-search-input" 
				       id="sawCoursesSearch" 
				       placeholder="<?php esc_attr_e( '🔍 Hledat kurz...', 'saw-wap' ); ?>" 
				       aria-label="<?php esc_attr_e( 'Hledat v kurzech', 'saw-wap' ); ?>">
			</div>

		</div><!-- .saw-courses__toolbar -->

		<!-- Courses Grid -->
		<div class="saw-courses__grid" id="sawCoursesGrid">
			
			<?php foreach ( $filtered_courses as $course ) : ?>
				
				<?php
				// Course data
				$product_id = $course['product_id'];
				$product = $course['product'];
				$title = $course['title'];
				$image_url = $course['image_url'];
				$progress_percent = $course['progress_percent'];
				$completed_videos = $course['completed_videos'];
				$total_videos = $course['total_videos'];
				$is_completed = $course['is_completed'];
				$is_expired = $course['is_expired'];
				$is_active = $course['is_active'];
				$expiration_date = $course['expiration_date'];
				$next_video = $course['next_video'];
				$videos = $course['videos'];
				
				// Generate unique ID for accordion
				$accordion_id = 'saw-course-' . $product_id;
				?>
				
				<div class="saw-course-card" data-course-title="<?php echo esc_attr( strtolower( $title ) ); ?>">
					
					<!-- Card Inner -->
					<div class="saw-course-card__inner">
						
						<!-- Thumbnail -->
						<div class="saw-course-card__thumbnail">
							<?php if ( $image_url ) : ?>
								<img src="<?php echo esc_url( $image_url ); ?>" 
								     alt="<?php echo esc_attr( $title ); ?>" 
								     loading="lazy">
							<?php else : ?>
								<div class="saw-course-card__thumbnail-placeholder">
									🎓
								</div>
							<?php endif; ?>
							
							<!-- Status Badge (overlay) -->
							<div class="saw-course-card__badge">
								<?php echo AccountHelpers::get_course_completion_badge( $is_completed, $is_expired, $is_active ); ?>
							</div>
						</div>

						<!-- Content -->
						<div class="saw-course-card__content">
							
							<!-- Title -->
							<h3 class="saw-course-card__title">
								<?php echo esc_html( $title ); ?>
							</h3>

							<!-- Progress Bar -->
							<div class="saw-course-card__progress">
								<?php echo AccountHelpers::get_progress_bar_html( $progress_percent ); ?>
							</div>

							<!-- Meta Info -->
							<div class="saw-course-card__meta">
								<span class="saw-course-card__meta-item">
									📝 <?php 
									/* translators: 1: completed videos, 2: total videos */
									printf( 
										esc_html__( '%1$d/%2$d lekcí', 'saw-wap' ), 
										$completed_videos, 
										$total_videos 
									); 
									?>
								</span>
								
								<?php if ( ! $is_expired && $expiration_date ) : ?>
									<span class="saw-course-card__meta-item">
										<?php echo AccountHelpers::format_expiration_status( $expiration_date ); ?>
									</span>
								<?php endif; ?>
							</div>

							<!-- Action Buttons -->
							<div class="saw-course-card__actions">
								
								<?php if ( $is_expired ) : ?>
									
									<!-- Expired - Show "Obnovit přístup" -->
									<a href="<?php echo esc_url( $product->get_permalink() ); ?>" 
									   class="saw-btn saw-btn--secondary saw-btn--small">
										🔄 <?php esc_html_e( 'Obnovit přístup', 'saw-wap' ); ?>
									</a>
									
								<?php elseif ( $is_completed ) : ?>
									
									<!-- Completed - Show "Znovu shlédnout" + "Certifikát" -->
									<?php if ( $next_video ) : ?>
										<a href="<?php echo esc_url( add_query_arg( 'token', $next_video->access_token, home_url( '/watch/' ) ) ); ?>" 
										   class="saw-btn saw-btn--secondary saw-btn--small">
											👁 <?php esc_html_e( 'Znovu shlédnout', 'saw-wap' ); ?>
										</a>
									<?php endif; ?>
									
									<a href="<?php echo esc_url( add_query_arg( array( 'endpoint' => 'certificates', 'course_id' => $product_id ), get_permalink() ) ); ?>" 
									   class="saw-btn saw-btn--primary saw-btn--small">
										📜 <?php esc_html_e( 'Certifikát', 'saw-wap' ); ?>
									</a>
									
								<?php else : ?>
									
									<!-- Active - Show "Pokračovat" or "Začít" -->
									<?php
									$button_text = ( $progress_percent > 0 ) 
										? __( 'Pokračovat', 'saw-wap' ) 
										: __( 'Začít kurz', 'saw-wap' );
									
									$button_icon = ( $progress_percent > 0 ) ? '▶️' : '🚀';
									?>
									
									<?php if ( $next_video ) : ?>
										<a href="<?php echo esc_url( add_query_arg( 'token', $next_video->access_token, home_url( '/watch/' ) ) ); ?>" 
										   class="saw-btn saw-btn--primary saw-btn--small">
											<?php echo esc_html( $button_icon ); ?> <?php echo esc_html( $button_text ); ?>
										</a>
									<?php endif; ?>
									
								<?php endif; ?>

								<!-- Toggle Lessons Button -->
								<button type="button" 
								        class="saw-btn saw-btn--ghost saw-btn--small saw-toggle-lessons" 
								        data-target="<?php echo esc_attr( $accordion_id ); ?>"
								        aria-expanded="false"
								        aria-controls="<?php echo esc_attr( $accordion_id ); ?>">
									📋 <?php esc_html_e( 'Lekce', 'saw-wap' ); ?>
									<span class="saw-toggle-icon">▼</span>
								</button>

							</div><!-- .saw-course-card__actions -->

						</div><!-- .saw-course-card__content -->

					</div><!-- .saw-course-card__inner -->

					<!-- Expandable Lessons List (Accordion) -->
					<div class="saw-course-card__lessons" 
					     id="<?php echo esc_attr( $accordion_id ); ?>" 
					     style="display: none;">
						
						<div class="saw-lessons-list">
							<h4 class="saw-lessons-list__title">
								<?php esc_html_e( 'Osnova kurzu', 'saw-wap' ); ?>
							</h4>
							
							<ul class="saw-lessons">
								<?php foreach ( $videos as $video ) : ?>
									<?php
									$video_index = (int) $video->video_index;
									$video_title = $video->video_title;
									$video_duration = (int) $video->video_duration;
									$is_free = (bool) $video->is_free;
									
									// Check if video is completed
									$is_video_completed = VideoHelpers::is_video_completed( 
										$user_id, 
										$product_id, 
										$video_index 
									);
									
									// Get token for this video
									$video_token = VideoHelpers::get_user_video_token( 
										$user_id, 
										$product_id, 
										$video_index 
									);
									
									// Build watch URL
									$watch_url = $video_token 
										? add_query_arg( 'token', $video_token, home_url( '/watch/' ) ) 
										: '#';
									?>
									
									<li class="saw-lesson-item <?php echo $is_video_completed ? 'saw-lesson-item--completed' : ''; ?>">
										<a href="<?php echo esc_url( $watch_url ); ?>" 
										   class="saw-lesson-link"
										   <?php echo ( ! $video_token ) ? 'onclick="return false;" style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
											
											<!-- Status Icon -->
											<span class="saw-lesson-icon">
												<?php if ( $is_video_completed ) : ?>
													✅
												<?php elseif ( $is_free ) : ?>
													🆓
												<?php else : ?>
													⭕
												<?php endif; ?>
											</span>
											
											<!-- Title -->
											<span class="saw-lesson-title">
												<?php echo esc_html( $video_title ); ?>
											</span>
											
											<!-- Duration -->
											<?php if ( $video_duration > 0 ) : ?>
												<span class="saw-lesson-duration">
													<?php echo esc_html( VideoHelpers::format_video_duration( $video_duration ) ); ?>
												</span>
											<?php endif; ?>
											
										</a>
									</li>
									
								<?php endforeach; ?>
							</ul>
						</div><!-- .saw-lessons-list -->
						
					</div><!-- .saw-course-card__lessons -->

				</div><!-- .saw-course-card -->
				
			<?php endforeach; ?>
			
		</div><!-- .saw-courses__grid -->

		<!-- No Results Message (hidden by default, shown by JS) -->
		<div class="saw-courses__no-results" id="sawCoursesNoResults" style="display: none;">
			<?php
			echo AccountHelpers::get_empty_state(
				'🔍',
				__( 'Žádné kurzy nenalezeny', 'saw-wap' ),
				__( 'Zkuste změnit vyhledávací dotaz nebo filtr.', 'saw-wap' ),
				'',
				''
			);
			?>
		</div>

	<?php else : ?>
		
		<!-- Empty State - No Courses -->
		<div class="saw-courses__empty">
			<?php
			echo AccountHelpers::get_empty_state(
				'🎓',
				__( 'Zatím nemáte žádné kurzy', 'saw-wap' ),
				__( 'Začněte svou první objednávku a získejte přístup k našim kurzům BOZP a PO.', 'saw-wap' ),
				wc_get_page_permalink( 'shop' ),
				__( 'Procházet kurzy', 'saw-wap' )
			);
			?>
		</div>
		
	<?php endif; ?>

</div><!-- .saw-courses -->