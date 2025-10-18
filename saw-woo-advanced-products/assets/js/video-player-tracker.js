/**
 * SAW Video Player Tracker - SIMPLIFIED VERSION
 * 
 * ✅ Trackuje JEN dokončení (≥ 90%)
 * ✅ ŽÁDNÉ auto-save každých 10s
 * ✅ 2 AJAX cally celkem: start_session + mark_completed
 * ✅ Completed = 100% progress, zelený checkmark
 */

(function($) {
	'use strict';

	// === Configuration ===
	const CONFIG = {
		completionThreshold: 0.9,  // 90% = completed
		retryAttempts: 3,
		retryDelay: 2000
	};

	// === State Management ===
	const state = {
		provider: null,
		player: null,
		tokenId: null,
		sessionId: null,
		videoIndex: null,
		productId: null,
		currentTime: 0,
		duration: 0,
		hasCompleted: false,
		isInitialized: false
	};

	// === Player API Loaders ===

	function loadYouTubeAPI() {
		return new Promise(function(resolve) {
			if (window.YT && window.YT.Player) {
				resolve();
				return;
			}

			window.onYouTubeIframeAPIReady = function() {
				console.log('SAW: YouTube API loaded');
				resolve();
			};

			const tag = document.createElement('script');
			tag.src = 'https://www.youtube.com/iframe_api';
			const firstScriptTag = document.getElementsByTagName('script')[0];
			firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
		});
	}

	function loadVimeoAPI() {
		return new Promise(function(resolve) {
			if (window.Vimeo && window.Vimeo.Player) {
				resolve();
				return;
			}

			const script = document.createElement('script');
			script.src = 'https://player.vimeo.com/api/player.js';
			script.onload = function() {
				console.log('SAW: Vimeo API loaded');
				resolve();
			};
			script.onerror = function() {
				console.error('SAW: Failed to load Vimeo API');
				resolve();
			};
			document.head.appendChild(script);
		});
	}

	// === Player Initialization ===

	function initYouTubePlayer(iframe) {
		return loadYouTubeAPI().then(function() {
			return new Promise(function(resolve) {
				state.player = new window.YT.Player(iframe, {
					events: {
						'onReady': function() {
							console.log('SAW: YouTube player ready');
							resolve();
						},
						'onStateChange': onYouTubeStateChange
					}
				});
			});
		});
	}

	function initVimeoPlayer(iframe) {
		return loadVimeoAPI().then(function() {
			state.player = new window.Vimeo.Player(iframe);

			state.player.on('timeupdate', function(data) {
				onTimeUpdate(data.seconds, data.duration);
			});

			console.log('SAW: Vimeo player ready');
			return Promise.resolve();
		});
	}

	function onYouTubeStateChange(event) {
		const YT = window.YT;

		if (event.data === YT.PlayerState.PLAYING) {
			startTimePolling();
		} else if (event.data === YT.PlayerState.PAUSED || event.data === YT.PlayerState.ENDED) {
			stopTimePolling();
			
			if (event.data === YT.PlayerState.ENDED && !state.hasCompleted) {
				markVideoCompleted();
			}
		}
	}

	// Poll YouTube current time
	let timePollingInterval = null;
	function startTimePolling() {
		if (timePollingInterval) return;

		timePollingInterval = setInterval(function() {
			if (state.player && state.player.getCurrentTime && state.player.getDuration) {
				const current = state.player.getCurrentTime();
				const duration = state.player.getDuration();
				onTimeUpdate(current, duration);
			}
		}, 1000);
	}

	function stopTimePolling() {
		if (timePollingInterval) {
			clearInterval(timePollingInterval);
			timePollingInterval = null;
		}
	}

	// === Event Handlers ===

	function onTimeUpdate(currentTime, duration) {
		state.currentTime = currentTime;
		state.duration = duration;

		// ✅ JEDINÁ DŮLEŽITÁ LOGIKA: Check completion threshold
		if (!state.hasCompleted && currentTime > 0 && duration > 0) {
			const progress = currentTime / duration;
			
			if (progress >= CONFIG.completionThreshold) {
				markVideoCompleted();
			}
		}
	}

	// === AJAX Functions ===

	function startSession() {
		return new Promise(function(resolve, reject) {
			$.ajax({
				url: sawwapWatchData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'saw_start_session',
					nonce: sawwapWatchData.nonce,
					token_id: state.tokenId,
					video_index: state.videoIndex
				}
			}).done(function(response) {
				if (response.success && response.data.session_id) {
					resolve(response.data.session_id);
				} else {
					reject(response.data ? response.data.message : 'Unknown error');
				}
			}).fail(function(xhr, status, error) {
				reject(error);
			});
		});
	}

	/**
	 * ✅ JEDINÝ důležitý AJAX call během sledování
	 */
	function markVideoCompleted() {
		if (state.hasCompleted || !state.sessionId) {
			return;
		}

		state.hasCompleted = true;
		console.log('SAW: ✅ Marking video as COMPLETED (90%+ reached)');

		$.ajax({
			url: sawwapWatchData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'saw_mark_completed',
				nonce: sawwapWatchData.nonce,
				session_id: state.sessionId
			}
		}).done(function(response) {
			if (response.success) {
				console.log('SAW: ✅ Video COMPLETED!');
				
				// ✅ Mark sidebar jako completed (zelený check, 100%)
				markSidebarCompleted(state.videoIndex);
				
				// ✅ Update celkový kurz progress
				if (response.data.course_progress) {
					updateCourseProgress(response.data.course_progress);
				}
				
				// ✅ Zobrazit gratulaci
				showNotification('success', '🎉 Gratulujeme! Video dokončeno.');
			}
		}).fail(function(xhr, status, error) {
			console.error('SAW: Mark completed failed:', error);
			
			// Retry mechanismus
			if (!state.hasCompleted) {
				console.log('SAW: Retrying mark_completed...');
				setTimeout(function() {
					state.hasCompleted = false; // Reset pro retry
					markVideoCompleted();
				}, CONFIG.retryDelay);
			}
		});
	}

	// === UI Updates ===

	/**
	 * ✅ Mark video jako completed v sidebaru
	 * - Zelený checkmark
	 * - ŽÁDNÝ progress bar
	 * - 100% = hotovo
	 */
	function markSidebarCompleted(videoIndex) {
		const $item = $('.saw-lesson-item[data-video-index="' + videoIndex + '"]');
		
		if (!$item.length) {
			return;
		}

		// ✅ Změnit ikonu na zelený checkmark
		$item.find('.saw-icon')
			.removeClass('saw-icon-current saw-icon-pending')
			.addClass('saw-icon-completed')
			.text('✅');

		// ✅ Přidat completed class
		$item.addClass('saw-completed');

		// ✅ Animace
		$item.find('.saw-icon-completed').hide().fadeIn(500);

		// ✅ Odstranit progress bar (pokud existuje)
		$item.find('.saw-lesson-progress').remove();
	}

	/**
	 * ✅ Update celkový kurz progress
	 */
	function updateCourseProgress(courseProgress) {
		const $progressSection = $('.saw-progress-section');
		
		if (!$progressSection.length) {
			return;
		}

		const completed = courseProgress.completed;
		const total = courseProgress.total;
		const percent = courseProgress.percent;

		console.log('SAW: Updating course progress:', completed + '/' + total, '(' + percent + '%)');

		// ✅ Update text
		$progressSection.find('.saw-progress-text').html(
			'Dokončeno: <strong>' + completed + ' z ' + total + '</strong> lekcí (' + percent + '%)'
		);

		// ✅ Animate progress bar
		const $fill = $progressSection.find('.saw-progress-fill');
		$fill.css('width', percent + '%');

		// ✅ Pulsing effect
		$progressSection.addClass('saw-progress-updated');
		setTimeout(function() {
			$progressSection.removeClass('saw-progress-updated');
		}, 1000);
	}

	/**
	 * Zobrazit notification
	 */
	function showNotification(type, message) {
		// Remove existing notifications
		$('.saw-notification').remove();

		const $notification = $('<div>')
			.addClass('saw-notification saw-notification-' + type)
			.text(message)
			.appendTo('body')
			.hide()
			.fadeIn();

		setTimeout(function() {
			$notification.fadeOut(function() {
				$(this).remove();
			});
		}, 4000);
	}

	// === Initialization ===

	$(document).ready(function() {
		const $videoWrapper = $('.saw-video-wrapper');
		
		if (!$videoWrapper.length) {
			return;
		}

		// Get data attributes
		state.tokenId = parseInt($videoWrapper.data('token-id'), 10);
		state.videoIndex = parseInt($videoWrapper.data('video-index'), 10);
		state.productId = parseInt($videoWrapper.data('product-id'), 10);

		console.log('SAW: 🚀 Initializing SIMPLIFIED tracker', {
			tokenId: state.tokenId,
			videoIndex: state.videoIndex,
			productId: state.productId
		});

		// Find iframe
		const $iframe = $videoWrapper.find('iframe');
		
		if (!$iframe.length) {
			console.error('SAW: No iframe found');
			return;
		}

		const iframe = $iframe[0];
		const src = $iframe.attr('src');

		// Detect provider
		if (src.indexOf('youtube.com') !== -1 || src.indexOf('youtu.be') !== -1) {
			state.provider = 'youtube';
		} else if (src.indexOf('vimeo.com') !== -1) {
			state.provider = 'vimeo';
		} else {
			console.warn('SAW: Unknown video provider');
			return;
		}

		console.log('SAW: Provider detected:', state.provider);

		// Start session
		startSession().then(function(sessionId) {
			state.sessionId = sessionId;
			console.log('SAW: ✅ Session started:', sessionId);

			// Initialize player based on provider
			if (state.provider === 'youtube') {
				return initYouTubePlayer(iframe);
			} else if (state.provider === 'vimeo') {
				return initVimeoPlayer(iframe);
			}
		}).then(function() {
			state.isInitialized = true;
			console.log('SAW: ✅ Tracker fully initialized - watching for 90% completion...');
		}).catch(function(error) {
			console.error('SAW: Initialization failed:', error);
			showNotification('error', sawwapWatchData.strings.error);
		});
	});

})(jQuery);