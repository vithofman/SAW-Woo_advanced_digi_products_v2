<?php
/**
 * Video course player template (pro shortcode)
 *
 * @package SAW\WAP\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables available: $video, $product, $all_videos, $progress, $prev_token, $next_token, $current_index, $access
?>

<div class="saw-watch-wrapper">
	
	<!-- Breadcrumbs -->
	<div class="saw-breadcrumbs">
		<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'saw-videos' ) ); ?>" class="saw-back-link">
			← Zpět na moje kurzy
		</a>
		<span class="saw-separator">›</span>
		<span class="saw-product-name"><?php echo esc_html( $product->get_name() ); ?></span>
		<span class="saw-separator">›</span>
		<span class="saw-video-name"><?php echo esc_html( $video->video_title ); ?></span>
	</div>
	
	<!-- Video Player -->
	<div class="saw-video-player">
		<?php echo \SAW\WAP\Helpers\VideoHelpers::render_video_embed( $video->video_url, $video->video_provider, $video->video_title ); ?>
	</div>
	
	<!-- Navigation Buttons -->
	<div class="saw-navigation">
		<?php if ( $prev_token ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'token', $prev_token, get_permalink() ) ); ?>" class="saw-btn saw-btn-prev">
				← Předchozí lekce
			</a>
		<?php else : ?>
			<button disabled class="saw-btn saw-btn-prev saw-btn-disabled">← Předchozí lekce</button>
		<?php endif; ?>
		
		<?php if ( $next_token ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'token', $next_token, get_permalink() ) ); ?>" class="saw-btn saw-btn-next">
				Další lekce →
			</a>
		<?php else : ?>
			<button disabled class="saw-btn saw-btn-next saw-btn-disabled">Další lekce →</button>
		<?php endif; ?>
	</div>
	
	<!-- Video Meta -->
	<div class="saw-video-meta">
		<h1 class="saw-video-title"><?php echo esc_html( $video->video_title ); ?></h1>
		<div class="saw-meta-items">
			<?php if ( $video->video_duration > 0 ) : ?>
				<span class="saw-meta-item">
				⏱️ Délka: <?php echo esc_html( \SAW\WAP\Helpers\VideoHelpers::format_duration( (int) $video->video_duration ) ); ?>
				</span>
			<?php endif; ?>
			<span class="saw-meta-item">
				🔒 <?php echo esc_html( \SAW\WAP\Helpers\VideoHelpers::format_access_expires( $access->access_expires ) ); ?>
			</span>
		</div>
	</div>
	
	<!-- Progress Bar -->
	<div class="saw-progress-section">
		<div class="saw-progress-text">
			Dokončeno: <?php echo (int) $progress['completed']; ?> z <?php echo (int) $progress['total']; ?> lekcí (<?php echo esc_html( number_format( $progress['percent'], 1 ) ); ?>%)
		</div>
		<div class="saw-progress-bar">
			<div class="saw-progress-fill" style="width: <?php echo esc_attr( (string) $progress['percent'] ); ?>%"></div>
		</div>
	</div>
	
	<!-- Two Column Layout -->
	<div class="saw-content-layout">
		
		<!-- Left: Video Description -->
		<div class="saw-description">
			<h2 class="saw-section-title">Popis lekce</h2>
			<?php if ( ! empty( $video->video_description ) ) : ?>
				<?php echo wpautop( wp_kses_post( $video->video_description ) ); ?>
			<?php else : ?>
				<p>Žádný popis není k dispozici.</p>
			<?php endif; ?>
		</div>
		
		<!-- Right: Course Outline -->
		<div class="saw-course-outline">
			<h3 class="saw-section-title">📚 Osnova kurzu</h3>
			<ul class="saw-lesson-list">
				<?php foreach ( $all_videos as $idx => $lesson ) : ?>
					<?php
					$is_current = ( $idx === $current_index );
					$is_completed = \SAW\WAP\Helpers\VideoHelpers::is_video_completed(
						get_current_user_id(),
						$product->get_id(),
						(int) $lesson->video_index
					);
					$lesson_token = \SAW\WAP\Helpers\VideoHelpers::get_user_video_token(
						get_current_user_id(),
						$product->get_id(),
						(int) $lesson->video_index
					);
					
					$item_classes = array( 'saw-lesson-item' );
					if ( $is_current ) {
						$item_classes[] = 'saw-current';
					}
					if ( $is_completed ) {
						$item_classes[] = 'saw-completed';
					}
					if ( ! $lesson_token ) {
						$item_classes[] = 'saw-locked';
					}
					?>
					
					<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>" data-video-index="<?php echo esc_attr( (string) $lesson->video_index ); ?>">
						
						<?php if ( $is_completed ) : ?>
							<span class="saw-icon saw-icon-completed">✅</span>
						<?php elseif ( $is_current ) : ?>
							<span class="saw-icon saw-icon-current">►</span>
						<?php else : ?>
							<span class="saw-icon saw-icon-pending">○</span>
						<?php endif; ?>
						
						<?php if ( $lesson_token ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'token', $lesson_token, get_permalink() ) ); ?>" class="saw-lesson-link">
								<span class="saw-lesson-title"><?php echo esc_html( $lesson->video_title ); ?></span>
								<?php if ( $lesson->video_duration > 0 ) : ?>
									<span class="saw-lesson-duration">(<?php echo esc_html( \SAW\WAP\Helpers\VideoHelpers::format_duration( (int) $lesson->video_duration ) ); ?>)</span>
								<?php endif; ?>
							</a>
						<?php else : ?>
							<span class="saw-lesson-locked">
								<span class="saw-lock-icon">🔒</span>
								<span class="saw-lesson-title"><?php echo esc_html( $lesson->video_title ); ?></span>
							</span>
						<?php endif; ?>
						
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		
	</div>
	
</div>