<?php
/**
 * Admin settings for the "Product Range" shortcode — pick which category
 * icons appear, their order, a custom icon per row, and products-per-page.
 *
 * Data is stored inside the same `sdbpc_settings` option used by
 * SDB_Configurator_Settings, under the 'product_ranges' / 'range_per_page' /
 * 'range_default_term' keys, so there is only ever one option row to load.
 *
 * @package SDB_Product_Configurator
 */

defined( 'ABSPATH' ) || exit;

class SDB_Range_Settings {

	const OPTION_KEY = 'sdbpc_settings';

	/* ─────────────────────────────────────────────────────────────────────
	 * DEFAULTS / GETTERS  (used by admin UI, shortcode, and AJAX)
	 * ───────────────────────────────────────────────────────────────────── */

	public static function default_per_page() {
		return 8;
	}

	private static function get_option_data() {
		return get_option( self::OPTION_KEY, array() );
	}

	public static function get_ranges() {
		$s = self::get_option_data();
		return ( ! empty( $s['product_ranges'] ) && is_array( $s['product_ranges'] ) ) ? $s['product_ranges'] : array();
	}

	public static function get_per_page() {
		$s = self::get_option_data();
		return ! empty( $s['range_per_page'] ) ? absint( $s['range_per_page'] ) : self::default_per_page();
	}

