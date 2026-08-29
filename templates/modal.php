<?php
/**
 * Configurator modal template.
 *
 * Rendered once in wp_footer via SDB_Product_Configurator::render_modal().
 * All dynamic data is passed through  window.sdbpcConfig  (see class-sdb-product-configurator.php).
 *
 * @package SDB_Product_Configurator
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="sdbpkp-modal-overlay" id="sdbpkpConfiguratorModal" role="dialog" aria-modal="true" aria-labelledby="sdbpkpModalTitle">
    <div class="sdbpkp-modal-container">

        <!-- Close button -->
        <button class="sdbpkp-close-modal" id="sdbpkpCloseModal" aria-label="<?php esc_attr_e( 'Sluiten', 'sdb-product-configurator' ); ?>">&#x2715;</button>

        <!-- Header -->
        <div class="sdbpkp-header">
            <div class="sdbpkp-modal-title" id="sdbpkpModalTitle"></div>
            <button class="sdbpkp-next-btn" id="sdbpkpNextBtnTop"><?php esc_html_e( 'Voltooien', 'sdb-product-configurator' ); ?></button>
        </div>

        <!-- Progress Stepper (built dynamically by JS) -->
        <div class="sdbpkp-stepper" id="sdbpkpStepper" role="tablist"></div>

        <!-- Main Content -->
        <div class="sdbpkp-content">
           
            <!-- Products -->
            <div class="sdbpkp-products-section" id="sdbpkpProductsSection">
                <div class="sdbpkp-loading"><?php esc_html_e( 'Laden…', 'sdb-product-configurator' ); ?></div>
            </div>

            <!-- Cart sidebar -->
            <div class="sdbpkp-cart-section">
                <div class="sdbpkp-cart-title"><?php esc_html_e( 'Winkelwagen', 'sdb-product-configurator' ); ?></div>
                <div class="sdbpkp-cart-items" id="sdbpkpCartItems">
                    <div class="sdbpkp-cart-item"><?php esc_html_e( 'Winkelwagen is leeg', 'sdb-product-configurator' ); ?></div>
                </div>
                <div class="sdbpkp-cart-subtotal" id="sdbpkpCartSubtotal" style="display:none;"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?>0,00</div>
                <div class="sdbpkp-cart-total">
                    <div class="sdbpkp-cart-total-row">
                        <div class="sdbpkp-cart-total-label"><?php esc_html_e( 'Totaal incl. BTW', 'sdb-product-configurator' ); ?></div>
                        <div class="sdbpkp-cart-total-price" id="sdbpkpCartTotal"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?>0,00</div>
                    </div>
                </div>
            </div>
        </div><!-- .sdbpkp-content -->

        <!-- Footer -->
        <div class="sdbpkp-footer">
            <button class="sdbpkp-next-btn" id="sdbpkpNextBtnBottom"><?php esc_html_e( 'Voltooien', 'sdb-product-configurator' ); ?></button>
        </div>

    </div><!-- .sdbpkp-modal-container -->
</div><!-- .sdbpkp-modal-overlay -->
