/**
 * SAW-WAP Admin Video Repeater
 * 
 * Handles:
 * - Adding/removing video rows
 * - Drag & drop reordering
 * - Collapsing/expanding rows
 * - Auto-updating title preview
 * - Auto-updating lesson order
 */

(function($) {
    'use strict';

    // Počkáme až se DOM načte
    $(document).ready(function() {
        const $repeater = $('#sawwap-video-repeater');
        
        // Pokud repeater neexistuje na stránce, skončíme
        if (!$repeater.length) {
            return;
        }

        let videoIndex = $repeater.find('.sawwap-video-row').length;

        /**
         * Inicializace Sortable.js pro drag & drop
         * 
         * Používáme jQuery UI Sortable (už je součástí WordPress adminu)
         */
        $repeater.sortable({
            handle: '.sawwap-video-handle',
            placeholder: 'sawwap-video-placeholder',
            axis: 'y',
            cursor: 'move',
            opacity: 0.7,
            update: function(event, ui) {
                // Po přetažení aktualizujeme lesson_order pro všechny řádky
                updateLessonOrder();
            }
        });

        /**
         * Přidat nové video
         */
        $('.sawwap-add-video').on('click', function(e) {
            e.preventDefault();

            // Získáme template
            const template = $('#sawwap-video-row-template').html();
            
            // Nahradíme __INDEX__ skutečným indexem
            const newRow = template.replace(/__INDEX__/g, videoIndex);
            
            // Přidáme do repeateru
            $repeater.append(newRow);
            
            // Zvýšíme index pro příští video
            videoIndex++;

            // Aktualizujeme pořadí
            updateLessonOrder();

            // Scrolujeme dolů k novému videu
            const $newRow = $repeater.find('.sawwap-video-row').last();
            $('html, body').animate({
                scrollTop: $newRow.offset().top - 100
            }, 300);

            // Automaticky rozbalíme nový řádek
            $newRow.find('.sawwap-video-row-content').slideDown(200);
            $newRow.find('.sawwap-toggle-video .dashicons')
                .removeClass('dashicons-arrow-down-alt2')
                .addClass('dashicons-arrow-up-alt2');
        });

        /**
         * Odstranit video
         */
        $repeater.on('click', '.sawwap-remove-video', function(e) {
            e.preventDefault();

            const $row = $(this).closest('.sawwap-video-row');
            const videoTitle = $row.find('.sawwap-video-title-preview').text();

            // Confirm dialog
            if (!confirm('Opravdu chcete odstranit video "' + videoTitle + '"?')) {
                return;
            }

            // Animace při odstranění
            $row.slideUp(200, function() {
                $row.remove();
                updateLessonOrder();
            });
        });

        /**
         * Toggle rozbalit/sbalit video
         */
        $repeater.on('click', '.sawwap-toggle-video', function(e) {
            e.preventDefault();

            const $row = $(this).closest('.sawwap-video-row');
            const $content = $row.find('.sawwap-video-row-content');
            const $icon = $(this).find('.dashicons');

            $content.slideToggle(200);

            // Změna ikony
            if ($icon.hasClass('dashicons-arrow-down-alt2')) {
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            } else {
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            }
        });

        /**
         * Auto-update title preview při psaní názvu
         */
        $repeater.on('input', '.sawwap-video-title-input', function() {
            const $row = $(this).closest('.sawwap-video-row');
            const title = $(this).val().trim();
            
            $row.find('.sawwap-video-title-preview').text(
                title || 'Nová lekce'
            );
        });

        /**
         * Aktualizovat lesson_order pro všechny řádky
         * 
         * Volá se po drag & drop nebo přidání/odstranění videa
         */
        function updateLessonOrder() {
            $repeater.find('.sawwap-video-row').each(function(index) {
                $(this).find('.sawwap-lesson-order').val(index);
            });
        }

        /**
         * Inicializace - aktualizujeme pořadí při načtení stránky
         */
        updateLessonOrder();

        /**
         * Validace před odesláním formuláře
         */
        $('form#post').on('submit', function(e) {
            let hasErrors = false;
            const errors = [];

            $repeater.find('.sawwap-video-row').each(function(index) {
                const $row = $(this);
                const title = $row.find('.sawwap-video-title-input').val().trim();
                const url = $row.find('input[name*="[video_url]"]').val().trim();

                // Zkontrolujeme povinná pole
                if (!title) {
                    errors.push('Video #' + (index + 1) + ': Chybí název lekce');
                    hasErrors = true;
                }

                if (!url) {
                    errors.push('Video #' + (index + 1) + ': Chybí URL videa');
                    hasErrors = true;
                }

                // Validace URL formátu
                if (url && !isValidVideoUrl(url)) {
                    errors.push('Video #' + (index + 1) + ': Neplatná URL (podporujeme pouze YouTube a Vimeo)');
                    hasErrors = true;
                }
            });

            if (hasErrors) {
                e.preventDefault();
                alert('Opravte prosím následující chyby:\n\n' + errors.join('\n'));
                return false;
            }
        });

        /**
         * Validace video URL (YouTube nebo Vimeo)
         * 
         * @param {string} url URL k ověření
         * @return {boolean} True pokud je URL validní
         */
        function isValidVideoUrl(url) {
            // YouTube patterns
            const youtubePatterns = [
                /^https?:\/\/(www\.)?youtube\.com\/watch\?v=[\w-]+/,
                /^https?:\/\/youtu\.be\/[\w-]+/
            ];

            // Vimeo pattern
            const vimeoPattern = /^https?:\/\/(www\.)?vimeo\.com\/\d+/;

            // Zkontrolujeme zda URL matchuje nějaký pattern
            for (let pattern of youtubePatterns) {
                if (pattern.test(url)) {
                    return true;
                }
            }

            if (vimeoPattern.test(url)) {
                return true;
            }

            // Vlastní URL (custom) - jen základní validace
            if (url.startsWith('http://') || url.startsWith('https://')) {
                return true;
            }

            return false;
        }

        /**
         * Debug info (jen pro development)
         */
        if (window.console && window.location.search.indexOf('sawwap_debug=1') !== -1) {
            console.log('SAW-WAP Video Repeater initialized');
            console.log('Current video count:', videoIndex);
        }
    });

})(jQuery);