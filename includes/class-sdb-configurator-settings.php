<?php
/**
 * Admin settings – unlimited tiers & steps, PHP 7.4 compatible.
 *
 * @package SDB_Product_Configurator
 */

defined( 'ABSPATH' ) || exit;

class SDB_Configurator_Settings {

    const OPTION_KEY = 'sdbpc_settings';

    /* ─────────────────────────────────────────────────────────────────────
     * DEFAULTS
     * ───────────────────────────────────────────────────────────────────── */

    public static function default_tiers() {
        return array(
            array(
                'prefix' => 'K1',
                'title'  => 'Stel zelf je Silent Disco set samen',
                'steps'  => array(
                    array( 'key' => '01', 'label' => 'Hoofdtelefoon' ),
                    array( 'key' => '02', 'label' => 'Zender' ),
                    array( 'key' => '03', 'label' => 'Kabels' ),
                    array( 'key' => '04', 'label' => 'Branding' ),
                    array( 'key' => '05', 'label' => 'Accessoires' ),
                    array( 'key' => '06', 'label' => 'Complete' ),
                ),
            ),
            array(
                'prefix' => 'K2',
                'title'  => 'Stel zelf je Silent Disco set samen',
                'steps'  => array(
                    array( 'key' => '01', 'label' => 'Hoofdtelefoon' ),
                    array( 'key' => '02', 'label' => 'Zender' ),
                    array( 'key' => '03', 'label' => 'Kabels' ),
                    array( 'key' => '04', 'label' => 'Branding' ),
                    array( 'key' => '05', 'label' => 'Accessoires' ),
                    array( 'key' => '06', 'label' => 'Complete' ),
                ),
            ),
            array(
                'prefix' => 'K3',
                'title'  => 'Stel zelf je Silent Disco set samen',
                'steps'  => array(
                    array( 'key' => '01', 'label' => 'Hoofdtelefoon' ),
                    array( 'key' => '02', 'label' => 'Zender' ),
                    array( 'key' => '03', 'label' => 'Kabels' ),
                    array( 'key' => '04', 'label' => 'Branding' ),
                    array( 'key' => '05', 'label' => 'Accessoires' ),
                    array( 'key' => '06', 'label' => 'Complete' ),
                ),
            ),
        );
    }

    public static function default_general() {
        return array(
            'btn_next'        => 'Naar stap %d >',
            'btn_finish'      => 'Voltooien',
            'cart_title'      => 'Winkelwagen',
            'cart_empty'      => 'Winkelwagen is leeg',
            'total_label'     => 'Totaal incl. BTW',
            'no_products_msg' => 'Geen producten beschikbaar voor deze stap.',
        );
    }

