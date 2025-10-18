<?php
/**
 * Watch video template - standalone (Oxygen compatible)
 *
 * @package SAW\WAP\Templates
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Získat data z globální proměnné (nastaveno v Videos::watch_template_redirect())
global $saw_watch_data;

if ( empty( $saw_watch_data ) ) {
	wp_die( esc_html__( 'Chyba: Data pro video nebyla načtena.', 'saw-wap' ) );
}

// Extrahovat data pro snadnější práci
$access        = $saw_watch_data['access'];
$video         = $saw_watch_data['video'];
$product       = $saw_watch_data['product'];
$all_videos    = $saw_watch_data['all_videos'];
$current_index = $saw_watch_data['current_index'];
$prev_token    = $saw_watch_data['prev_token'];
$next_token    = $saw_watch_data['next_token'];
$progress      = $saw_watch_data['progress'];

// Helper pro snadnější použití
use SAW\WAP\Helpers\VideoHelpers;

// Current user pro sidebar
$current_user_id = get_current_user_id();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $video->video_title . ' - ' . $product->get_name() ); ?> | <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'saw-watch-page' ); ?>>

<?php
/**
 * Hook před začátkem watch page obsahu.
 * Může být použit pro tracking, analytics, atd.
 */
do_action( 'saw_before_watch_content', $video, $product, $access );
?>

<!-- Header s breadcrumbs -->
<div class="saw-watch-header">
	<div class="saw-container">
		<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'saw-videos' ) ); ?>" 
		   class="saw-back-link"
		   aria-label="<?php esc_attr_e( 'Zpět na přehled kurzů', 'saw-wap' ); ?>">
			<span class="saw-back-icon">←</span>
			<span class="saw-back-text"><?php esc_html_e( 'Zpět na moje kurzy', 'saw-wap' ); ?></span>
		</a>
		
		<nav class="saw-breadcrumbs" aria-label="<?php esc_attr_e( 'Navigační drobečková cesta', 'saw-wap' ); ?>">
			<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="saw-breadcrumb-link">
				<?php echo esc_html( $product->get_name() ); ?>
			</a>
			<span class="saw-separator" aria-hidden="true">›</span>
			<span class="saw-current-page"><?php echo esc_html( $video->video_title ); ?></span>
		</nav>
	</div>
</div>

<!-- Video player sekce -->
<div class="saw-video-section">
	<div class="saw-container">
		<div class="saw-video-wrapper" data-video-index="<?php echo esc_attr( (string) $video->video_index ); ?>">
			<?php 
			echo VideoHelpers::render_video_embed(
				$video->video_url,
				$video->video_provider,
				$video->video_title
			);
			?>
		</div>
	</div>
</div>

<!-- Navigační tlačítka -->
<div class="saw-navigation">
	<div class="saw-container">
		<div class="saw-nav-buttons">
			<?php if ( $prev_token ) : ?>
				<a href="<?php echo esc_url( home_url( '/watch/' . $prev_token . '/' ) ); ?>" 
				   class="saw-btn saw-btn-prev"
				   data-direction="prev"
				   aria-label="<?php esc_attr_e( 'Přejít na předchozí lekci', 'saw-wap' ); ?>">
					<span class="saw-btn-icon" aria-hidden="true">←</span>
					<span class="saw-btn-text"><?php esc_html_e( 'Předchozí lekce', 'saw-wap' ); ?></span>
				</a>
			<?php else : ?>
				<button class="saw-btn saw-btn-prev" 
				        disabled
				        aria-label="<?php esc_attr_e( 'Žádná předchozí lekce', 'saw-wap' ); ?>">
					<span class="saw-btn-icon" aria-hidden="true">←</span>
					<span class="saw-btn-text"><?php esc_html_e( 'Předchozí lekce', 'saw-wap' ); ?></span>
				</button>
			<?php endif; ?>
			
			<?php if ( $next_token ) : ?>
				<a href="<?php echo esc_url( home_url( '/watch/' . $next_token . '/' ) ); ?>" 
				   class="saw-btn saw-btn-next"
				   data-direction="next"
				   aria-label="<?php esc_attr_e( 'Přejít na další lekci', 'saw-wap' ); ?>">
					<span class="saw-btn-text"><?php esc_html_e( 'Další lekce', 'saw-wap' ); ?></span>
					<span class="saw-btn-icon" aria-hidden="true">→</span>
				</a>
			<?php else : ?>
				<button class="saw-btn saw-btn-next" 
				        disabled
				        aria-label="<?php esc_attr_e( 'Žádná další lekce', 'saw-wap' ); ?>">
					<span class="saw-btn-text"><?php esc_html_e( 'Další lekce', 'saw-wap' ); ?></span>
					<span class="saw-btn-icon" aria-hidden="true">→</span>
				</button>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Main content + Sidebar -->
