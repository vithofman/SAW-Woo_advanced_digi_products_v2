/**
 * SAW - Watch Video UI
 * 
 * Handles countdown refresh and keyboard navigation.
 */

(function($) {
	'use strict';

	// Wait for DOM ready
	$(document).ready(function() {
		
		// === Countdown Auto-Refresh ===
		const $countdown = $('.saw-meta-countdown[data-expires]');
		
		if ($countdown.length) {
			// Refresh countdown každou minutu
			setInterval(updateCountdown, 60000);
			
			function updateCountdown() {
				const expiresDate = new Date($countdown.data('expires'));
				const now = new Date();
				const diffMs = expiresDate - now;
				const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
				
				let text, cssClass;
				
				if (diffDays > 30) {
					text = 'Přístup do: ' + diffDays + ' dní';
					cssClass = 'saw-countdown-ok';
				} else if (diffDays >= 7) {
					text = 'Brzy vyprší: ' + diffDays + ' dní';
					cssClass = 'saw-countdown-warning';
				} else {
					text = 'POZOR: Vyprší za ' + Math.max(0, diffDays) + ' dní';
					cssClass = 'saw-countdown-critical';
				}
				
				$countdown
					.removeClass('saw-countdown-ok saw-countdown-warning saw-countdown-critical')
					.addClass(cssClass)
					.find('.saw-meta-text')
					.text(text);
			}
		}
		
		// === Keyboard Navigation ===
		const $prevBtn = $('.saw-btn-prev:not([disabled])');
		const $nextBtn = $('.saw-btn-next:not([disabled])');
		
		$(document).on('keydown', function(e) {
			// Ignorovat pokud je focus v input/textarea
			if ($(e.target).is('input, textarea')) {
				return;
			}
			
			// Arrow Left → Previous video
			if (e.key === 'ArrowLeft' && $prevBtn.length) {
				e.preventDefault();
				window.location.href = $prevBtn.attr('href');
			}
			
			// Arrow Right → Next video
			if (e.key === 'ArrowRight' && $nextBtn.length) {
				e.preventDefault();
				window.location.href = $nextBtn.attr('href');
			}
		});
		
		// === Smooth Scroll to Video on Load ===
		setTimeout(function() {
			const $video = $('.saw-video-wrapper');
			if ($video.length && $(window).scrollTop() > 100) {
				$('html, body').animate({
					scrollTop: $video.offset().top - 80
				}, 500);
			}
		}, 300);
		
		// === Visual Feedback for Navigation ===
		$('.saw-btn[data-direction]').on('click', function() {
			const direction = $(this).data('direction');
			$(this).addClass('saw-btn-loading');
			
			// Show loading indicator (optional)
			if (window.console && window.console.log) {
				console.log('Navigating to ' + direction + ' video...');
			}
		});
		
		// === Sidebar Active Item Highlight ===
		const currentVideoIndex = $('.saw-video-wrapper').data('video-index');
		if (currentVideoIndex !== undefined) {
			$(`.saw-lesson-item[data-video-index="${currentVideoIndex}"]`)
				.addClass('saw-current')
				.find('.saw-lesson-link')
				.attr('aria-current', 'page');
		}
		
		// === Debug Info (pouze pokud WP_DEBUG) ===
		if (window.location.search.indexOf('sawwap_debug=1') !== -1) {
			console.log('SAW Watch Video UI initialized');
			console.log('Current video index:', currentVideoIndex);
			console.log('Countdown element:', $countdown.length ? 'Found' : 'Not found');
		}
		
	});
	
})(jQuery);