    /* ─────────────────────────────────────────────────────────────────────
     * BOOT
     * ───────────────────────────────────────────────────────────────────── */

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init',            array( __CLASS__, 'maybe_save' ) ); // fires early enough
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
    }

    public static function add_menu() {
        add_menu_page(
            __( 'Product Configurator', 'sdb-product-configurator' ),
            __( 'Configurator',         'sdb-product-configurator' ),
            'manage_options',
            'sdb-product-configurator',
            array( __CLASS__, 'render_page' ),
            'dashicons-list-view',
            56
        );
    }

    /* ─────────────────────────────────────────────────────────────────────
     * ADMIN ASSETS
     * ───────────────────────────────────────────────────────────────────── */

    public static function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'sdb-product-configurator' ) === false ) {
            return;
        }
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_add_inline_style( 'wp-admin', self::admin_css() );
        wp_add_inline_script( 'jquery-ui-sortable', self::admin_js() );

        if ( class_exists( 'SDB_Range_Settings' ) ) {
            wp_add_inline_style( 'wp-admin', SDB_Range_Settings::admin_css() );
            SDB_Range_Settings::enqueue_admin();
        }
    }

    /* ─────────────────────────────────────────────────────────────────────
     * SAVE HANDLER  (called from admin_init — fires before any output)
     * ───────────────────────────────────────────────────────────────────── */

    public static function maybe_save() {
        // Only act when our form was submitted
        if ( ! isset( $_POST['sdbpc_do_save'] ) ) {
            return;
        }
        self::handle_save();
    }

    public static function handle_save() {
        // Permission check
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to do this.', 'sdb-product-configurator' ) );
        }

        // Nonce check
        $nonce = isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'sdbpc_save_settings' ) ) {
            wp_die( __( 'Security check failed. Please go back and try again.', 'sdb-product-configurator' ) );
        }

        $input    = ( isset( $_POST['sdbpc'] ) && is_array( $_POST['sdbpc'] ) ) ? $_POST['sdbpc'] : array();
        $existing = get_option( self::OPTION_KEY, array() );
        $tab      = isset( $_POST['_tab'] ) ? sanitize_key( $_POST['_tab'] ) : 'general';

        /* ── General ── */
        if ( $tab === 'general' ) {
            $defaults = self::default_general();
            foreach ( $defaults as $k => $def ) {
                $existing[ $k ] = sanitize_text_field( isset( $input[ $k ] ) ? $input[ $k ] : $def );
            }
        }

        /* ── Tiers ── */
        if ( $tab === 'tiers' ) {
            $raw_tiers = ( isset( $input['tiers'] ) && is_array( $input['tiers'] ) ) ? $input['tiers'] : array();
            $tiers     = array();

            foreach ( $raw_tiers as $tier_data ) {
                $raw_prefix = isset( $tier_data['prefix'] ) ? $tier_data['prefix'] : '';
                $prefix     = strtoupper( preg_replace( '/[^a-zA-Z0-9\-_]/', '', sanitize_text_field( $raw_prefix ) ) );
                $title      = sanitize_text_field( isset( $tier_data['title'] ) ? $tier_data['title'] : '' );

                if ( $prefix === '' ) {
                    continue;
                }

                $steps = array();
                if ( isset( $tier_data['steps'] ) && is_array( $tier_data['steps'] ) ) {
                    foreach ( $tier_data['steps'] as $step_data ) {
                        $key   = preg_replace( '/[^a-z0-9\-]/', '', strtolower( sanitize_text_field( isset( $step_data['key'] ) ? $step_data['key'] : '' ) ) );
                        $label = sanitize_text_field( isset( $step_data['label'] ) ? $step_data['label'] : '' );
                        if ( $key !== '' && $label !== '' ) {
                            $type = sanitize_key( isset( $step_data['type'] ) ? $step_data['type'] : 'product' );
                            if ( ! in_array( $type, array( 'product', 'booking' ), true ) ) {
                                $type = 'product';
                            }
                            $saved_step = array( 'key' => $key, 'label' => $label, 'type' => $type );
                            if ( $type === 'booking' ) {
                                $saved_step['booking_product_id'] = absint( isset( $step_data['booking_product_id'] ) ? $step_data['booking_product_id'] : 0 );
                                $saved_step['booking_min_days']   = absint( isset( $step_data['booking_min_days'] )   ? $step_data['booking_min_days']   : 3 );
                                $saved_step['booking_show_end'] = ( isset( $step_data['booking_show_end'] ) && $step_data['booking_show_end'] === '1' ) ? '1' : '0';
                            }
                            $steps[] = $saved_step;
                        }
                    }
                }

                if ( empty( $steps ) ) {
                    continue;
                }

                $tiers[] = array(
                    'prefix' => $prefix,
                    'title'  => $title,
                    'steps'  => $steps,
                );
            }

            $existing['tiers'] = ! empty( $tiers ) ? $tiers : self::default_tiers();
        }

        /* ── Product Range ── */
        if ( $tab === 'ranges' && class_exists( 'SDB_Range_Settings' ) ) {
            $existing = SDB_Range_Settings::sanitize_and_merge( $input, $existing );
        }

        update_option( self::OPTION_KEY, $existing );

        // Redirect back to settings page with success flag
        wp_safe_redirect( admin_url( 'admin.php?page=sdb-product-configurator&saved=1&tab=' . $tab ) );
        exit;
    }

    /* ─────────────────────────────────────────────────────────────────────
     * RENDER PAGE
     * ───────────────────────────────────────────────────────────────────── */

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $s     = get_option( self::OPTION_KEY, array() );
        $tiers = ( ! empty( $s['tiers'] ) ) ? $s['tiers'] : self::default_tiers();
        $gen   = array_merge( self::default_general(), $s );
        $saved = isset( $_GET['saved'] );
        $tab   = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $base  = admin_url( 'admin.php?page=sdb-product-configurator' );

        $tabs = array(
            'general' => 'General',
            'tiers'   => 'Tiers & Steps',
            'ranges'  => 'Product Range',
            'howto'   => 'How To Use',
        );
        ?>
        <div class="wrap sdbpc-admin">
            <h1 class="sdbpc-page-title">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e( 'SDB Product Configurator', 'sdb-product-configurator' ); ?>
            </h1>

            <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Settings saved successfully.', 'sdb-product-configurator' ); ?></p>
            </div>
            <?php endif; ?>

            <nav class="sdbpc-tabs">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                <a href="<?php echo esc_url( $base . '&tab=' . $slug ); ?>"
                   class="sdbpc-tab <?php echo ( $tab === $slug ) ? 'active' : ''; ?>">
                    <?php echo esc_html( $label ); ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sdb-product-configurator' ) ); ?>">
                <input type="hidden" name="sdbpc_do_save" value="1">
                <input type="hidden" name="_tab"          value="<?php echo esc_attr( $tab ); ?>">
                <?php wp_nonce_field( 'sdbpc_save_settings' ); ?>

                <?php
                if ( $tab === 'general' ) {
                    self::tab_general( $gen );
                } elseif ( $tab === 'tiers' ) {
                    self::tab_tiers( $tiers );
                } elseif ( $tab === 'ranges' && class_exists( 'SDB_Range_Settings' ) ) {
                    SDB_Range_Settings::render_tab();
                } elseif ( $tab === 'howto' ) {
                    self::tab_howto( $tiers );
                }
                ?>

                <?php if ( $tab !== 'howto' ) : ?>
                <div style="margin-top:24px;">
                    <?php submit_button( __( 'Save Settings', 'sdb-product-configurator' ), 'primary large', 'submit', false ); ?>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    /* ─────────────────────────────────────────────────────────────────────
     * TAB: GENERAL
     * ───────────────────────────────────────────────────────────────────── */

    private static function tab_general( $g ) {
        $rows = array(
            'btn_next'        => array( 'Next step button',          'Use %d as placeholder for the step number.' ),
            'btn_finish'      => array( 'Finish button (last step)',  '' ),
            'cart_title'      => array( 'Cart sidebar title',         '' ),
            'cart_empty'      => array( 'Empty cart message',         '' ),
            'total_label'     => array( 'Total label',                '' ),
            'no_products_msg' => array( 'No products message',        '' ),
        );
        ?>
        <div class="sdbpc-card">
            <h2><?php esc_html_e( 'Labels & Messages', 'sdb-product-configurator' ); ?></h2>
            <p class="sdbpc-desc"><?php esc_html_e( 'All text shown in the configurator popup. Changes here reflect immediately on the frontend.', 'sdb-product-configurator' ); ?></p>
            <table class="sdbpc-table">
                <?php foreach ( $rows as $key => $info ) :
                    $label = $info[0];
                    $desc  = $info[1];
                    $value = isset( $g[ $key ] ) ? $g[ $key ] : '';
                ?>
                <tr>
                    <th><?php echo esc_html( $label ); ?></th>
                    <td>
                        <input type="text"
                               name="sdbpc[<?php echo esc_attr( $key ); ?>]"
                               value="<?php echo esc_attr( $value ); ?>"
                               class="regular-text">
                        <?php if ( $desc ) : ?>
                        <p class="description"><?php echo esc_html( $desc ); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    /* ─────────────────────────────────────────────────────────────────────
     * TAB: TIERS & STEPS
     * ───────────────────────────────────────────────────────────────────── */

    private static function tab_tiers( $tiers ) {
        ?>
        <div class="sdbpc-card">
            <div class="sdbpc-tiers-topbar">
                <div>
                    <h2><?php esc_html_e( 'Tiers & Steps', 'sdb-product-configurator' ); ?></h2>
                    <p class="sdbpc-desc">
                        <?php esc_html_e( 'Each tier = one configurator popup triggered by data-configurator="PREFIX". Add unlimited tiers and steps. Drag ≡ to reorder.', 'sdb-product-configurator' ); ?>
                    </p>
                </div>
                <button type="button" id="sdbpc-add-tier" class="button button-primary">
                    + <?php esc_html_e( 'Add Tier', 'sdb-product-configurator' ); ?>
                </button>
            </div>

            <div id="sdbpc-tiers-list">
                <?php foreach ( $tiers as $ti => $tier ) : ?>
                <?php self::render_tier_block( $ti, $tier ); ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Templates for JS cloning -->
        <script type="text/template" id="sdbpc-tier-template">
        <?php self::render_tier_block( '__TI__', array( 'prefix' => '', 'title' => '', 'steps' => array() ), true ); ?>
        </script>
        <script type="text/template" id="sdbpc-step-template">
        <?php self::render_step_row( '__TI__', '__SI__', array( 'key' => '', 'label' => '' ), true ); ?>
        </script>
        <?php
    }

    private static function render_tier_block( $ti, $tier, $template = false ) {
        $prefix = $template ? '' : esc_attr( isset( $tier['prefix'] ) ? $tier['prefix'] : '' );
        $title  = $template ? '' : esc_attr( isset( $tier['title'] )  ? $tier['title']  : '' );
        $steps  = $template ? array() : ( isset( $tier['steps'] ) ? $tier['steps'] : array() );
        ?>
        <div class="sdbpc-tier-block" data-tier-index="<?php echo esc_attr( $ti ); ?>">
            <div class="sdbpc-tier-header">
                <span class="dashicons dashicons-menu sdbpc-tier-drag" title="Drag to reorder"></span>
                <div class="sdbpc-tier-fields">
                    <div class="sdbpc-tier-field-group">
                        <label><?php esc_html_e( 'Prefix', 'sdb-product-configurator' ); ?> <small>(K1, WK, KT…)</small></label>
                        <input type="text"
                               name="sdbpc[tiers][<?php echo $ti; ?>][prefix]"
                               value="<?php echo $prefix; ?>"
                               placeholder="K1"
                               class="sdbpc-tier-prefix small-text"
                               maxlength="20">
                    </div>
                    <div class="sdbpc-tier-field-group sdbpc-tier-title-group">
                        <label><?php esc_html_e( 'Modal title', 'sdb-product-configurator' ); ?></label>
                        <input type="text"
                               name="sdbpc[tiers][<?php echo $ti; ?>][title]"
                               value="<?php echo $title; ?>"
                               placeholder="<?php esc_attr_e( 'Configure your set', 'sdb-product-configurator' ); ?>"
                               class="regular-text">
                    </div>
                    <div class="sdbpc-tier-field-group">
                        <label>&nbsp;</label>
                        <button type="button" class="button sdbpc-remove-tier">
                            <?php esc_html_e( '✕ Remove Tier', 'sdb-product-configurator' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="sdbpc-steps-area">
                <div class="sdbpc-steps-cols">
                    <span></span>
                    <span>#</span>
                    <span><?php esc_html_e( 'Key', 'sdb-product-configurator' ); ?></span>
                    <span><?php esc_html_e( 'Label', 'sdb-product-configurator' ); ?></span>
                    <span><?php esc_html_e( 'Type', 'sdb-product-configurator' ); ?></span>
                    <span></span>
                </div>
                <div class="sdbpc-steps-list" data-tier="<?php echo esc_attr( $ti ); ?>">
                    <?php foreach ( $steps as $si => $step ) : ?>
                    <?php self::render_step_row( $ti, $si, $step ); ?>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button button-secondary sdbpc-add-step" data-tier="<?php echo esc_attr( $ti ); ?>" style="margin-top:10px;">
                    + <?php esc_html_e( 'Add Step', 'sdb-product-configurator' ); ?>
                </button>
                <p class="sdbpc-steps-hint">
                    <?php
                    echo sprintf(
                        /* translators: %s = example slug */
                        esc_html__( 'Example: prefix "%1$s" + key "01" → WooCommerce category slug: %2$s', 'sdb-product-configurator' ),
                        esc_html( $prefix ? $prefix : 'K1' ),
                        '<code>' . esc_html( strtolower( ( $prefix ? $prefix : 'k1' ) . '-01' ) ) . '</code>'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }

    private static function render_step_row( $ti, $si, $step, $template = false ) {
        $key        = $template ? '' : esc_attr( isset( $step['key'] )               ? $step['key']               : '' );
        $label      = $template ? '' : esc_attr( isset( $step['label'] )             ? $step['label']             : '' );
        $type       = $template ? 'product' : ( isset( $step['type'] )               ? $step['type']              : 'product' );
        $prod_id    = $template ? '' : esc_attr( isset( $step['booking_product_id'] ) ? $step['booking_product_id'] : '' );
        $min_days   = $template ? '3' : esc_attr( isset( $step['booking_min_days'] )  ? $step['booking_min_days']  : '3' );
        $show_end   = $template ? '' : ( isset( $step['booking_show_end'] ) && $step['booking_show_end'] ? 'checked' : '' );
        $num        = is_numeric( $si ) ? $si + 1 : '?';
        $is_booking = ( $type === 'booking' );
        $booking_style = $is_booking ? '' : 'display:none';
        ?>
        <div class="sdbpc-step-row">
            <span class="sdbpc-drag-handle dashicons dashicons-menu"></span>
            <span class="sdbpc-step-num"><?php echo $num; ?></span>
            <input type="text"
                   name="sdbpc[tiers][<?php echo $ti; ?>][steps][<?php echo $si; ?>][key]"
                   value="<?php echo $key; ?>"
                   placeholder="01"
                   class="sdbpc-step-key small-text"
                   maxlength="10">
            <input type="text"
                   name="sdbpc[tiers][<?php echo $ti; ?>][steps][<?php echo $si; ?>][label]"
                   value="<?php echo $label; ?>"
                   placeholder="<?php esc_attr_e( 'Step label', 'sdb-product-configurator' ); ?>"
                   class="sdbpc-step-label regular-text">
            <select name="sdbpc[tiers][<?php echo $ti; ?>][steps][<?php echo $si; ?>][type]"
                    class="sdbpc-step-type">
                <option value="product"  <?php selected( $type, 'product' ); ?>><?php esc_html_e( 'Products',  'sdb-product-configurator' ); ?></option>
                <option value="booking"  <?php selected( $type, 'booking' ); ?>><?php esc_html_e( 'Booking',   'sdb-product-configurator' ); ?></option>
            </select>
            <button type="button" class="button sdbpc-remove-step">&#x2715;</button>

            <!-- Booking options – shown only when type = booking -->
            <div class="sdbpc-booking-options" style="<?php echo $booking_style; ?>">
                <div class="sdbpc-booking-opt-row">
                    <label><?php esc_html_e( 'WooCommerce Product ID', 'sdb-product-configurator' ); ?></label>
                    <input type="number"
                           name="sdbpc[tiers][<?php echo $ti; ?>][steps][<?php echo $si; ?>][booking_product_id]"
                           value="<?php echo $prod_id; ?>"
                           placeholder="e.g. 2503"
                           class="small-text">
                    <span class="description"><?php esc_html_e( 'The booking/rental product ID.', 'sdb-product-configurator' ); ?></span>
                </div>
                <div class="sdbpc-booking-opt-row">
                    <label><?php esc_html_e( 'Minimum days from today', 'sdb-product-configurator' ); ?></label>
                    <input type="number"
                           name="sdbpc[tiers][<?php echo $ti; ?>][steps][<?php echo $si; ?>][booking_min_days]"
                           value="<?php echo $min_days; ?>"
                           min="0"
                           class="small-text">
                </div>
                <div class="sdbpc-booking-opt-row">
                    <label>
                        <input type="hidden"
                               name="sdbpc[tiers][<?php echo $ti; ?>][steps][<?php echo $si; ?>][booking_show_end]"
                               value="0">
                        <input type="checkbox"
                               name="sdbpc[tiers][<?php echo $ti; ?>][steps][<?php echo $si; ?>][booking_show_end]"
                               value="1"
                               <?php echo $show_end; ?>>
                        <?php esc_html_e( 'Show end date picker', 'sdb-product-configurator' ); ?>
                    </label>
                </div>
            </div>
        </div>
        <?php
    }

    /* ─────────────────────────────────────────────────────────────────────
     * TAB: HOW TO USE
     * ───────────────────────────────────────────────────────────────────── */

    private static function tab_howto( $tiers ) {
        ?>
        <div class="sdbpc-card">
            <h2><?php esc_html_e( 'Product Range shortcode', 'sdb-product-configurator' ); ?></h2>
            <p class="sdbpc-desc"><?php esc_html_e( 'Drop this on any page to show the category-icon row and paginated product grid. Configure its icons on the "Product Range" tab.', 'sdb-product-configurator' ); ?></p>
            <pre class="sdbpc-code">[sdb_product_range]</pre>
        </div>

        <div class="sdbpc-card">
            <h2><?php esc_html_e( 'Trigger buttons', 'sdb-product-configurator' ); ?></h2>
            <p class="sdbpc-desc"><?php esc_html_e( 'Add data-configurator="PREFIX" to any button or element. Use the prefix you set in Tiers & Steps.', 'sdb-product-configurator' ); ?></p>
            <table class="sdbpc-table" style="max-width:600px">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Title', 'sdb-product-configurator' ); ?></th>
                        <th><?php esc_html_e( 'Prefix', 'sdb-product-configurator' ); ?></th>
                        <th><?php esc_html_e( 'HTML attribute', 'sdb-product-configurator' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tiers as $tier ) :
                        $prefix = isset( $tier['prefix'] ) ? $tier['prefix'] : '';
                        $title  = isset( $tier['title'] )  ? $tier['title']  : $prefix;
                    ?>
                    <tr>
                        <td><?php echo esc_html( $title ); ?></td>
                        <td><strong><?php echo esc_html( $prefix ); ?></strong></td>
                        <td><code>data-configurator="<?php echo esc_html( $prefix ); ?>"</code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <pre class="sdbpc-code"><?php
                foreach ( $tiers as $tier ) {
                    $p = isset( $tier['prefix'] ) ? $tier['prefix'] : '';
                    $t = isset( $tier['title'] )  ? $tier['title']  : $p;
                    echo esc_html( '<button data-configurator="' . $p . '">' . $t . '</button>' ) . "\n";
                }
            ?></pre>
        </div>

        <div class="sdbpc-card">
            <h2><?php esc_html_e( 'WooCommerce category slug reference', 'sdb-product-configurator' ); ?></h2>
            <p class="sdbpc-desc"><?php esc_html_e( 'Create these category slugs in Products → Categories. WordPress auto-lowercases slugs on save.', 'sdb-product-configurator' ); ?></p>
            <table class="sdbpc-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Prefix', 'sdb-product-configurator' ); ?></th>
                        <th><?php esc_html_e( 'Step key', 'sdb-product-configurator' ); ?></th>
                        <th><?php esc_html_e( 'WC category slug', 'sdb-product-configurator' ); ?></th>
                        <th><?php esc_html_e( 'Label', 'sdb-product-configurator' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tiers as $tier ) :
                        $prefix = isset( $tier['prefix'] ) ? $tier['prefix'] : '';
                        $steps  = isset( $tier['steps'] )  ? $tier['steps']  : array();
                        foreach ( $steps as $step ) :
                            $key   = isset( $step['key'] )   ? $step['key']   : '';
                            $lbl   = isset( $step['label'] ) ? $step['label'] : '';
                            $slug  = strtolower( $prefix . '-' . $key );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $prefix ); ?></td>
                        <td><?php echo esc_html( $key ); ?></td>
                        <td><code><?php echo esc_html( $slug ); ?></code></td>
                        <td><?php echo esc_html( $lbl ); ?></td>
                    </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sdbpc-card">
            <h2><?php esc_html_e( 'Custom field reference', 'sdb-product-configurator' ); ?></h2>
            <table class="sdbpc-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Custom field key', 'sdb-product-configurator' ); ?></th>
                        <th><?php esc_html_e( 'What it does', 'sdb-product-configurator' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tiers as $tier ) :
                        $prefix = isset( $tier['prefix'] ) ? $tier['prefix'] : '';
                    ?>
                    <tr>
                        <td><code><?php echo esc_html( $prefix ); ?>_qty_value</code></td>
                        <td><?php printf( esc_html__( 'Pre-selected quantity for %s tier', 'sdb-product-configurator' ), esc_html( $prefix ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><code>sdbpc_sort_order</code></td>
                        <td><?php esc_html_e( 'Display order — lower number = shown first', 'sdb-product-configurator' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* ─────────────────────────────────────────────────────────────────────
     * PUBLIC HELPERS  (used by main plugin class & AJAX)
     * ───────────────────────────────────────────────────────────────────── */

    public static function get_tiers() {
        $s = get_option( self::OPTION_KEY, array() );
        return ( ! empty( $s['tiers'] ) ) ? $s['tiers'] : self::default_tiers();
    }

    public static function get( $key, $default = '' ) {
        $s = get_option( self::OPTION_KEY, array() );
        return ( isset( $s[ $key ] ) && $s[ $key ] !== '' ) ? $s[ $key ] : $default;
    }

    /* ─────────────────────────────────────────────────────────────────────
     * INLINE CSS
     * ───────────────────────────────────────────────────────────────────── */

    private static function admin_css() {
        return '
        .sdbpc-admin{max-width:980px}
        .sdbpc-page-title{display:flex;align-items:center;gap:10px;margin-bottom:20px}
        .sdbpc-page-title .dashicons{font-size:26px;width:26px;height:26px;color:#667eea}
        .sdbpc-tabs{display:flex;border-bottom:2px solid #ddd;margin-bottom:24px}
        .sdbpc-tab{padding:10px 22px;text-decoration:none;color:#555;font-weight:500;border-bottom:3px solid transparent;margin-bottom:-2px;display:inline-block}
        .sdbpc-tab:hover{color:#667eea}
        .sdbpc-tab.active{color:#667eea;border-bottom-color:#667eea}
        .sdbpc-card{background:#fff;border:1px solid #ddd;border-radius:6px;padding:24px 28px;margin-bottom:20px}
        .sdbpc-card h2{margin:0 0 6px;font-size:1.05rem}
        .sdbpc-desc{color:#666;font-size:13px;margin:0 0 16px}
        .sdbpc-table{width:100%;border-collapse:collapse}
        .sdbpc-table th{text-align:left;padding:9px 12px 9px 0;color:#444;font-weight:500;width:220px;vertical-align:middle}
        .sdbpc-table td{padding:7px 0;vertical-align:middle}
        .sdbpc-table thead th{border-bottom:1px solid #e0e0e0;width:auto}
        .sdbpc-table code{background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:12px}
        .sdbpc-tiers-topbar{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
        .sdbpc-tier-block{border:1px solid #d0d0d0;border-radius:6px;margin-bottom:20px;overflow:hidden}
        .sdbpc-tier-header{background:#f7f7f7;padding:14px 16px;border-bottom:1px solid #e0e0e0;display:flex;align-items:flex-start;gap:10px}
        .sdbpc-tier-drag{color:#aaa;cursor:grab;font-size:20px;margin-top:26px;flex-shrink:0}
        .sdbpc-tier-drag:hover{color:#667eea}
        .sdbpc-tier-fields{display:flex;gap:16px;flex-wrap:wrap;flex:1;align-items:flex-end}
        .sdbpc-tier-field-group{display:flex;flex-direction:column;gap:4px}
        .sdbpc-tier-field-group label{font-size:12px;color:#555;font-weight:500}
        .sdbpc-tier-title-group{flex:1;min-width:260px}
        .sdbpc-tier-prefix{width:90px!important}
        .sdbpc-remove-tier{color:#c0392b!important;border-color:#e0b0b0!important}
        .sdbpc-remove-tier:hover{background:#c0392b!important;color:#fff!important;border-color:#c0392b!important}
        .sdbpc-steps-area{padding:14px 16px}
        .sdbpc-steps-cols{display:grid;grid-template-columns:24px 24px 110px 1fr 90px 40px;gap:8px;font-size:11px;color:#888;font-weight:600;padding-bottom:6px;border-bottom:1px solid #eee;margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em}
        .sdbpc-step-row{display:grid;grid-template-columns:24px 24px 110px 1fr 90px 40px;gap:8px;align-items:center;padding:5px 0;border-bottom:1px solid #f5f5f5;flex-wrap:wrap}
        .sdbpc-step-row:last-child{border-bottom:none}
        .sdbpc-step-row:hover{background:#fafafa}
        .sdbpc-booking-options{grid-column:1/-1;background:#f0f4ff;border-left:3px solid #667eea;border-radius:4px;padding:10px 14px;margin:4px 0 8px;display:flex;flex-wrap:wrap;gap:12px}
        .sdbpc-booking-opt-row{display:flex;align-items:center;gap:8px;font-size:13px}
        .sdbpc-booking-opt-row label{font-weight:500;color:#444;min-width:180px}
        .sdbpc-booking-opt-row .description{color:#888;font-size:12px}
        .sdbpc-drag-handle{cursor:grab;color:#ccc;font-size:16px}
        .sdbpc-drag-handle:hover{color:#667eea}
        .sdbpc-step-num{font-size:11px;color:#aaa;text-align:center}
        .sdbpc-step-key{width:90px!important}
        .sdbpc-remove-step{color:#c0392b!important;border-color:#e0b0b0!important;padding:2px 6px!important;font-size:13px!important;min-height:0!important;height:28px!important;line-height:1!important;width:32px!important;text-align:center!important}
        .sdbpc-remove-step:hover{background:#c0392b!important;color:#fff!important;border-color:#c0392b!important}
        .sdbpc-steps-hint{font-size:12px;color:#999;margin:8px 0 0}
        .sdbpc-code{background:#f7f7f7;border:1px solid #e0e0e0;border-radius:4px;padding:12px 16px;font-size:12px;margin-top:12px;line-height:2;overflow-x:auto}
        ';
    }

    /* ─────────────────────────────────────────────────────────────────────
     * INLINE JS
     * ───────────────────────────────────────────────────────────────────── */

    private static function admin_js() {
        return '
jQuery(function($){
    var $tierList = $("#sdbpc-tiers-list");
    if(!$tierList.length) return;

    // Sortable: reorder tiers
    $tierList.sortable({ handle: ".sdbpc-tier-drag", update: reindexAll });

    // Add tier
    $("#sdbpc-add-tier").on("click", function(){
        var tmpl = $("#sdbpc-tier-template").html();
        var ti   = $tierList.find(".sdbpc-tier-block").length;
        tmpl = tmpl.replace(/__TI__/g, String(ti));
        $tierList.append(tmpl);
        var $newTier = $tierList.find(".sdbpc-tier-block").last();
        initStepsSortable( $newTier.find(".sdbpc-steps-list") );
        reindexAll();
    });

    // Remove tier
    $tierList.on("click", ".sdbpc-remove-tier", function(){
        if( $tierList.find(".sdbpc-tier-block").length <= 1 ){
            alert("You need at least one tier.");
            return;
        }
        if( !confirm("Remove this tier and all its steps?") ) return;
        $(this).closest(".sdbpc-tier-block").remove();
        reindexAll();
    });

    // Add step
    $tierList.on("click", ".sdbpc-add-step", function(){
        var $tier  = $(this).closest(".sdbpc-tier-block");
        var ti     = $tierList.find(".sdbpc-tier-block").index( $tier );
        var $steps = $tier.find(".sdbpc-steps-list");
        var si     = $steps.find(".sdbpc-step-row").length;
        var tmpl   = $("#sdbpc-step-template").html();
        tmpl = tmpl.replace(/__TI__/g, String(ti)).replace(/__SI__/g, String(si));
        $steps.append(tmpl);
        reindexSteps( $steps, ti );
    });

    // Remove step
    $tierList.on("click", ".sdbpc-remove-step", function(){
        var $steps = $(this).closest(".sdbpc-steps-list");
        var ti     = $tierList.find(".sdbpc-tier-block").index( $(this).closest(".sdbpc-tier-block") );
        $(this).closest(".sdbpc-step-row").remove();
        reindexSteps( $steps, ti );
    });

    // Show/hide booking options when step type changes
    $tierList.on("change", ".sdbpc-step-type", function(){
        var $opts = $(this).closest(".sdbpc-step-row").find(".sdbpc-booking-options");
        if($(this).val() === "booking"){ $opts.show(); } else { $opts.hide(); }
    });

    function initStepsSortable( $list ){
        $list.sortable({
            handle: ".sdbpc-drag-handle",
            update: function(){
                var ti = $tierList.find(".sdbpc-tier-block").index( $(this).closest(".sdbpc-tier-block") );
                reindexSteps( $(this), ti );
            }
        });
    }

    function reindexAll(){
        $tierList.find(".sdbpc-tier-block").each(function(ti){
            $(this).attr("data-tier-index", ti);
            // reindex tier-level inputs
            $(this).find(".sdbpc-tier-header input").each(function(){
                var n = $(this).attr("name");
                if(n) $(this).attr("name", n.replace(/\[tiers\]\[\d+\]/, "[tiers]["+ti+"]") );
            });
            reindexSteps( $(this).find(".sdbpc-steps-list"), ti );
        });
    }

    function reindexSteps( $steps, ti ){
        $steps.find(".sdbpc-step-row").each(function(si){
            $(this).find(".sdbpc-step-num").text(si+1);
            $(this).find("input").each(function(){
                var n = $(this).attr("name");
                if(n){
                    n = n.replace(/\[tiers\]\[\d+\]/, "[tiers]["+ti+"]");
                    n = n.replace(/\[steps\]\[\d+\]/, "[steps]["+si+"]");
                    $(this).attr("name", n);
                }
            });
        });
    }
});
        ';
    }
}
