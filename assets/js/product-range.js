/**
 * SDB Product Range – front-end JS for the [sdb_product_range] shortcode.
 * Namespaced entirely apart from configurator.js (which drives the
 * step-by-step modal); the two can run on the same page without conflict.
 */

(function ($) {
	'use strict';

	var cfg  = window.sdbprConfig || {};
	var i18n = cfg.i18n || {};

	/* ── Init every instance on the page (supports more than one shortcode) ── */
	$(document).ready(function () {
		$('.sdbpr-wrap').each(function () {
			initInstance($(this));
		});
	});

	function initInstance($wrap) {
		var state = {
			termId:  parseInt($wrap.data('active-term'), 10) || 0,
			perPage: parseInt($wrap.data('per-page'), 10) || 8,
			page:    1,
			loading: false,
		};
		$wrap.data('sdbprState', state);

		var $cats     = $wrap.find('.sdbpr-cats');
		var $products = $wrap.find('.sdbpr-products');
		var $pager    = $wrap.find('.sdbpr-pagination-wrap');

		/* Category click → filter grid (configurator/link items use their own native behavior) */
		$cats.on('click', '.sdbpr-cat-item[data-type="category"]', function () {
			var termId = parseInt($(this).data('term-id'), 10);
			if (!termId || termId === state.termId) {
				return;
			}
			state.termId = termId;
			state.page   = 1;

			$cats.find('.sdbpr-cat-item').removeClass('sdbpr-active').attr('aria-selected', 'false');
			$(this).addClass('sdbpr-active').attr('aria-selected', 'true');

			loadProducts($wrap, state, $products, $pager);
		});

		/* Pagination click */
		$pager.on('click', '.sdbpr-page-btn', function () {
			if ($(this).hasClass('sdbpr-page-active') || $(this).prop('disabled')) {
				return;
			}
			var page = parseInt($(this).data('page'), 10) || 1;
			if (page === state.page) {
				return;
			}
			state.page = page;
			loadProducts($wrap, state, $products, $pager);
			scrollToTop($wrap);
		});

		/* Qty input: keep it a sane positive integer */
		$products.on('change', '.sdbpr-qty-input', function () {
			var v = Math.max(1, parseInt($(this).val(), 10) || 1);
			$(this).val(v);
		});

		/* Add to cart */
		$products.on('click', '.sdbpr-add-btn', function () {
			addToCart($wrap, $(this));
		});
	}

	/* ── Load products for the current term/page via AJAX ─────────────────── */
	function loadProducts($wrap, state, $products, $pager) {
		if (state.loading) {
			return;
		}
		state.loading = true;

		$products.html('<div class="sdbpr-loading"><span class="sdbpr-spinner"></span></div>');

		$.post(cfg.ajaxUrl, {
			action:   'sdbpr_get_products',
			nonce:    cfg.nonce,
			term_id:  state.termId,
			paged:    state.page,
			per_page: state.perPage,
		}, function (response) {
			state.loading = false;

			if (!response || !response.success) {
				$products.html('<div class="sdbpr-error">' + escHtml(i18n.errorLoading || 'Something went wrong.') + '</div>');
				return;
			}

			var data = response.data;
			renderProducts($products, data.products || []);
			renderPagination($pager, data.current_page || 1, data.max_pages || 1);
		}).fail(function () {
			state.loading = false;
			$products.html('<div class="sdbpr-error">' + escHtml(i18n.errorLoading || 'Something went wrong.') + '</div>');
		});
	}

	/* ── Render helpers (mirror SDB_Product_Range::render_product_card_html / render_pagination_html) ── */
	function renderProducts($products, products) {
		if (!products.length) {
			$products.html('<div class="sdbpr-empty">' + escHtml(i18n.noProducts || 'No products found in this category.') + '</div>');
			return;
		}

		var html = '';
		$.each(products, function (_, p) {
			var oos = !p.in_stock || !p.purchasable;

			html += '<div class="sdbpr-card' + (oos ? ' sdbpr-card--oos' : '') + '" data-product-id="' + p.id + '">' +
						'<a class="sdbpr-card-image" href="' + escAttr(p.permalink) + '">' +
							'<img src="' + escAttr(p.image) + '" alt="' + escAttr(p.name) + '" loading="lazy">' +
						'</a>' +
						'<div class="sdbpr-card-body">' +
							'<a class="sdbpr-card-name" href="' + escAttr(p.permalink) + '">' +
								'<span class="sdbpr-card-name-text">' + escHtml(p.name) + '</span>' +
								'<span class="sdbpr-card-name-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="11"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></span>' +
							'</a>' +
							'<div class="sdbpr-card-price">' + p.price_html + '</div>' +
						'</div>';

			if (oos) {
				html += '<div class="sdbpr-card-oos">' + escHtml(i18n.outOfStock || 'Out of stock') + '</div>';
			} else {
				html += '<div class="sdbpr-card-actions">' +
							'<input type="number" class="sdbpr-qty-input" value="1" min="1" data-product-id="' + p.id + '">' +
							'<button type="button" class="sdbpr-add-btn" data-product-id="' + p.id + '" aria-label="Add to cart">' +
								'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>' +
							'</button>' +
						'</div>';
			}

			html += '</div>';
		});

		$products.html(html);
	}

	function renderPagination($pager, current, max) {
		if (max <= 1) {
			$pager.empty();
			return;
		}

		var html = '<div class="sdbpr-pagination">';
		html += '<button type="button" class="sdbpr-page-btn sdbpr-page-prev" data-page="' + Math.max(1, current - 1) + '"' + (current === 1 ? ' disabled' : '') + '>&lsaquo;</button>';

		for (var p = 1; p <= max; p++) {
			html += '<button type="button" class="sdbpr-page-btn' + (p === current ? ' sdbpr-page-active' : '') + '" data-page="' + p + '">' + p + '</button>';
		}

		html += '<button type="button" class="sdbpr-page-btn sdbpr-page-next" data-page="' + Math.min(max, current + 1) + '"' + (current === max ? ' disabled' : '') + '>&rsaquo;</button>';
		html += '</div>';

		$pager.html(html);
	}

	/* ── Add to cart ─────────────────────────────────────────────────────── */
	function addToCart($wrap, $btn) {
		if ($btn.prop('disabled')) {
			return;
		}

		var productId = parseInt($btn.data('product-id'), 10);
		var $card     = $btn.closest('.sdbpr-card');
		var quantity  = Math.max(1, parseInt($card.find('.sdbpr-qty-input').val(), 10) || 1);

		if (!productId) {
			return;
		}

		var originalHtml = $btn.html();
		$btn.prop('disabled', true);

		$.post(cfg.ajaxUrl, {
			action:     'sdbpr_add_to_cart',
			nonce:      cfg.nonce,
			product_id: productId,
			quantity:   quantity,
		}, function (response) {
			if (response && response.success) {
				$btn.addClass('sdbpr-added');
				showAddedMessage($card);

				// Refresh any theme mini-cart that listens for WooCommerce fragments.
				if (response.data.fragments) {
					$.each(response.data.fragments, function (key, value) {
						$(key).replaceWith(value);
					});
				}
				// Only refresh fragments (mini-cart count, etc). We deliberately do NOT
				// trigger the standard 'added_to_cart' event here — many themes listen
				// for it to pop up their own "View cart" notice/tooltip, which would
				// duplicate the custom "Added ✓ / View cart" message we show above.
				$(document.body).trigger('wc_fragment_refresh');

				setTimeout(function () {
					$btn.removeClass('sdbpr-added').prop('disabled', false).html(originalHtml);
				}, 1200);
			} else {
				alert((response && response.data && response.data.message) || i18n.errorCart || 'Could not add to cart.');
				$btn.prop('disabled', false).html(originalHtml);
			}
		}).fail(function () {
			alert(i18n.errorCart || 'Could not add to cart.');
			$btn.prop('disabled', false).html(originalHtml);
		});
	}

	/* ── "Added ✓ · View cart" message shown below the qty/cart row ───────── */
	function showAddedMessage($card) {
		$card.find('.sdbpr-added-msg').remove();

		var cartUrl = cfg.cartUrl || '';
		var html = '<div class="sdbpr-added-msg">' + escHtml(i18n.added || 'Added ✓');
		if (cartUrl) {
			html += ' <a href="' + escAttr(cartUrl) + '">' + escHtml(i18n.viewCart || 'View cart') + '</a>';
		}
		html += '</div>';

		var $msg = $(html);
		$card.find('.sdbpr-card-actions').after($msg);

		setTimeout(function () {
			$msg.fadeOut(300, function () {
				$(this).remove();
			});
		}, 5000);
	}

	/* ── Utilities ───────────────────────────────────────────────────────── */
	function scrollToTop($wrap) {
		var top = $wrap.offset().top - 80;
		if ($(window).scrollTop() > top) {
			$('html, body').animate({ scrollTop: top }, 300);
		}
	}

	function escHtml(str) {
		return $('<div>').text(str || '').html();
	}

	function escAttr(str) {
		return (str || '').replace(/"/g, '&quot;');
	}

}(jQuery));
