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
	 */
	public static function get_default_term_id() {
		$s = self::get_option_data();

		if ( ! empty( $s['range_default_term'] ) ) {
			foreach ( self::get_ranges() as $range ) {
				if ( ! empty( $range['term_id'] ) && (int) $range['term_id'] === (int) $s['range_default_term'] ) {
					return absint( $s['range_default_term'] );
				}
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
		';
	}

	public static function admin_js() {
		return <<<'JS'
jQuery(function($){
    var $rows = $("#sdbpc-range-rows");
    if(!$rows.length) return;

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
});
JS;
	}
}