<div class="saw-content-area">
	<div class="saw-container">
		<div class="saw-layout">
			
			<!-- Main Content -->
			<main class="saw-main" role="main">
				
				<!-- Video title -->
				<h1 class="saw-video-title">
					<?php echo esc_html( $video->video_title ); ?>
				</h1>
				
				<!-- Video metadata -->
				<div class="saw-video-meta">
					<?php if ( $video->video_duration > 0 ) : ?>
						<span class="saw-meta-item saw-meta-duration" aria-label="<?php esc_attr_e( 'Délka videa', 'saw-wap' ); ?>">
							<span class="saw-meta-icon" aria-hidden="true">⏱️</span>
							<span class="saw-meta-text">
								<?php 
								/* translators: %s: formatted video duration */
								printf( 
									esc_html__( 'Délka: %s', 'saw-wap' ), 
									esc_html( VideoHelpers::format_duration( (int) $video->video_duration ) ) 
								);
								?>
							</span>
						</span>
					<?php endif; ?>
					
					<?php
					$countdown = VideoHelpers::format_countdown( $access->access_expires );
					?>
					<span class="saw-meta-item saw-meta-countdown <?php echo esc_attr( $countdown['class'] ); ?>" 
					      data-expires="<?php echo esc_attr( $access->access_expires ); ?>"
					      aria-label="<?php esc_attr_e( 'Zbývající přístup', 'saw-wap' ); ?>">
						<span class="saw-meta-icon" aria-hidden="true">🔒</span>
						<span class="saw-meta-text"><?php echo esc_html( $countdown['text'] ); ?></span>
					</span>
				</div>
				
				<!-- Progress section -->
				<div class="saw-progress-section" aria-label="<?php esc_attr_e( 'Průběh kurzu', 'saw-wap' ); ?>">
					<div class="saw-progress-label">
						<?php
						/* translators: 1: completed count, 2: total count, 3: percentage */
						printf(
							esc_html__( 'Dokončeno: %1$d z %2$d lekcí (%3$s%%)', 'saw-wap' ),
							(int) $progress['completed'],
							(int) $progress['total'],
							esc_html( number_format( $progress['percent'], 1 ) )
						);
						?>
					</div>
					<div class="saw-progress-bar" role="progressbar" 
					     aria-valuenow="<?php echo esc_attr( (string) $progress['percent'] ); ?>" 
					     aria-valuemin="0" 
					     aria-valuemax="100">
						<div class="saw-progress-fill" 
						     style="width: <?php echo esc_attr( (string) $progress['percent'] ); ?>%">
						</div>
					</div>
				</div>
				
				<!-- Video description -->
				<?php if ( ! empty( $video->video_description ) ) : ?>
					<div class="saw-video-description">
						<h2 class="saw-description-title">
							<?php esc_html_e( 'Popis lekce', 'saw-wap' ); ?>
						</h2>
						<div class="saw-description-content">
							<?php echo wp_kses_post( wpautop( $video->video_description ) ); ?>
						</div>
					</div>
				<?php endif; ?>
				
				<?php
				/**
				 * Hook pro přidání vlastního obsahu po popisu videa.
				 * Může být použit pro komentáře, poznámky, atd.
				 */
				do_action( 'saw_after_video_description', $video, $product );
				?>
				
			</main>
			
			<!-- Sidebar with course outline -->
			<aside class="saw-sidebar" role="complementary" aria-label="<?php esc_attr_e( 'Osnova kurzu', 'saw-wap' ); ?>">
				<h3 class="saw-sidebar-title">
					<span class="saw-sidebar-icon" aria-hidden="true">📚</span>
					<?php esc_html_e( 'Osnova kurzu', 'saw-wap' ); ?>
				</h3>
				
				<nav class="saw-lesson-list" aria-label="<?php esc_attr_e( 'Seznam lekcí', 'saw-wap' ); ?>">
					<ul class="saw-lesson-items">
						<?php foreach ( $all_videos as $idx => $lesson ) : ?>
							<?php
							$is_current   = ( $idx === $current_index );
							$is_completed = VideoHelpers::is_video_completed(
								$current_user_id,
								$product->get_id(),
								(int) $lesson->video_index
							);
							$lesson_token = VideoHelpers::get_user_video_token(
								$current_user_id,
								$product->get_id(),
								(int) $lesson->video_index
							);
							
							// CSS třídy pro item
							$item_classes   = [ 'saw-lesson-item' ];
							$item_classes[] = $is_current ? 'saw-current' : '';
							$item_classes[] = $is_completed ? 'saw-completed' : '';
							$item_classes[] = ! $lesson_token ? 'saw-locked' : '';
							$item_classes   = array_filter( $item_classes );
							?>
							
							<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>"
							    data-video-index="<?php echo esc_attr( (string) $lesson->video_index ); ?>">
								
								<?php echo VideoHelpers::get_completion_icon( $is_completed, $is_current ); ?>
								
								<?php if ( $lesson_token ) : ?>
									<a href="<?php echo esc_url( home_url( '/watch/' . $lesson_token . '/' ) ); ?>" 
									   class="saw-lesson-link"
									   <?php echo $is_current ? 'aria-current="page"' : ''; ?>
									   aria-label="<?php 
									   		/* translators: %s: lesson title */
									   		echo esc_attr( sprintf( __( 'Přejít na lekci: %s', 'saw-wap' ), $lesson->video_title ) ); 
									   ?>">
										<span class="saw-lesson-title"><?php echo esc_html( $lesson->video_title ); ?></span>
										<?php if ( $lesson->video_duration > 0 ) : ?>
											<span class="saw-lesson-duration" aria-label="<?php esc_attr_e( 'Délka', 'saw-wap' ); ?>">
												<?php echo esc_html( VideoHelpers::format_duration( (int) $lesson->video_duration ) ); ?>
											</span>
										<?php endif; ?>
									</a>
								<?php else : ?>
									<span class="saw-lesson-locked" aria-label="<?php esc_attr_e( 'Lekce není dostupná', 'saw-wap' ); ?>">
										<span class="saw-lock-icon" aria-hidden="true">🔒</span>
										<span class="saw-lesson-title"><?php echo esc_html( $lesson->video_title ); ?></span>
									</span>
								<?php endif; ?>
								
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
				
				<?php
				/**
				 * Hook pro přidání obsahu do sidebaru.
				 */
				do_action( 'saw_sidebar_content', $product, $all_videos );
				?>
				
			</aside>
			
		</div>
	</div>
</div>

<?php
/**
 * Hook po konci watch page obsahu.
 */
do_action( 'saw_after_watch_content', $video, $product, $access );
?>

<?php wp_footer(); ?>
</body>
</html>