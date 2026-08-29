<?php
/**
 * Front-end markup for [sdb_product_range].
 *
 * Expects (from SDB_Product_Range::render_shortcode):
 * @var array  $ranges           configured icon rows
 * @var int    $per_page
 * @var int    $default_term_id
 * @var string $instance_id
 * @var array|null $initial      first page of products for $default_term_id, or null
 *
 * @package SDB_Product_Configurator
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="sdbpr-wrap" id="<?php echo esc_attr( $instance_id ); ?>" data-per-page="<?php echo esc_attr( $per_page ); ?>" data-active-term="<?php echo esc_attr( $default_term_id ); ?>">

	<div class="sdbpr-cats-wrap">
		<div class="sdbpr-cats" role="tablist">
			<?php foreach ( $ranges as $row ) :
				$icon      = SDB_Product_Range::resolve_icon_url( $row );
				$label     = SDB_Product_Range::resolve_label( $row );
				$is_active = ( (int) $row['term_id'] === (int) $default_term_id );
				?>
				<button type="button"
						class="sdbpr-cat-item<?php echo $is_active ? ' sdbpr-active' : ''; ?>"
						data-type="category"
						data-term-id="<?php echo esc_attr( $row['term_id'] ); ?>"
						role="tab"
						aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
					<span class="sdbpr-cat-icon"><?php if ( $icon ) : ?><img src="<?php echo esc_url( $icon ); ?>" alt="" loading="lazy"><?php endif; ?></span>
					<span class="sdbpr-cat-label"><?php echo esc_html( $label ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>

		<button type="button" class="sdbpr-reset-filter" data-default-term="<?php echo esc_attr( $default_term_id ); ?>">
			<?php esc_html_e( 'Reset filter', 'sdb-product-configurator' ); ?>
		</button>
	</div>

	<div class="sdbpr-products" aria-live="polite">
		<?php if ( $initial && ! empty( $initial['products'] ) ) : ?>
			<?php foreach ( $initial['products'] as $product ) : ?>
				<?php echo SDB_Product_Range::render_product_card_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		<?php elseif ( $initial ) : ?>
			<div class="sdbpr-empty"><?php esc_html_e( 'No products found in this category.', 'sdb-product-configurator' ); ?></div>
		<?php else : ?>
			<div class="sdbpr-loading"><span class="sdbpr-spinner"></span></div>
		<?php endif; ?>
	</div>

	<div class="sdbpr-pagination-wrap">
		<?php if ( $initial ) : ?>
			<?php echo SDB_Product_Range::render_pagination_html( $initial['current_page'], $initial['max_pages'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>

</div>
