/**
 * SDB Product Configurator – front-end JS
 * Supports: regular product steps + booking (date-picker) steps
 */

(function ($) {
    'use strict';

    /* ── State ──────────────────────────────────────────────────────────── */
    var state = {
        currentStep:           1,
        totalSteps:            6,
        currentCategoryPrefix: 'K1',
        selectedProducts:      [],  // [{ id, name, price, quantity, bookingStart?, bookingEnd? }]
        productCache:          {},
    };

    var cfg  = window.sdbpcConfig || {};
    var i18n = cfg.i18n || {};

    function formatPrice(amount) {
        var sym = cfg.currency || '€';
        return sym + parseFloat(amount).toFixed(2).replace('.', ',');
    }

    /* ── DOM refs ───────────────────────────────────────────────────────── */
    var $modal, $stepper, $productsSection, $cartItems,
        $cartSubtotal, $cartTotal, $modalTitle,
        $nextBtnTop, $nextBtnBottom, $closeBtn;

    /* ── Init ───────────────────────────────────────────────────────────── */
    $(document).ready(function () {
        $modal           = $('#sdbpkpConfiguratorModal');
        $stepper         = $('#sdbpkpStepper');
        $productsSection = $('#sdbpkpProductsSection');
        $cartItems       = $('#sdbpkpCartItems');
        $cartSubtotal    = $('#sdbpkpCartSubtotal');
        $cartTotal       = $('#sdbpkpCartTotal');
        $modalTitle      = $('#sdbpkpModalTitle');
        $nextBtnTop      = $('#sdbpkpNextBtnTop');
        $nextBtnBottom   = $('#sdbpkpNextBtnBottom');
        $closeBtn        = $('#sdbpkpCloseModal');

        buildStepper();
        bindEvents();
    });

    /* ── Stepper ─────────────────────────────────────────────────────────── */
    function buildStepper() {
        var steps = getCurrentSteps();
        state.totalSteps = steps.length || 1;
        var html = '';
        $.each(steps, function (index, step) {
            var num = index + 1;
            html += '<div class="sdbpkp-step" data-step="' + num + '" role="tab" tabindex="0">' +
                        '<div class="sdbpkp-step-number">' + num + '</div>' +
                        '<div class="sdbpkp-step-label">' + escHtml(step.label) + '</div>' +
                    '</div>';
        });
        $stepper.html(html);
    }

    /* ── Events ──────────────────────────────────────────────────────────── */
    function bindEvents() {
        $(document).on('click', '[data-configurator]', function () {
            openConfigurator($(this).data('configurator'));
        });

        $closeBtn.on('click', closeConfigurator);
        $modal.on('click', function (e) {
            if ($(e.target).is($modal)) closeConfigurator();
        });

        $stepper.on('click keypress', '.sdbpkp-step', function (e) {
            if (e.type === 'keypress' && e.which !== 13) return;
            goToStep(parseInt($(this).data('step')));
        });

        $nextBtnTop.on('click', nextStep);
        $nextBtnBottom.on('click', nextStep);

        $productsSection.on('click', '.sdbpkp-qty-btn-decrease', function () {
            adjustQty(parseInt($(this).data('id')), -1);
        });
        $productsSection.on('click', '.sdbpkp-qty-btn-increase', function () {
            adjustQty(parseInt($(this).data('id')), 1);
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $modal.hasClass('sdbpkp-active')) closeConfigurator();
        });

        $productsSection.on('change', '.sdbpkp-qty-input', function () {
            var productId = parseInt($(this).data('product-id'));
            var newQty    = Math.max(0, parseInt($(this).val()) || 0);
            $(this).val(newQty);
            var products = $productsSection.data('products') || [];
            var product  = null;
            $.each(products, function (_, p) { if (p.id === productId) { product = p; return false; } });
            if (!product) return;
            var existing = getSelectedProduct(productId);
            if (newQty === 0) {
                removeSelectedProduct(productId);
            } else if (existing) {
                existing.quantity = newQty;
            } else {
                state.selectedProducts.push({ id: product.id, name: product.name, price: product.price, quantity: newQty });
            }
            renderCart();
        });

    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */
    function getCurrentSteps() {
        var tiers = cfg.tiers || {};
        var tier  = tiers[state.currentCategoryPrefix];
        return tier ? (tier.steps || []) : [];
    }

    function getCurrentStepConfig(stepNumber) {
        var steps = getCurrentSteps();
        return steps[stepNumber - 1] || null;
    }

    function isBookingStep(stepNumber) {
        var sc = getCurrentStepConfig(stepNumber);
        return sc && sc.type === 'booking';
    }

    /* ── Open / Close ────────────────────────────────────────────────────── */
    function openConfigurator(prefix) {
        state.currentCategoryPrefix = prefix || 'K1';
        state.currentStep           = 1;
        state.selectedProducts      = [];

        var tier = (cfg.tiers || {})[state.currentCategoryPrefix];
        $modalTitle.text(tier ? tier.title : state.currentCategoryPrefix);

        $modal.addClass('sdbpkp-active');
        $('body').css('overflow', 'hidden');

        buildStepper();
        updateStepperDisplay();
        loadStep(1);
        renderCart();
    }

    function closeConfigurator() {
        $modal.removeClass('sdbpkp-active');
        $('body').css('overflow', '');
    }

    /* ── Navigation ──────────────────────────────────────────────────────── */
    function goToStep(stepNumber) {
        if (stepNumber < 1 || stepNumber > state.totalSteps) return;
        state.currentStep = stepNumber;
        updateStepperDisplay();
        loadStep(stepNumber);
        $modal[0].scrollTop = 0;
    }

    function nextStep() {
        if (state.currentStep < state.totalSteps) {
            goToStep(state.currentStep + 1);
        } else {
            finishConfigurator();
        }
    }

    function updateStepperDisplay() {
        $stepper.find('.sdbpkp-step').each(function () {
            var num = parseInt($(this).data('step'));
            $(this)
                .toggleClass('sdbpkp-active',  num === state.currentStep)
                .toggleClass('sdbpkp-visited', num < state.currentStep);
        });
        var isLast  = state.currentStep === state.totalSteps;
        var btnText = isLast
            ? (i18n.finish || 'Voltooien')
            : (i18n.nextStep || 'Naar stap %d >').replace('%d', state.currentStep + 1);
        $nextBtnTop.text(btnText).toggleClass('sdbpkp-btn-finish', isLast);
        $nextBtnBottom.text(btnText).toggleClass('sdbpkp-btn-finish', isLast);

        // For booking steps, enforce disabled state until date is confirmed
        updateNextButtonState();
    }

    /* ── Load step – routes to booking or product view ──────────────────── */
    function loadStep(stepNumber) {
        if (isBookingStep(stepNumber)) {
            renderBookingStep(stepNumber);
        } else {
            loadStepProducts(stepNumber);
        }
    }

    /* ── Booking step renderer ───────────────────────────────────────────── */
    /* ── Booking step renderer – auto-confirms on date change ───────────── */
    function renderBookingStep(stepNumber) {
        var sc      = getCurrentStepConfig(stepNumber);
        var minDays = sc ? (parseInt(sc.booking_min_days) || 3) : 3;
        var showEnd = sc ? (sc.booking_show_end === '1' || sc.booking_show_end === true) : false;
        var dateType = cfg.datePickerType || 'date';
        var minDate  = addDaysToToday(minDays);

        // Use a fixed virtual ID for booking (no real product ID needed)
        var bookingKey = 'booking-step-' + stepNumber;

        // Restore previously confirmed booking
        var existing       = getSelectedBooking(stepNumber);
        var confirmedStart = existing ? existing.bookingStart : '';
        var confirmedEnd   = existing ? existing.bookingEnd   : '';

        var startValue = confirmedStart || minDate;
        var endValue   = confirmedEnd   || startValue;

        // End date: visible picker OR hidden (mirrors start)
        var endInputHtml = showEnd
            ? '<div>' +
                  '<label for="rental-period-end">' + escHtml(i18n.bookingEndDate || 'Einddatum') + '</label>' +
                  '<input id="rental-period-end" name="rentman_rental_period_end" class="sdbpkp-booking-end" type="' + dateType + '" min="' + minDate + '" value="' + escAttr(endValue) + '" onkeydown="return false;">' +
              '</div>'
            : '<input id="rental-period-end" name="rentman_rental_period_end" class="sdbpkp-booking-end" type="hidden" value="' + escAttr(startValue) + '">';

        var html =
            '<div class="sdbpkp-booking-box rental-period" data-step-key="' + escAttr(bookingKey) + '" data-step="' + stepNumber + '" data-show-end="' + (showEnd ? '1' : '0') + '">' +
                '<div>' +
                    '<label for="rental-period-start">' + escHtml(i18n.bookingStartDate || 'Datum van de reveal') + '</label>' +
                    '<input id="rental-period-start" name="rentman_rental_period_start" class="sdbpkp-booking-start" type="' + dateType + '" required min="' + minDate + '" value="' + escAttr(startValue) + '" onkeydown="return false;">' +
                '</div>' +
                endInputHtml +
            '</div>';

        $productsSection.html(html);

        // Auto-confirm with current values immediately (date is pre-filled)
        autoConfirmBooking(stepNumber, bookingKey, startValue, endValue, showEnd);

        // Re-confirm whenever start date changes
        $productsSection.on('change', '#rental-period-start', function () {
            var start = $(this).val();
            var end   = showEnd ? $('#rental-period-end').val() : start;
            if (!end || end < start) {
                end = start;
                if (showEnd) $('#rental-period-end').val(end);
            }
            if (!showEnd) $('#rental-period-end').val(start);
            autoConfirmBooking(stepNumber, bookingKey, start, end, showEnd);
        });

        // Re-confirm when end date changes (only if visible)
        if (showEnd) {
            $productsSection.on('change', '#rental-period-end', function () {
                var start = $('#rental-period-start').val();
                var end   = $(this).val();
                if (!end || end < start) { end = start; $(this).val(end); }
                autoConfirmBooking(stepNumber, bookingKey, start, end, showEnd);
            });
        }

        updateNextButtonState();
    }

    function autoConfirmBooking(stepNumber, bookingKey, startVal, endVal, showEnd) {
        if (!startVal) return;
        if (!endVal || endVal < startVal) endVal = startVal;

        // Remove previous booking entry for this step
        state.selectedProducts = state.selectedProducts.filter(function (p) {
            return !(p.isBooking && p.bookingStepKey === bookingKey);
        });

        state.selectedProducts.push({
            id:            bookingKey,      // virtual key, no real product ID
            bookingStepKey: bookingKey,
            name:          i18n.bookingStartDate || 'Datum van de reveal',
            price:         0,
            quantity:      1,
            bookingStart:  startVal,
            bookingEnd:    endVal,
            isBooking:     true,
            showEnd:       showEnd,
        });

        renderCart();
        updateNextButtonState();
    }

    /* ── Get booking entry for a step ────────────────────────────────────── */
    function getSelectedBooking(stepNumber) {
        var key = 'booking-step-' + stepNumber;
        var found = null;
        state.selectedProducts.forEach(function (p) {
            if (p.isBooking && p.bookingStepKey === key) found = p;
        });
        return found;
    }

    function fetchBookingProduct(productId, callback) {
        $.post(cfg.ajaxUrl, {
            action:     'sdbpc_get_booking_product',
            nonce:      cfg.nonce,
            product_id: productId,
        }, function (response) {
            if (response.success && response.data.product && typeof callback === 'function') {
                callback(response.data.product);
            }
        });
    }

    /* ── Enable/disable next button based on booking confirmation ─────────── */
    function updateNextButtonState() {
        var sc = getCurrentStepConfig(state.currentStep);
        if (!sc || sc.type !== 'booking') {
            $nextBtnTop.prop('disabled', false).css('opacity', '');
            $nextBtnBottom.prop('disabled', false).css('opacity', '');
            return;
        }
        // Booking step is always auto-confirmed (date pre-filled), always enable
        $nextBtnTop.prop('disabled', false).css('opacity', '');
        $nextBtnBottom.prop('disabled', false).css('opacity', '');
    }

    function addDaysToToday(n) {
        var d = new Date();
        d.setDate(d.getDate() + n);
        var m   = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    /* ── Regular product AJAX load ───────────────────────────────────────── */
    function loadStepProducts(step) {
        var steps      = getCurrentSteps();
        var stepConfig = steps[step - 1] || null;
        var stepKey    = stepConfig ? stepConfig.key : String(step).padStart(2, '0');
        var cacheKey   = state.currentCategoryPrefix + '-' + stepKey;

        if (state.productCache[cacheKey]) {
            renderProducts(state.productCache[cacheKey]);
            return;
        }

        $productsSection.html('<div class="sdbpkp-loading"><span class="sdbpkp-spinner"></span>Laden…</div>');

        $.post(cfg.ajaxUrl, {
            action:  'sdbpc_get_products',
            nonce:   cfg.nonce,
            prefix:  state.currentCategoryPrefix,
            step:    stepKey,
        }, function (response) {
            if (response.success && response.data.products) {
                state.productCache[cacheKey] = {
                    products:    response.data.products,
                    description: response.data.cat_description || '',
                };
                renderProducts(state.productCache[cacheKey]);
            } else {
                $productsSection.html(
                    '<div class="sdbpkp-no-products">' +
                    escHtml(i18n.noProducts || 'Geen producten beschikbaar voor deze stap.') +
                    '</div>'
                );
            }
        }).fail(function () {
            $productsSection.html('<div class="sdbpkp-error">' + escHtml(i18n.errorAddingCart || 'Er is een fout opgetreden.') + '</div>');
        });
    }

    /* ── Render product list ─────────────────────────────────────────────── */
    function renderProducts(data) {
        var products    = Array.isArray(data) ? data : (data.products || []);
        var description = Array.isArray(data) ? '' : (data.description || '');

        if (!products || products.length === 0) {
            $productsSection.html(
                (description ? '<div class="sdbpkp-cat-description">' + description + '</div>' : '') +
                '<div class="sdbpkp-no-products">' + escHtml(i18n.noProducts || 'Geen producten beschikbaar voor deze stap.') + '</div>'
            );
            return;
        }

        var html = '';
        $.each(products, function (_, product) {
            var existing   = getSelectedProduct(product.id);
            var defaultQty = product.default_qty || 0;
            var qty        = existing ? existing.quantity : defaultQty;

            if (!existing && defaultQty > 0) {
                state.selectedProducts.push({ id: product.id, name: product.name, price: product.price, quantity: defaultQty });
            }

            var imgHtml = product.image
                ? '<img src="' + escAttr(product.image) + '" alt="' + escAttr(product.name) + '">'
                : '📦';

            html += '<div class="sdbpkp-product-item" data-product-id="' + product.id + '">' +
                        '<div class="sdbpkp-product-image">' + imgHtml + '</div>' +
                        '<div class="sdbpkp-product-details">' +
                            '<div class="sdbpkp-product-left">' +
                                '<div class="sdbpkp-product-name">' + escHtml(product.name) + '</div>' +
                                '<div class="sdbpkp-product-description">' + escHtml(product.description) + '</div>' +
                            '</div>' +
                            '<div class="sdbpkp-product-right">' +
                                '<div class="sdbpkp-product-price">' + formatPrice(product.price) + '</div>' +
                                '<div class="sdbpkp-quantity-control">' +
                                    '<button class="sdbpkp-qty-btn sdbpkp-qty-btn-decrease" data-id="' + product.id + '" aria-label="Verminderen">&#8722;</button>' +
                                    '<input type="number" class="sdbpkp-qty-input" value="' + qty + '" min="0" data-product-id="' + product.id + '">' +
                                    '<button class="sdbpkp-qty-btn sdbpkp-qty-btn-increase" data-id="' + product.id + '" aria-label="Verhogen">+</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
        });

        var descHtml = description ? '<div class="sdbpkp-cat-description">' + description + '</div>' : '';
        $productsSection.html(descHtml + html);
        $productsSection.data('products', products);
        renderCart();
    }

    /* ── Qty adjustment ──────────────────────────────────────────────────── */
    function adjustQty(productId, delta) {
        var products = $productsSection.data('products') || [];
        var product  = null;
        $.each(products, function (_, p) { if (p.id === productId) { product = p; return false; } });
        if (!product) return;
        var existing = getSelectedProduct(productId);
        var newQty   = existing ? (existing.quantity + delta) : (delta > 0 ? delta : 0);
        newQty = Math.max(0, newQty);
        if (newQty === 0) {
            removeSelectedProduct(productId);
        } else if (existing) {
            existing.quantity = newQty;
        } else {
            state.selectedProducts.push({ id: product.id, name: product.name, price: product.price, quantity: newQty });
        }
        $productsSection.find('.sdbpkp-qty-input[data-product-id="' + productId + '"]').val(newQty);
        renderCart();
    }

    /* ── Cart sidebar ────────────────────────────────────────────────────── */
    function renderCart() {
        if (state.selectedProducts.length === 0) {
            $cartItems.html('<div class="sdbpkp-cart-item">' + escHtml(i18n.emptyCart || 'Winkelwagen is leeg') + '</div>');
            $cartSubtotal.hide();
            $cartTotal.text(formatPrice(0));
            return;
        }

        var total     = 0;
        var itemsHtml = '';

        $.each(state.selectedProducts, function (_, item) {
            var itemTotal = item.quantity * item.price;
            total += itemTotal;

            if (item.isBooking) {
                var dateStr = item.bookingStart;
                if (item.showEnd && item.bookingEnd && item.bookingEnd !== item.bookingStart) {
                    dateStr += ' \u2192 ' + item.bookingEnd;
                }
                itemsHtml +=
                    '<div class="sdbpkp-cart-item sdbpkp-cart-item--booking">' +
                        '<span class="dashicons dashicons-calendar-alt" style="color:#667eea;vertical-align:middle;margin-right:4px"></span>' +
                        '<strong>' + escHtml(item.name) + '</strong>' +
                        '<div class="sdbpkp-cart-item-date">' + escHtml(dateStr) + '</div>' +
                    '</div>';
            } else {
                itemsHtml +=
                    '<div class="sdbpkp-cart-item">' +
                        '&bull; ' + item.quantity + 'x ' + escHtml(item.name) +
                        ' <span class="sdbpkp-cart-item-price">' + formatPrice(itemTotal) + '</span>' +
                    '</div>';
            }
        });

        $cartItems.html(itemsHtml);
        $cartSubtotal.text(formatPrice(total)).show();
        $cartTotal.text(formatPrice(total));
    }

    /* ── Finish ──────────────────────────────────────────────────────────── */
    function finishConfigurator() {
        if (state.selectedProducts.length === 0) {
            alert(i18n.selectProducts || 'Voeg eerst producten toe aan uw winkelwagen');
            return;
        }

        $nextBtnTop.prop('disabled', true).text(i18n.addingToCart || 'Toevoegen aan winkelwagen…');
        $nextBtnBottom.prop('disabled', true).text(i18n.addingToCart || 'Toevoegen aan winkelwagen…');

        var items = [];
        $.each(state.selectedProducts, function (_, p) {
            if (p.isBooking) {
                // Get the real product ID from step config if set
                var sc = null;
                getCurrentSteps().forEach(function (step, idx) {
                    if (step.type === 'booking' && ('booking-step-' + (idx + 1)) === p.bookingStepKey) {
                        sc = step;
                    }
                });
                var realId = sc && sc.booking_product_id ? sc.booking_product_id : 0;
                if (realId) {
                    items.push({
                        id:           realId,
                        quantity:     1,
                        bookingStart: p.bookingStart,
                        bookingEnd:   p.bookingEnd,
                        isBooking:    true,
                    });
                } else {
                    // No product ID — just store the date in session via AJAX meta
                    items.push({
                        id:           0,
                        quantity:     0,
                        bookingStart: p.bookingStart,
                        bookingEnd:   p.bookingEnd,
                        isBooking:    true,
                        dateOnly:     true,
                    });
                }
            } else {
                items.push({
                    id:           p.id,
                    quantity:     p.quantity,
                    bookingStart: '',
                    bookingEnd:   '',
                    isBooking:    false,
                });
            }
        });

        $.post(cfg.ajaxUrl, {
            action: 'sdbpc_add_to_cart',
            nonce:  cfg.nonce,
            items:  JSON.stringify(items),
        }, function (response) {
            if (response.success) {
                window.location.href = response.data.cart_url || cfg.cartUrl;
            } else {
                alert(i18n.errorAddingCart || 'Er is een fout opgetreden. Probeer het opnieuw.');
                $nextBtnTop.prop('disabled', false);
                $nextBtnBottom.prop('disabled', false);
                updateStepperDisplay();
            }
        }).fail(function () {
            alert(i18n.errorAddingCart || 'Er is een fout opgetreden. Probeer het opnieuw.');
            $nextBtnTop.prop('disabled', false);
            $nextBtnBottom.prop('disabled', false);
            updateStepperDisplay();
        });
    }

    /* ── Utilities ───────────────────────────────────────────────────────── */
    function getSelectedProduct(id) {
        var found = null;
        $.each(state.selectedProducts, function (_, p) { if (p.id === id) { found = p; return false; } });
        return found;
    }

    function removeSelectedProduct(id) {
        state.selectedProducts = $.grep(state.selectedProducts, function (p) { return p.id !== id; });
    }

    function escHtml(str) { return $('<div>').text(str || '').html(); }
    function escAttr(str) { return (str || '').replace(/"/g, '&quot;'); }

}(jQuery));