	/**
	 * The category (term_id) that should be active when the shortcode first renders.
	 *
	 * The saved default does NOT have to be one of the configured icon rows —
	 * e.g. a catch-all "show everything" category (such as an "0-all" category
	 * some sites tag every product into) is a valid default even with no
	 * dedicated icon of its own. We only require that it's a real product
	 * category that actually has products, matching the same relaxed check
	 * used in SDB_Product_Range::render_shortcode().
	 */
	public static function get_default_term_id() {
		$s = self::get_option_data();

		if ( ! empty( $s['range_default_term'] ) ) {
			$term_id = absint( $s['range_default_term'] );
			$term    = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) && $term->count > 0 ) {
				return $term_id;
			}
		}

		foreach ( self::get_ranges() as $range ) {
			if ( ! empty( $range['term_id'] ) ) {
				return absint( $range['term_id'] );
			}
		}

		return 0;
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * ADMIN ASSETS  (called from SDB_Configurator_Settings::enqueue_admin_assets)
	 * ───────────────────────────────────────────────────────────────────── */

	public static function enqueue_admin() {
		wp_enqueue_media();
		wp_add_inline_script( 'jquery-ui-sortable', self::admin_js() );
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * RENDER TAB
	 * ───────────────────────────────────────────────────────────────────── */

	public static function render_tab() {
		$ranges       = self::get_ranges();
		$per_page     = self::get_per_page();
		$default_term = self::get_default_term_id();

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		$tiers = class_exists( 'SDB_Configurator_Settings' ) ? SDB_Configurator_Settings::get_tiers() : array();
		?>
		<div class="sdbpc-card">
			<h2><?php esc_html_e( 'Product Range Shortcode', 'sdb-product-configurator' ); ?></h2>
			<p class="sdbpc-desc">
				<?php esc_html_e( 'Use [sdb_product_range] on any page. It renders a row of round category icons and a paginated product grid; clicking an icon shows that category\'s products via AJAX, without reloading the page. Pick how many categories to show, in what order, and optionally override each one\'s name and icon — otherwise the real WooCommerce category name and thumbnail are used.', 'sdb-product-configurator' ); ?>
			</p>

			<table class="sdbpc-table" style="max-width:460px;margin-bottom:24px;">
				<tr>
					<th><?php esc_html_e( 'Products per page', 'sdb-product-configurator' ); ?></th>
					<td>
						<input type="number" min="1" max="48"
							   name="sdbpc[range_per_page]"
							   value="<?php echo esc_attr( $per_page ); ?>"
							   class="small-text">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Default category', 'sdb-product-configurator' ); ?></th>
					<td>
						<select name="sdbpc[range_default_term]">
							<option value=""><?php esc_html_e( '— first category icon —', 'sdb-product-configurator' ); ?></option>
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( (string) $default_term, (string) $cat->term_id ); ?>>
									<?php echo esc_html( $cat->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Which category\'s products load first, before any icon is clicked.', 'sdb-product-configurator' ); ?></p>
					</td>
				</tr>
			</table>

			<div id="sdbpc-range-rows">
				<?php if ( empty( $ranges ) ) : ?>
					<p class="sdbpc-desc" id="sdbpc-range-empty-hint"><?php esc_html_e( 'No icons yet — click "Add icon" below to add your first category.', 'sdb-product-configurator' ); ?></p>
				<?php endif; ?>
				<?php foreach ( $ranges as $i => $row ) : ?>
					<?php self::render_row( $i, $row, false, $categories, $tiers ); ?>
				<?php endforeach; ?>
			</div>

			<button type="button" class="button button-primary" id="sdbpc-range-add-row">
				+ <?php esc_html_e( 'Add icon', 'sdb-product-configurator' ); ?>
			</button>

			<script type="text/template" id="sdbpc-range-row-template">
				<?php self::render_row( '__RI__', array(), true, $categories, $tiers ); ?>
			</script>
		</div>

		<?php self::render_shortcode_generator( $ranges, $categories, $per_page ); ?>
		<?php
	}

	private static function render_row( $i, $row, $template, $categories, $tiers ) {
		$term_id  = $template ? '' : ( isset( $row['term_id'] ) ? $row['term_id'] : '' );
		$label    = $template ? '' : ( isset( $row['label'] ) ? $row['label'] : '' );
		$icon_id  = $template ? 0 : ( isset( $row['icon_id'] ) ? absint( $row['icon_id'] ) : 0 );
		$icon_url = $icon_id ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '';
		?>
		<div class="sdbpc-range-row" data-index="<?php echo esc_attr( $i ); ?>">
			<span class="dashicons dashicons-menu sdbpc-range-drag" title="Drag to reorder"></span>

			<div class="sdbpc-range-icon-picker">
				<button type="button" class="button sdbpc-range-icon-btn" style="<?php echo $icon_url ? 'background-image:url(' . esc_url( $icon_url ) . ')' : ''; ?>">
					<?php if ( ! $icon_url ) : ?><span class="dashicons dashicons-format-image"></span><?php endif; ?>
				</button>
				<input type="hidden" class="sdbpc-range-icon-id" name="sdbpc[ranges][<?php echo $i; ?>][icon_id]" value="<?php echo esc_attr( $icon_id ); ?>">
				<?php if ( $icon_id ) : ?>
					<button type="button" class="button-link sdbpc-range-icon-remove"><?php esc_html_e( 'Remove', 'sdb-product-configurator' ); ?></button>
				<?php endif; ?>
				<p class="description" style="margin:0;text-align:center;font-size:11px;"><?php esc_html_e( 'Icon (optional)', 'sdb-product-configurator' ); ?></p>
			</div>

			<div class="sdbpc-range-fields">
				<div class="sdbpc-range-field-group sdbpc-range-title-group">
					<label><?php esc_html_e( 'Category', 'sdb-product-configurator' ); ?></label>
					<select name="sdbpc[ranges][<?php echo $i; ?>][term_id]">
						<option value=""><?php esc_html_e( '— choose —', 'sdb-product-configurator' ); ?></option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( (string) $term_id, (string) $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="sdbpc-range-field-group sdbpc-range-title-group">
					<label><?php esc_html_e( 'Display name (optional)', 'sdb-product-configurator' ); ?></label>
					<input type="text" name="sdbpc[ranges][<?php echo $i; ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Uses the category name if empty', 'sdb-product-configurator' ); ?>" class="regular-text">
				</div>

				<button type="button" class="button sdbpc-range-remove-row"><?php esc_html_e( '✕ Remove', 'sdb-product-configurator' ); ?></button>
			</div>
		</div>
		<?php
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * SHORTCODE GENERATOR  (pick configured icons by name, get a ready
	 *    [sdb_product_range ...] string to paste into any page — no need
	 *    to know WooCommerce category IDs.)
	 * ───────────────────────────────────────────────────────────────────── */

	private static function display_label( $row, $categories ) {
		if ( ! empty( $row['label'] ) ) {
			return $row['label'];
		}

		$term_id = isset( $row['term_id'] ) ? (int) $row['term_id'] : 0;
		foreach ( $categories as $cat ) {
			if ( (int) $cat->term_id === $term_id ) {
				return $cat->name;
			}
		}

		return '';
	}

	private static function render_shortcode_generator( $ranges, $categories, $per_page ) {
		// Only rows that actually have a category chosen can be used.
		$rows = array();
		foreach ( $ranges as $row ) {
			if ( ! empty( $row['term_id'] ) ) {
				$rows[] = $row;
			}
		}
		?>
		<div class="sdbpc-card" id="sdbpc-shortcode-generator">
			<h2><?php esc_html_e( 'Shortcode Generator', 'sdb-product-configurator' ); ?></h2>
			<p class="sdbpc-desc">
				<?php esc_html_e( 'Tick the categories a page should show (from the icons configured above), then copy the shortcode and paste it into that page. Every page can show a different set — you never need to type a category ID.', 'sdb-product-configurator' ); ?>
			</p>

			<?php if ( empty( $rows ) ) : ?>
				<p class="sdbpc-desc"><?php esc_html_e( 'Add at least one category icon above first.', 'sdb-product-configurator' ); ?></p>
			<?php else : ?>

				<div id="sdbpc-gen-checks">
					<?php foreach ( $rows as $row ) : ?>
						<label class="sdbpc-gen-check">
							<input type="checkbox" class="sdbpc-gen-cat-check" value="<?php echo esc_attr( absint( $row['term_id'] ) ); ?>" checked>
							<?php echo esc_html( self::display_label( $row, $categories ) ); ?>
						</label>
					<?php endforeach; ?>
				</div>

				<table class="sdbpc-table" style="max-width:460px;margin:10px 0 16px;">
					<tr>
						<th><?php esc_html_e( 'Default category', 'sdb-product-configurator' ); ?></th>
						<td>
							<select id="sdbpc-gen-default">
								<option value=""><?php esc_html_e( '— first ticked category —', 'sdb-product-configurator' ); ?></option>
								<?php foreach ( $rows as $row ) : ?>
									<option value="<?php echo esc_attr( absint( $row['term_id'] ) ); ?>"><?php echo esc_html( self::display_label( $row, $categories ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Which category\'s products load first on this page. Picking it here also ticks it above.', 'sdb-product-configurator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Products per page', 'sdb-product-configurator' ); ?></th>
						<td>
							<input type="number" min="1" max="48" id="sdbpc-gen-per-page" class="small-text" placeholder="<?php echo esc_attr( $per_page ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank to use the site-wide default set above.', 'sdb-product-configurator' ); ?></p>
						</td>
					</tr>
				</table>

				<p style="margin-bottom:6px;"><strong><?php esc_html_e( 'Shortcode for this page:', 'sdb-product-configurator' ); ?></strong></p>
				<div class="sdbpc-gen-output-wrap">
					<input type="text" id="sdbpc-gen-output" readonly value="[sdb_product_range]" onclick="this.select();">
					<button type="button" class="button button-primary" id="sdbpc-gen-copy"><?php esc_html_e( 'Copy', 'sdb-product-configurator' ); ?></button>
				</div>
				<p class="description" id="sdbpc-gen-copy-msg" style="display:none;"></p>

			<?php endif; ?>
		</div>
		<?php
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * SAVE  (called from SDB_Configurator_Settings::handle_save for tab=ranges)
	 * ───────────────────────────────────────────────────────────────────── */

	public static function sanitize_and_merge( $input, $existing ) {
		$existing['range_per_page'] = isset( $input['range_per_page'] ) ? max( 1, absint( $input['range_per_page'] ) ) : self::default_per_page();
		$existing['range_default_term'] = isset( $input['range_default_term'] ) ? absint( $input['range_default_term'] ) : 0;

		$raw_rows = ( isset( $input['ranges'] ) && is_array( $input['ranges'] ) ) ? $input['ranges'] : array();
		$rows     = array();

		foreach ( $raw_rows as $row ) {
			$term_id = isset( $row['term_id'] ) ? absint( $row['term_id'] ) : 0;
			if ( ! $term_id ) {
				continue;
			}

			$rows[] = array(
				'type'    => 'category',
				'term_id' => $term_id,
				'label'   => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
				'icon_id' => isset( $row['icon_id'] ) ? absint( $row['icon_id'] ) : 0,
			);
		}

		$existing['product_ranges'] = $rows;

		return $existing;
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * INLINE ADMIN CSS / JS
	 * ───────────────────────────────────────────────────────────────────── */

	public static function admin_css() {
		return '
		.sdbpc-range-row{display:flex;gap:14px;align-items:flex-start;border:1px solid #e0e0e0;border-radius:6px;padding:14px;margin-bottom:12px;background:#fafafa}
		.sdbpc-range-drag{cursor:grab;color:#aaa;margin-top:30px;flex-shrink:0}
		.sdbpc-range-drag:hover{color:#667eea}
		.sdbpc-range-icon-picker{display:flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0}
		.sdbpc-range-icon-btn{width:64px;height:64px;border-radius:50%;border:1px solid #ccc!important;background-color:#fff;background-position:center;background-size:cover;background-repeat:no-repeat;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0!important}
		.sdbpc-range-icon-btn .dashicons{color:#bbb;font-size:22px}
		.sdbpc-range-icon-remove{color:#c0392b;font-size:11px}
		.sdbpc-range-fields{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;flex:1}
		.sdbpc-range-field-group{display:flex;flex-direction:column;gap:4px;min-width:160px}
		.sdbpc-range-field-group label{font-size:12px;color:#555;font-weight:500}
		.sdbpc-range-title-group{min-width:220px;flex:1}
		.sdbpc-range-inline-check{flex-direction:row!important;align-items:center;gap:6px!important;font-size:12px;font-weight:400!important;margin-top:6px}
		.sdbpc-range-remove-row{align-self:flex-end;color:#c0392b!important;border-color:#e0b0b0!important}
		.sdbpc-range-remove-row:hover{background:#c0392b!important;color:#fff!important;border-color:#c0392b!important}
		#sdbpc-gen-checks{display:flex;flex-wrap:wrap;margin-bottom:4px}
		.sdbpc-gen-check{display:inline-flex;align-items:center;gap:6px;margin:0 18px 10px 0;font-size:13px}
		.sdbpc-gen-output-wrap{display:flex;gap:8px;align-items:center;max-width:640px}
		#sdbpc-gen-output{flex:1;font-family:Consolas,Menlo,monospace;font-size:13px;padding:6px 8px}
		';
	}

	public static function admin_js() {
		return <<<'JS'
jQuery(function($){
    var $rows = $("#sdbpc-range-rows");
    if($rows.length){

    $rows.sortable({ handle: ".sdbpc-range-drag", update: reindex });

    $("#sdbpc-range-add-row").on("click", function(){
        $("#sdbpc-range-empty-hint").remove();
        var tmpl = $("#sdbpc-range-row-template").html();
        var ri   = $rows.find(".sdbpc-range-row").length;
        tmpl = tmpl.split("__RI__").join(String(ri));
        $rows.append(tmpl);
        reindex();
    });

    $rows.on("click", ".sdbpc-range-remove-row", function(){
        $(this).closest(".sdbpc-range-row").remove();
        reindex();
    });

    $rows.on("click", ".sdbpc-range-icon-btn", function(e){
        e.preventDefault();
        var $btn  = $(this);
        var $wrap = $btn.closest(".sdbpc-range-icon-picker");
        var frame = wp.media({ title: "Select icon", multiple: false, library: { type: "image" } });
        frame.on("select", function(){
            var att = frame.state().get("selection").first().toJSON();
            var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
            $btn.css("background-image", "url(" + url + ")").find(".dashicons").remove();
            $wrap.find(".sdbpc-range-icon-id").val(att.id);
            if(!$wrap.find(".sdbpc-range-icon-remove").length){
                $wrap.append('<button type="button" class="button-link sdbpc-range-icon-remove">Remove</button>');
            }
        });
        frame.open();
    });

    $rows.on("click", ".sdbpc-range-icon-remove", function(){
        var $wrap = $(this).closest(".sdbpc-range-icon-picker");
        $wrap.find(".sdbpc-range-icon-id").val("");
        $wrap.find(".sdbpc-range-icon-btn").css("background-image", "").html('<span class="dashicons dashicons-format-image"></span>');
        $(this).remove();
    });

    function reindex(){
        $rows.find(".sdbpc-range-row").each(function(ri){
            $(this).attr("data-index", ri);
            $(this).find("[name]").each(function(){
                var n = $(this).attr("name");
                if(n) $(this).attr("name", n.replace(/\[ranges\]\[\d+\]/, "[ranges][" + ri + "]"));
            });
        });
    }

    }

    /* ── Shortcode generator ── */
    var $genOut = $("#sdbpc-gen-output");
    if($genOut.length){

        function sdbpcGenUpdate(){
            var $checks = $(".sdbpc-gen-cat-check");
            var total   = $checks.length;
            var $picked = $checks.filter(":checked");
            var ids     = $picked.map(function(){ return $(this).val(); }).get();

            var $copyBtn = $("#sdbpc-gen-copy");

            if(!ids.length){
                $genOut.val("Tick at least one category above.");
                $copyBtn.prop("disabled", true);
                return;
            }
            $copyBtn.prop("disabled", false);

            var attrs = [];
            if(ids.length < total){
                attrs.push('categories="' + ids.join(",") + '"');
            }
            var def = $("#sdbpc-gen-default").val();
            if(def){
                attrs.push('default="' + def + '"');
            }
            var pp = $.trim($("#sdbpc-gen-per-page").val());
            if(pp){
                attrs.push('per_page="' + pp + '"');
            }

            var shortcode = attrs.length ? "[sdb_product_range " + attrs.join(" ") + "]" : "[sdb_product_range]";
            $genOut.val(shortcode);
        }

        $("#sdbpc-gen-default").on("change", function(){
            var v = $(this).val();
            if(v){
                $('.sdbpc-gen-cat-check[value="' + v + '"]').prop("checked", true);
            }
        });

        $(document).on("change", ".sdbpc-gen-cat-check, #sdbpc-gen-default", sdbpcGenUpdate);
        $(document).on("keyup change", "#sdbpc-gen-per-page", sdbpcGenUpdate);

        $("#sdbpc-gen-copy").on("click", function(){
            if($(this).prop("disabled")) return;
            var el = document.getElementById("sdbpc-gen-output");
            el.focus();
            el.select();
            el.setSelectionRange(0, 99999);
            var ok = false;
            try { ok = document.execCommand("copy"); } catch(e){ ok = false; }
            var $msg = $("#sdbpc-gen-copy-msg");
            if(ok){
                $msg.stop(true, true).css("color", "#2e7d32").text("Copied!").show().delay(1500).fadeOut();
            } else {
                $msg.stop(true, true).css("color", "#996600").text("Press Ctrl+C to copy the selected text.").show();
            }
        });

        sdbpcGenUpdate();
    }
});
JS;
	}
}
