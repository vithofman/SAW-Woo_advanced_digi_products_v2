/**
 * My Account JavaScript
 * 
 * Handles interactivity for My Account pages
 * 
 * @package SAW\WAP\Assets
 */

(function($) {
	'use strict';
	
	/**
	 * Document ready
	 */
	$(document).ready(function() {
		
		// Initialize courses page features
		if ($('.saw-courses').length > 0) {
			initCoursesPage();
		}
		
	});
	
	/**
	 * Initialize Courses Page
	 */
	function initCoursesPage() {
		
		// 1. Search functionality
		initCoursesSearch();
		
		// 2. Accordion toggle for lessons
		initLessonsAccordion();
		
	}
	
	/**
	 * Courses Search
	 */
	function initCoursesSearch() {
		var $searchInput = $('#sawCoursesSearch');
		var $coursesGrid = $('#sawCoursesGrid');
		var $courseCards = $coursesGrid.find('.saw-course-card');
		var $noResults = $('#sawCoursesNoResults');
		
		if ($searchInput.length === 0 || $courseCards.length === 0) {
			return;
		}
		
		// Search on input
		$searchInput.on('input', function() {
			var searchTerm = $(this).val().toLowerCase().trim();
			
			// If empty, show all
			if (searchTerm === '') {
				$courseCards.show();
				$noResults.hide();
				return;
			}
			
			// Filter cards
			var visibleCount = 0;
			
			$courseCards.each(function() {
				var $card = $(this);
				var courseTitle = $card.data('course-title') || '';
				
				if (courseTitle.indexOf(searchTerm) !== -1) {
					$card.show();
					visibleCount++;
				} else {
					$card.hide();
				}
			});
			
			// Show/hide no results message
			if (visibleCount === 0) {
				$noResults.show();
			} else {
				$noResults.hide();
			}
		});
	}
	
	/**
	 * Lessons Accordion Toggle
	 */
	function initLessonsAccordion() {
		$('.saw-toggle-lessons').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var targetId = $button.data('target');
			var $target = $('#' + targetId);
			
			if ($target.length === 0) {
				return;
			}
			
			// Toggle visibility
			var isExpanded = $button.attr('aria-expanded') === 'true';
			
			if (isExpanded) {
				// Collapse
				$target.slideUp(300);
				$button.attr('aria-expanded', 'false');
			} else {
				// Expand
				$target.slideDown(300);
				$button.attr('aria-expanded', 'true');
			}
		});
	}
	
})(jQuery);