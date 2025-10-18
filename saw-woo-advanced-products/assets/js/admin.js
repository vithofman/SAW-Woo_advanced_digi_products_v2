(function ($) {
    'use strict';

    const AdminUI = {
        init() {
            this.initLessonRepeater();
            this.initPromoRepeater();
            this.initBundleSuggestions();
            this.initDiscountBuilder();
        },

        initLessonRepeater() {
            $('.sawwap-lessons').each(function () {
                const $wrap = $(this);
                const $list = $wrap.find('.sawwap-repeater__list');

                function createRow(value) {
                    const $template = $list.find('.sawwap-repeater__item--template').first();
                    if (!$template.length) {
                        return null;
                    }

                    const $row = $template.clone();
                    $row.removeClass('sawwap-repeater__item--template');
                    $row.show();
                    $row.find('input').val(value || '');
                    return $row;
                }

                function ensureRow() {
                    if (!$list.find('.sawwap-repeater__item').not('.sawwap-repeater__item--template').length) {
                        const $row = createRow('');
                        if ($row) {
                            $list.append($row);
                        }
                    }
                }

                ensureRow();

                $wrap.on('click', '.sawwap-lessons-add', function (event) {
                    event.preventDefault();
                    const $row = createRow('');
                    if ($row) {
                        $list.append($row);
                    }
                });

                $wrap.on('click', '.sawwap-lessons-bulk', function (event) {
                    event.preventDefault();
                    const promptMessage = $(this).data('prompt') || '';
                    const response = window.prompt(promptMessage);
                    if (!response) {
                        return;
                    }

                    response.split(/
?
/).forEach(function (line) {
                        const trimmed = $.trim(line);
                        if (trimmed) {
                            const $row = createRow(trimmed);
                            if ($row) {
                                $list.append($row);
                            }
                        }
                    });
                });

                $wrap.on('click', '.sawwap-repeater__remove', function (event) {
                    event.preventDefault();
                    const $row = $(this).closest('.sawwap-repeater__item');
                    if ($row.hasClass('sawwap-repeater__item--template')) {
                        return;
                    }

                    $row.remove();
                    ensureRow();
                });
            });
        },

        initPromoRepeater() {
            $('.sawwap-promo').each(function () {
                const $wrap = $(this);
                const $list = $wrap.find('.sawwap-repeater__list');
                const basePrice = parseFloat($wrap.data('base-price')) || 0;
                let suggestions = $wrap.find('.sawwap-promo-suggest').data('suggestion');
                if (typeof suggestions === 'string') {
                    try {
                        suggestions = JSON.parse(suggestions);
                    } catch (error) {
                        suggestions = [];
                    }
                }
                if (!Array.isArray(suggestions)) {
                    suggestions = [];
                }

                function createRow(limit, price) {
                    const $template = $list.find('.sawwap-repeater__item--template').first();
                    if (!$template.length) {
                        return null;
                    }

                    const $row = $template.clone();
                    $row.removeClass('sawwap-repeater__item--template');
                    $row.show();
                    $row.find('input[name="sawwap_promo_tiers_limit[]"]').val(limit || '');
                    if (price) {
                        $row.find('input[name="sawwap_promo_tiers_price[]"]').val(price.toFixed(2));
                    } else {
                        $row.find('input[name="sawwap_promo_tiers_price[]"]').val('');
                    }

                    return $row;
                }

                function ensureRow() {
                    if (!$list.find('.sawwap-repeater__item').not('.sawwap-repeater__item--template').length) {
                        const $row = createRow('', '');
                        if ($row) {
                            $list.append($row);
                        }
                    }
                }

                $wrap.on('click', '.sawwap-promo-add', function (event) {
                    event.preventDefault();
                    const $row = createRow('', '');
                    if ($row) {
                        $list.append($row);
                    }
                });

                $wrap.on('click', '.sawwap-promo-suggest', function (event) {
                    event.preventDefault();
                    if (!basePrice || !suggestions.length) {
                        return;
                    }

                    suggestions.forEach(function (item) {
                        const limit = parseInt(item.limit, 10);
                        const discount = parseFloat(item.discount);
                        if (!limit || !discount) {
                            return;
                        }

                        const price = basePrice * (1 - discount / 100);
                        const $row = createRow(limit, price);
                        if ($row) {
                            $list.append($row);
                        }
                    });
                });

                $wrap.on('click', '.sawwap-repeater__remove', function (event) {
                    event.preventDefault();
                    const $row = $(this).closest('.sawwap-repeater__item');
                    if ($row.hasClass('sawwap-repeater__item--template')) {
                        return;
                    }
                    $row.remove();
                    ensureRow();
                });

                ensureRow();
            });
        },

        initBundleSuggestions() {
            $('.sawwap-bundle-field').each(function () {
                const $wrap = $(this);
                const $select = $wrap.find('.sawwap-bundle-select');
                let suggestions = $wrap.data('suggestions');
                if (typeof suggestions === 'string') {
                    try {
                        suggestions = JSON.parse(suggestions);
                    } catch (error) {
                        suggestions = [];
                    }
                }
                if (!Array.isArray(suggestions)) {
                    suggestions = [];
                }

                if (!suggestions.length) {
                    return;
                }

                $wrap.on('click', '.sawwap-bundle-apply', function (event) {
                    event.preventDefault();
                    suggestions.forEach(function (item) {
                        if (!item || !item.id) {
                            return;
                        }

                        const id = String(item.id);
                        if ($select.find('option[value="' + id + '"]').length) {
                            return;
                        }

                        const option = new Option(item.name || id, id, true, true);
                        $select.append(option);
                    });

                    $select.trigger('change');

                    if ($select.hasClass('wc-product-search')) {
                        $(document.body).trigger('wc-enhanced-select-init');
                    }
                });
            });
        },

        initDiscountBuilder() {
            $('.sawwap-discount-builder').each(function () {
                const $builder = $(this);
                let quantitySuggestions = $builder.data('quantitySuggestions');
                if (typeof quantitySuggestions === 'string') {
                    try {
                        quantitySuggestions = JSON.parse(quantitySuggestions);
                    } catch (error) {
                        quantitySuggestions = [];
                    }
                }
                if (!Array.isArray(quantitySuggestions)) {
                    quantitySuggestions = [];
                }

                function toggleSections() {
                    $builder.find('[data-toggle-target]').each(function () {
                        const $checkbox = $(this);
                        const target = $checkbox.data('toggle-target');
                        const enabled = $checkbox.is(':checked');
                        $builder.find('[data-toggle-block="' + target + '"]').toggleClass('is-hidden', !enabled);
                    });
                }

                function addQuantityRow(limit, percent) {
                    const $template = $builder.find('.sawwap-discount-row--template').first();
                    if (!$template.length) {
                        return null;
                    }

                    const $row = $template.clone();
                    $row.removeClass('sawwap-discount-row--template');
                    $row.show();
                    $row.find('input[name="sawwap_discount_builder[quantity][limit][]"]').val(limit || '');
                    $row.find('input[name="sawwap_discount_builder[quantity][percent][]"]').val(percent || '');
                    return $row;
                }

                function ensureQuantityRow() {
                    const $rows = $builder.find('.sawwap-discount-quantity-rows tr').not('.sawwap-discount-row--template');
                    if (!$rows.length) {
                        const $row = addQuantityRow('', '');
                        if ($row) {
                            $builder.find('.sawwap-discount-quantity-rows').append($row);
                        }
                    }
                }

                function updateHiddenField() {
                    const rules = [];

                    const promoEnabled = $builder.find('input[name="sawwap_discount_builder[promo][enabled]"]').is(':checked');
                    if (promoEnabled) {
                        const percent = parseFloat($builder.find('input[name="sawwap_discount_builder[promo][percent]"]').val());
                        const until = $builder.find('input[name="sawwap_discount_builder[promo][until]"]').val();
                        if (percent > 0 && until) {
                            rules.push({
                                type: 'promo_until',
                                priority: 5,
                                percent: percent,
                                until: until,
                            });
                        }
                    }

                    const quantityEnabled = $builder.find('input[name="sawwap_discount_builder[quantity][enabled]"]').is(':checked');
                    if (quantityEnabled) {
                        const tiers = [];
                        $builder.find('.sawwap-discount-quantity-rows tr').not('.sawwap-discount-row--template').each(function () {
                            const $row = $(this);
                            const limit = parseInt($row.find('input[name="sawwap_discount_builder[quantity][limit][]"]').val(), 10);
                            const percent = parseFloat($row.find('input[name="sawwap_discount_builder[quantity][percent][]"]').val());
                            if (limit > 0 && percent > 0) {
                                tiers.push({ min_qty: limit, percent: percent });
                            }
                        });

                        if (tiers.length) {
                            rules.push({
                                type: 'quantity',
                                priority: 10,
                                tiers: tiers,
                            });
                        }
                    }

                    const membershipEnabled = $builder.find('input[name="sawwap_discount_builder[membership][enabled]"]').is(':checked');
                    if (membershipEnabled) {
                        const percent = parseFloat($builder.find('input[name="sawwap_discount_builder[membership][percent]"]').val());
                        if (percent > 0) {
                            rules.push({
                                type: 'membership',
                                priority: 40,
                                percent: percent,
                            });
                        }
                    }

                    $('#sawwap_discount_rules').val(JSON.stringify(rules));
                }

                toggleSections();
                ensureQuantityRow();
                updateHiddenField();

                $builder.on('change', 'input, select', updateHiddenField);

                $builder.on('change', '.sawwap-discount-toggle', function () {
                    window.requestAnimationFrame(function () {
                        toggleSections();
                        updateHiddenField();
                    });
                });

                $builder.on('click', '.sawwap-discount-add-quantity', function (event) {
                    event.preventDefault();
                    const $row = addQuantityRow('', '');
                    if ($row) {
                        $builder.find('.sawwap-discount-quantity-rows').append($row);
                    }
                });

                $builder.on('click', '.sawwap-discount-remove-row', function (event) {
                    event.preventDefault();
                    const $row = $(this).closest('tr');
                    if ($row.hasClass('sawwap-discount-row--template')) {
                        return;
                    }
                    $row.remove();
                    ensureQuantityRow();
                    updateHiddenField();
                });

                $builder.on('click', '.sawwap-discount-suggest', function (event) {
                    event.preventDefault();
                    if (!Array.isArray(quantitySuggestions) || !quantitySuggestions.length) {
                        return;
                    }

                    quantitySuggestions.forEach(function (item) {
                        const limit = parseInt(item.limit, 10);
                        const percent = parseFloat(item.percent);
                        if (!limit || !percent) {
                            return;
                        }
                        const $row = addQuantityRow(limit, percent);
                        if ($row) {
                            $builder.find('.sawwap-discount-quantity-rows').append($row);
                        }
                    });

                    $builder.find('input[name="sawwap_discount_builder[quantity][enabled]"]').prop('checked', true);
                    toggleSections();
                    updateHiddenField();
                });

                $builder.on('click', '.sawwap-promo-preset', function (event) {
                    event.preventDefault();
                    const days = parseInt($(this).data('days'), 10) || 0;
                    const percent = parseFloat($(this).data('percent')) || 0;
                    if (percent) {
                        $builder.find('input[name="sawwap_discount_builder[promo][percent]"]').val(percent);
                    }
                    if (days) {
                        const now = new Date();
                        now.setDate(now.getDate() + days);
                        const formatted = now.toISOString().slice(0, 16);
                        $builder.find('input[name="sawwap_discount_builder[promo][until]"]').val(formatted);
                    }
                    $builder.find('input[name="sawwap_discount_builder[promo][enabled]"]').prop('checked', true);
                    toggleSections();
                    updateHiddenField();
                });

                $builder.on('click', '.sawwap-membership-preset', function (event) {
                    event.preventDefault();
                    const percent = parseFloat($(this).data('percent')) || 0;
                    if (percent) {
                        $builder.find('input[name="sawwap_discount_builder[membership][percent]"]').val(percent);
                    }
                    $builder.find('input[name="sawwap_discount_builder[membership][enabled]"]').prop('checked', true);
                    toggleSections();
                    updateHiddenField();
                });
            });
        },
    };

    $(function () {
        AdminUI.init();
    });
})(jQuery);
