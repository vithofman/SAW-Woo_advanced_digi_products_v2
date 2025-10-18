/**
 * SAW Video Player Tracker
 * 
 * Trackuje sledování YouTube a Vimeo videí.
 * Ukládá progress každých 10 sekund.
 * Detekuje dokončení (≥ 90%).
 */

(function($) {
	'use strict';

	// === Configuration ===
	const CONFIG = {
		autoSaveInterval: 10000,      // 10 sekund
		completionThreshold: 0.9,      // 90% = completed
		retryAttempts: 3,
		retryDelay: 2000,              // 2 sekundy
		minWatchTime: 3                // Min 3 sekundy před prvním save
	};

	// === State Management ===
	const state = {
		provider: null,                // 'youtube' nebo 'vimeo'
		player: null,
		tokenId: null,
		sessionId: null,
		videoIndex: null,
		productId: null,
		isPlaying: false,
		currentTime: 0,
		duration: 0,
		watchStartTime: null,
		totalWatchedSeconds: 0,
		autoSaveTimer: null,
		hasCompleted: false,
		isInitialized: false
	};

	// === Player API Loaders ===

	/**
	 * Load YouTube IFrame API
	 * @return {Promise}
	 */
	function loadYouTubeAPI() {
		return new Promise(function(resolve) {
			// Already loaded
			if (window.YT && window.YT.Player) {
				resolve();
				return;
			}

			// Set callback
			window.onYouTubeIframeAPIReady = function() {
				console.log('SAW: YouTube API loaded');
				resolve();
			};

			// Load script
			const tag = document.createElement('script');
			tag.src = 'https://www.youtube.com/iframe_api';
			const firstScriptTag = document.getElementsByTagName('script')[0];
			firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
		});
	}

	/**
	 * Load Vimeo Player SDK
	 * @return {Promise}
	 */
	function loadVimeoAPI() {
		return new Promise(function(resolve) {
			// Already loaded
			if (window.Vimeo && window.Vimeo.Player) {
				resolve();
				return;
			}

			// Load script
			const script = document.createElement('script');
			script.src = 'https://player.vimeo.com/api/player.js';
			script.onload = function() {
				console.log('SAW: Vimeo API loaded');
				resolve();
			};
			script.onerror = function() {
				console.error('SAW: Failed to load Vimeo API');
				resolve(); // Resolve anyway to not block
			};
			document.head.appendChild(script);
		});
	}

	// === Player Initialization ===

	/**
	 * Initialize YouTube player
	 * @param {HTMLElement} iframe
	 */
	function initYouTubePlayer(iframe) {
		// Give iframe an ID
		iframe.id = 'saw-youtube-player';

		const player = new YT.Player('saw-youtube-player', {
			events: {
				'onReady': function(event) {
					state.duration = player.getDuration();
					state.player = player;
					state.isInitialized = true;
					
					console.log('SAW: YouTube player ready, duration:', state.duration);
					onPlayerReady();
				},
				'onStateChange': function(event) {
					// YT.PlayerState.PLAYING = 1
					// YT.PlayerState.PAUSED = 2
					// YT.PlayerState.ENDED = 0

					if (event.data === YT.PlayerState.PLAYING) {
						onPlay();
						startTimeUpdateLoop();
					} else if (event.data === YT.PlayerState.PAUSED) {
						onPause();
					} else if (event.data === YT.PlayerState.ENDED) {
						onEnded();
					}
				}
			}
		});

		// YouTube nemá timeupdate event, musíme pollovat
		function startTimeUpdateLoop() {
			if (state.isPlaying && state.player) {
				state.currentTime = state.player.getCurrentTime();
				onTimeUpdate(state.currentTime, state.duration);
				setTimeout(startTimeUpdateLoop, 1000);
			}
		}
	}

	/**
	 * Initialize Vimeo player
	 * @param {HTMLElement} iframe
	 */
	function initVimeoPlayer(iframe) {
		const player = new Vimeo.Player(iframe);

		player.ready().then(function() {
			return player.getDuration();
		}).then(function(duration) {
			state.duration = duration;
			state.player = player;
			state.isInitialized = true;
			
			console.log('SAW: Vimeo player ready, duration:', duration);
			onPlayerReady();
		}).catch(function(error) {
			console.error('SAW: Vimeo player error:', error);
		});

		player.on('play', function() {
			onPlay();
		});

		player.on('pause', function() {
			onPause();
		});

		player.on('ended', function() {
			onEnded();
		});

		player.on('timeupdate', function(data) {
			state.currentTime = data.seconds;
			onTimeUpdate(data.seconds, data.duration);
		});
	}

	// === Event Handlers ===

	/**
	 * Player je připravený
	 */
	function onPlayerReady() {
		console.log('SAW: Player ready, waiting for play event');
		
		// Zobrazit loading indicator (pokud existuje)
		$('.saw-video-loading').fadeOut();
	}

	/**
	 * Video začalo přehrávat
	 */
	function onPlay() {
		if (!state.isInitialized) {
			return;
		}

		console.log('SAW: Video playing');
		
		state.isPlaying = true;
		
		// Pokud ještě nemáme session, vytvořit
		if (!state.sessionId) {
			startSession().then(function(sessionId) {
				state.sessionId = sessionId;
				console.log('SAW: Session started:', sessionId);
				
				// Start auto-save timer
				startAutoSave();
			}).catch(function(error) {
				console.error('SAW: Failed to start session:', error);
				showNotification('error', sawwapWatchData.strings.error);
			});
		} else {
			// Session už existuje, jen restart auto-save
			startAutoSave();
		}

		// Mark start time pro výpočet watch duration
		if (!state.watchStartTime) {
			state.watchStartTime = Date.now();
		}
	}

	/**
	 * Video je pozastavené
	 */
	function onPause() {
		console.log('SAW: Video paused');
		
		state.isPlaying = false;
		
		// Stop auto-save timer
		stopAutoSave();
		
		// Save finální progress
		if (state.sessionId && state.watchStartTime) {
			const watchedThisSession = Math.floor((Date.now() - state.watchStartTime) / 1000);
			state.totalWatchedSeconds += watchedThisSession;
			
			const progressPercent = calculateProgressPercent();
			
			saveProgress(state.sessionId, state.totalWatchedSeconds, progressPercent);
			
			// Reset watch start time
			state.watchStartTime = null;
		}
	}

	/**
	 * Time update event
	 * @param {number} currentTime
	 * @param {number} duration
	 */
	function onTimeUpdate(currentTime, duration) {
		state.currentTime = currentTime;
		state.duration = duration;

		// Check completion threshold
		if (!state.hasCompleted && currentTime > 0 && duration > 0) {
			const progress = currentTime / duration;
			
			if (progress >= CONFIG.completionThreshold) {
				markVideoCompleted();
			}
		}
	}

	/**
	 * Video skončilo
	 */
	function onEnded() {
		console.log('SAW: Video ended');
		
		state.isPlaying = false;
		stopAutoSave();
		
		// Mark completed (pokud ještě není)
		if (!state.hasCompleted) {
			markVideoCompleted();
		}
	}

	// === Progress Calculation ===

	/**
	 * Calculate progress percent
	 * @return {number} 0-100
	 */
	function calculateProgressPercent() {
		if (!state.duration || state.duration <= 0) {
			return 0;
		}

		const percent = Math.floor((state.currentTime / state.duration) * 100);
		return Math.max(0, Math.min(100, percent));
	}

	// === AJAX Functions ===

	/**
	 * Start novou session
	 * @return {Promise<number>} Session ID
	 */
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
	 * Save progress
	 * @param {number} sessionId
	 * @param {number} watchDuration Sekundy
	 * @param {number} progressPercent 0-100
	 * @param {number} retryCount
	 * @return {Promise}
	 */
	function saveProgress(sessionId, watchDuration, progressPercent, retryCount) {
		retryCount = retryCount || 0;

		// Validace
		if (!sessionId || watchDuration < CONFIG.minWatchTime) {
			return Promise.resolve();
		}

		return $.ajax({
			url: sawwapWatchData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'saw_save_progress',
				nonce: sawwapWatchData.nonce,
				session_id: sessionId,
				watch_duration: watchDuration,
				progress_percent: progressPercent
			}
		}).done(function(response) {
			if (response.success) {
				console.log('SAW: Progress saved -', watchDuration, 's,', progressPercent, '%');
				
				// Update sidebar UI
				updateSidebarProgress(state.videoIndex, progressPercent);
			}
		}).fail(function(xhr, status, error) {
			console.error('SAW: Progress save failed:', error);

			// Retry mechanismus
			if (retryCount < CONFIG.retryAttempts) {
				console.log('SAW: Retrying... attempt', retryCount + 1);
				
				setTimeout(function() {
					saveProgress(sessionId, watchDuration, progressPercent, retryCount + 1);
				}, CONFIG.retryDelay);
			} else {
				// Finální failure
				console.error('SAW: All retry attempts failed');
				showNotification('error', sawwapWatchData.strings.error);
			}
		});
	}

	/**
	 * Mark video jako completed
	 */
	function markVideoCompleted() {
		if (state.hasCompleted || !state.sessionId) {
			return;
		}

		state.hasCompleted = true;
		console.log('SAW: Marking video as completed');

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
				console.log('SAW: Video marked as completed!');
				
				// Update sidebar UI
				markSidebarCompleted(state.videoIndex);
				
				// Zobrazit gratulaci
				showNotification('success', '🎉 Gratulujeme! Video dokončeno.');
			}
		}).fail(function(xhr, status, error) {
			console.error('SAW: Failed to mark completed:', error);
			state.hasCompleted = false; // Reset pro retry
		});
	}

	// === Auto-Save Mechanism ===

	/**
	 * Start auto-save timer
	 */
	function startAutoSave() {
		// Stop existující timer (pokud běží)
		stopAutoSave();

		state.autoSaveTimer = setInterval(function() {
			if (state.isPlaying && state.watchStartTime) {
				const watchedThisSession = Math.floor((Date.now() - state.watchStartTime) / 1000);
				const totalWatched = state.totalWatchedSeconds + watchedThisSession;
				const progressPercent = calculateProgressPercent();

				saveProgress(state.sessionId, totalWatched, progressPercent);
			}
		}, CONFIG.autoSaveInterval);

		console.log('SAW: Auto-save timer started');
	}

	/**
	 * Stop auto-save timer
	 */
	function stopAutoSave() {
		if (state.autoSaveTimer) {
			clearInterval(state.autoSaveTimer);
			state.autoSaveTimer = null;
			console.log('SAW: Auto-save timer stopped');
		}
	}

	// === UI Updates ===

	/**
	 * Update progress v sidebaru
	 * @param {number} videoIndex
	 * @param {number} progressPercent 0-100
	 */
	function updateSidebarProgress(videoIndex, progressPercent) {
		const $item = $(`.saw-lesson-item[data-video-index="${videoIndex}"]`);
		
		if (!$item.length) {
			return;
		}

		let $progressBar = $item.find('.saw-lesson-progress');

		// Create progress bar pokud neexistuje
		if (!$progressBar.length) {
			$progressBar = $(`
				<div class="saw-lesson-progress">
					<div class="saw-lesson-progress-bar">
						<div class="saw-lesson-progress-fill" style="width: 0%"></div>
					</div>
					<span class="saw-lesson-progress-text">0%</span>
				</div>
			`);
			
			$item.find('.saw-lesson-link').append($progressBar);
		}

		// Update progress
		$progressBar.show();
		$progressBar.find('.saw-lesson-progress-fill').css('width', progressPercent + '%');
		$progressBar.find('.saw-lesson-progress-text').text(progressPercent + '%');
	}

	/**
	 * Mark video jako completed v sidebaru
	 * @param {number} videoIndex
	 */
	function markSidebarCompleted(videoIndex) {
		const $item = $(`.saw-lesson-item[data-video-index="${videoIndex}"]`);
		
		if (!$item.length) {
			return;
		}

		// Změnit ikonu
		$item.find('.saw-icon')
			.removeClass('saw-icon-current saw-icon-pending')
			.addClass('saw-icon-completed')
			.text('✓');

		// Přidat completed class
		$item.addClass('saw-completed');

		// Animace
		$item.find('.saw-icon-completed').hide().fadeIn(500);

		// Schovat progress bar
		$item.find('.saw-lesson-progress').fadeOut();
	}

	/**
	 * Zobrazit notification
	 * @param {string} type 'success' nebo 'error'
	 * @param {string} message
	 */
	function showNotification(type, message) {
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
		}, 3000);
	}

	// === Initialization ===

	$(document).ready(function() {
		const $videoWrapper = $('.saw-video-wrapper');
		
		if (!$videoWrapper.length) {
			return; // Žádné video na stránce
		}

		const $iframe = $videoWrapper.find('iframe');
		
		if (!$iframe.length) {
			console.error('SAW: No iframe found in video wrapper');
			return;
		}

		// Get metadata z data attributes
		state.tokenId = $videoWrapper.data('token-id');
		state.videoIndex = $videoWrapper.data('video-index');
		state.productId = $videoWrapper.data('product-id');

		if (!state.tokenId || state.videoIndex === undefined) {
			console.error('SAW: Missing required data attributes');
			return;
		}

		// Detect provider
		const src = $iframe.attr('src');
		
		if (!src) {
			console.error('SAW: Iframe has no src');
			return;
		}

		if (src.indexOf('youtube.com') !== -1 || src.indexOf('youtu.be') !== -1) {
			state.provider = 'youtube';
			console.log('SAW: Detected YouTube video');
			
			loadYouTubeAPI().then(function() {
				initYouTubePlayer($iframe[0]);
			}).catch(function(error) {
				console.error('SAW: Failed to load YouTube API:', error);
			});
			
		} else if (src.indexOf('vimeo.com') !== -1) {
			state.provider = 'vimeo';
			console.log('SAW: Detected Vimeo video');
			
			loadVimeoAPI().then(function() {
				initVimeoPlayer($iframe[0]);
			}).catch(function(error) {
				console.error('SAW: Failed to load Vimeo API:', error);
			});
			
		} else {
			console.warn('SAW: Unknown video provider, tracking disabled');
		}

		// Cleanup při opuštění stránky
		$(window).on('beforeunload', function() {
			if (state.isPlaying) {
				onPause(); // Save finální progress
			}
		});
	});

})(jQuery);