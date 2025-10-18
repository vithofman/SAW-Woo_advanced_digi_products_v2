/**
 * My Account - Mobile Menu Toggle (Inline)
 * Lightweight script for mobile menu functionality
 */
(function() {
	'use strict';
	
	function initMobileMenu() {
		var toggle = document.getElementById('sawAccountMobileToggle');
		var sidebar = document.getElementById('sawAccountSidebar');
		
		if (!toggle || !sidebar) {
			return;
		}
		
		// Toggle menu on button click
		toggle.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			var isOpen = sidebar.classList.contains('saw-my-account__sidebar--open');
			
			if (isOpen) {
				sidebar.classList.remove('saw-my-account__sidebar--open');
				toggle.classList.remove('saw-mobile-toggle--active');
				toggle.setAttribute('aria-expanded', 'false');
			} else {
				sidebar.classList.add('saw-my-account__sidebar--open');
				toggle.classList.add('saw-mobile-toggle--active');
				toggle.setAttribute('aria-expanded', 'true');
			}
		});
		
		// Close menu when clicking outside (mobile only)
		document.addEventListener('click', function(e) {
			if (window.innerWidth >= 768) {
				return;
			}
			
			var isClickInsideSidebar = sidebar.contains(e.target);
			var isClickOnToggle = toggle.contains(e.target);
			
			if (!isClickInsideSidebar && !isClickOnToggle) {
				sidebar.classList.remove('saw-my-account__sidebar--open');
				toggle.classList.remove('saw-mobile-toggle--active');
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
		
		// Close menu on window resize to desktop
		var resizeTimer;
		window.addEventListener('resize', function() {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function() {
				if (window.innerWidth >= 768) {
					sidebar.classList.remove('saw-my-account__sidebar--open');
					toggle.classList.remove('saw-mobile-toggle--active');
					toggle.setAttribute('aria-expanded', 'false');
				}
			}, 250);
		});
	}
	
	// Init when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initMobileMenu);
	} else {
		initMobileMenu();
	}
	
})();