<?php
/*
Plugin Name: WPC Share Cart for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Share Cart is a simple but powerful tool that can help your customer share their cart.
Version: 1.2.5
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-share-cart
Domain Path: /languages/
Requires at least: 4.0
Tested up to: 6.3
WC requires at least: 3.0
WC tested up to: 7.9
*/

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

! defined( 'WPCSS_VERSION' ) && define( 'WPCSS_VERSION', '1.2.5' );
! defined( 'WPCSS_FILE' ) && define( 'WPCSS_FILE', __FILE__ );
! defined( 'WPCSS_URI' ) && define( 'WPCSS_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCSS_REVIEWS' ) && define( 'WPCSS_REVIEWS', 'https://wordpress.org/support/plugin/wpc-share-cart/reviews/?filter=5' );
! defined( 'WPCSS_CHANGELOG' ) && define( 'WPCSS_CHANGELOG', 'https://wordpress.org/plugins/wpc-share-cart/#developers' );
! defined( 'WPCSS_DISCUSSION' ) && define( 'WPCSS_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-share-cart' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCSS_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';

if ( ! function_exists( 'wpcss_init' ) ) {
	add_action( 'plugins_loaded', 'wpcss_init', 11 );

	function wpcss_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-share-cart', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcss_notice_wc' );

			return;
		}

		if ( ! class_exists( 'WPCleverWpcss' ) ) {
			class WPCleverWpcss {
				protected static $settings = [];
				protected static $localization = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings     = (array) get_option( 'wpcss_settings', [] );
					self::$localization = (array) get_option( 'wpcss_localization', [] );

					// add query var
					add_filter( 'query_vars', [ $this, 'query_vars' ], 1 );

					add_action( 'init', [ $this, 'init' ] );

					// add products from share cart
					add_action( 'wp', [ $this, 'add_products' ] );

					// settings
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// frontend scripts
					add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );

					// backend scripts
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 99 );

					// link
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// share button on cart page
					add_action( 'woocommerce_cart_actions', [ $this, 'share_button' ] );

					// ajax share
					add_action( 'wp_ajax_wpcss_share', [ $this, 'ajax_share' ] );
					add_action( 'wp_ajax_nopriv_wpcss_share', [ $this, 'ajax_share' ] );

					// footer
					add_action( 'wp_footer', [ $this, 'footer' ] );

					// HPOS compatibility
					add_action( 'before_woocommerce_init', function () {
						if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
							FeaturesUtil::declare_compatibility( 'custom_order_tables', WPCSS_FILE );
						}
					} );
				}

				function query_vars( $vars ) {
					$vars[] = 'wpcss_id';

					return $vars;
				}

				function init() {
					// add page
					$wpcss_page = get_page_by_path( 'share-cart', OBJECT );

					if ( empty( $wpcss_page ) ) {
						$wpcss_page_data = [
							'post_status'    => 'publish',
							'post_type'      => 'page',
							'post_author'    => 1,
							'post_name'      => 'share-cart',
							'post_title'     => esc_html__( 'Share Cart', 'wpc-share-cart' ),
							'post_content'   => '[wpcss_list]',
							'post_parent'    => 0,
							'comment_status' => 'closed'
						];
						$wpcss_page_id   = wp_insert_post( $wpcss_page_data );

						update_option( 'wpcss_page_id', $wpcss_page_id );
					}

					// rewrite
					if ( $page_id = self::get_page_id() ) {
						$page_slug = get_post_field( 'post_name', $page_id );

						if ( $page_slug !== '' ) {
							add_rewrite_rule( '^' . $page_slug . '/([\w]+)/?', 'index.php?page_id=' . $page_id . '&wpcss_id=$matches[1]', 'top' );
						}
					}

					// shortcode
					add_shortcode( 'wpcss_list', [ $this, 'list_shortcode' ] );
				}

				public static function get_settings() {
					return apply_filters( 'wpcss_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( 'wpcss_' . $name, $default );
					}

					return apply_filters( 'wpcss_get_setting', $setting, $name, $default );
				}

				public static function localization( $key = '', $default = '' ) {
					$str = '';

					if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
						$str = self::$localization[ $key ];
					} elseif ( ! empty( $default ) ) {
						$str = $default;
					}

					return apply_filters( 'wpcss_localization_' . $key, $str );
				}

				function add_products() {
					if ( isset( $_POST['wpcss-action'], $_POST['wpcss-key'], $_POST['wpcss-nonce'] ) ) {
						if ( ! wp_verify_nonce( sanitize_key( $_POST['wpcss-nonce'] ), 'wpcss_add_products' ) ) {
							print 'Permissions check failed.';
							exit;
						}

						$action           = sanitize_key( $_POST['wpcss-action'] );
						$saved_cart       = self::get_setting( 'cart_' . sanitize_key( $_POST['wpcss-key'] ) );
						$saved_cart_items = $saved_cart['cart'];

						switch ( $action ) {
							case 'selected':
								$selected_products = isset( $_POST['wpcss-products'] ) ? self::sanitize_array( $_POST['wpcss-products'] ) : [];

								if ( ! empty( $selected_products ) && ! empty( $saved_cart_items ) ) {
									foreach ( $selected_products as $selected_product ) {
										if ( isset( $saved_cart_items[ $selected_product ] ) ) {
											$cart_item = $saved_cart_items[ $selected_product ];

											if ( isset( $cart_item['woosb_parent_id'] ) || isset( $cart_item['wooco_parent_id'] ) || isset( $cart_item['woofs_parent_id'] ) || isset( $cart_item['woobt_parent_id'] ) ) {
												// don't add these special products
												continue;
											}

											if ( self::get_setting( 'keep_data', 'yes' ) === 'yes' ) {
												WC()->cart->add_to_cart( $cart_item['product_id'], $cart_item['quantity'], $cart_item['variation_id'], $cart_item['variation'], $cart_item );
											} else {
												WC()->cart->add_to_cart( $cart_item['product_id'], $cart_item['quantity'], $cart_item['variation_id'], $cart_item['variation'] );
											}
										}
									}
								}

								break;
							case 'all':
								// empty cart first
								WC()->cart->empty_cart();

								foreach ( $saved_cart_items as $cart_item ) {
									if ( isset( $cart_item['woosb_parent_id'] ) || isset( $cart_item['wooco_parent_id'] ) || isset( $cart_item['woofs_parent_id'] ) || isset( $cart_item['woobt_parent_id'] ) ) {
										// don't add these special products
										continue;
									}

									if ( self::get_setting( 'keep_data', 'yes' ) === 'yes' ) {
										WC()->cart->add_to_cart( $cart_item['product_id'], $cart_item['quantity'], $cart_item['variation_id'], $cart_item['variation'], $cart_item );
									} else {
										WC()->cart->add_to_cart( $cart_item['product_id'], $cart_item['quantity'], $cart_item['variation_id'], $cart_item['variation'] );
									}
								}

								break;
						}

						if ( self::get_setting( 'redirect', 'yes' ) === 'yes' ) {
							wp_redirect( wc_get_cart_url() );
						}
					}
				}

				function share_links( $url ) {
					$share_links = '';

					if ( self::get_setting( 'page_share', 'yes' ) === 'yes' ) {
						$facebook  = esc_html__( 'Facebook', 'wpc-share-cart' );
						$twitter   = esc_html__( 'Twitter', 'wpc-share-cart' );
						$pinterest = esc_html__( 'Pinterest', 'wpc-share-cart' );
						$mail      = esc_html__( 'Mail', 'wpc-share-cart' );

						if ( self::get_setting( 'page_icon', 'yes' ) === 'yes' ) {
							$facebook = $twitter = $pinterest = $mail = "<i class='wpcss-icon'></i>";
						}

						$page_items = (array) self::get_setting( 'page_items', [] );

						if ( ! empty( $page_items ) ) {
							$share_links .= '<div class="wpcss-share">';
							$share_links .= '<span class="wpcss-share-label">' . self::localization( 'share_on', esc_html__( 'Share on:', 'wpc-share-cart' ) ) . '</span>';
							$share_links .= ( in_array( 'facebook', $page_items ) ) ? '<a class="wpcss-share-facebook" href="https://www.facebook.com/sharer.php?u=' . $url . '" target="_blank">' . $facebook . '</a>' : '';
							$share_links .= ( in_array( 'twitter', $page_items ) ) ? '<a class="wpcss-share-twitter" href="https://twitter.com/share?url=' . $url . '" target="_blank">' . $twitter . '</a>' : '';
							$share_links .= ( in_array( 'pinterest', $page_items ) ) ? '<a class="wpcss-share-pinterest" href="https://pinterest.com/pin/create/button/?url=' . $url . '" target="_blank">' . $pinterest . '</a>' : '';
							$share_links .= ( in_array( 'mail', $page_items ) ) ? '<a class="wpcss-share-mail" href="mailto:?body=' . $url . '" target="_blank">' . $mail . '</a>' : '';
							$share_links .= '</div>';
						}
					}
					// $share_links = '<div class="wpcss-share">';
					// $share_links .= '
					// 	<a 
					// 		id="order_on_whatsapp_cart" 
					// 		class="woow_whatsapp_button" 
					// 		data-source="cart" 
					// 		data-url="'.$url.'"
					// 	>
					// 		<img src="'.plugin_dir_url(__FILE__).'public/img/whatsapp-button.png">
					// 	</a>';
					// $share_links .= '</div>';

					return apply_filters( 'wpcss_share_links', $share_links, $url );
				}

				function list_shortcode() {
					if ( ! ( $key = get_query_var( 'wpcss_id' ) ) ) {
						return '';
					}

					$url_raw = self::get_url( $key );
					$url     = urlencode( $url_raw );
					$cart    = self::get_setting( 'cart_' . $key );

					if ( empty( $cart ) || ! isset( $cart['cart'] ) ) {
						return '';
					}

					ob_start();
					?>
					<form method="post" action="">
						<table class="wpcss-products shop_table shop_table_responsive cart woocommerce-cart-form__contents">
							<thead>
							<tr>
								<?php if ( self::get_setting( 'add_selected', 'yes' ) === 'yes' ) { ?>
									<th class="product-checkbox">
										<input type="checkbox" class="wpcss-checkbox-all" checked/>
									</th>
								<?php } ?>
								<th class="product-thumbnail">&nbsp;</th>
								<th class="product-name"><?php echo self::localization( 'column_product', esc_html__( 'Product', 'wpc-share-cart' ) ); ?></th>
								<th class="product-price"><?php echo self::localization( 'column_price', esc_html__( 'Price', 'wpc-share-cart' ) ); ?></th>
								<th class="product-quantity"><?php echo self::localization( 'column_quantity', esc_html__( 'Quantity', 'wpc-share-cart' ) ); ?></th>
								<th class="product-subtotal"><?php echo self::localization( 'column_subtotal', esc_html__( 'Subtotal', 'wpc-share-cart' ) ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $cart['cart'] as $cart_item_key => $cart_item ) {
								$product_id = $cart_item['product_id'];
								$_product   = wc_get_product( $product_id );
								$link       = self::get_setting( 'link', 'yes' );

								if ( $_product && $_product->exists() && ( $cart_item['quantity'] > 0 ) && apply_filters( 'wpcss_item_visible', true, $cart_item ) ) {
									$product_permalink = $_product->is_visible() ? $_product->get_permalink() : ''; ?>
									<tr class="woocommerce-cart-form__cart-item">
										<?php if ( self::get_setting( 'add_selected', 'yes' ) === 'yes' ) { ?>
											<td class="product-checkbox">
												<?php
												if ( isset( $cart_item['woosb_parent_id'] ) || isset( $cart_item['wooco_parent_id'] ) || isset( $cart_item['woofs_parent_id'] ) || isset( $cart_item['woobt_parent_id'] ) ) {
													// don't add these special products
													echo '';
												} else {
													echo '<input type="checkbox" class="wpcss-checkbox" name="wpcss-products[]" value="' . esc_attr( $cart_item_key ) . '" checked/>';
												}
												?>
											</td>
										<?php } ?>
										<td class="product-thumbnail">
											<?php
											$thumbnail = apply_filters( 'wpcss_cart_item_thumbnail', apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key ), $cart_item, $cart_item_key );

											if ( ! $product_permalink || $link === 'no' ) {
												echo $thumbnail;
											} else {
												printf( '<a href="%s" ' . ( $link === 'yes_popup' ? 'class="woosq-btn" data-id="' . $product_id . '"' : '' ) . ' ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $product_permalink ), $thumbnail );
											}
											?>
										</td>
										<td class="product-name" data-title="<?php esc_attr_e( 'Product', 'wpc-share-cart' ); ?>">
											<?php
											if ( ! $product_permalink || $link === 'no' ) {
												echo wp_kses_post( apply_filters( 'wpcss_cart_item_name', apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ), $cart_item, $cart_item_key ) . '&nbsp;' );
											} else {
												echo wp_kses_post( apply_filters( 'wpcss_cart_item_name', apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s" ' . ( $link === 'yes_popup' ? 'class="woosq-btn" data-id="' . $product_id . '"' : '' ) . ' ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ), $cart_item, $cart_item_key ) );
											}

											do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
											do_action( 'wpcss_after_cart_item_name', $cart_item, $cart_item_key );

											// Meta data.
											echo wc_get_formatted_cart_item_data( $cart_item );

											// Backorder notification.
											if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
												echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'wpc-share-cart' ) . '</p>', $product_id ) );
											}
											?>
										</td>
										<td class="product-price" data-title="<?php esc_attr_e( 'Price', 'wpc-share-cart' ); ?>">
											<?php echo apply_filters( 'wpcss_cart_item_price', apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $cart_item['data'] ), $cart_item, $cart_item_key ), $cart_item, $cart_item_key ); ?>
										</td>
										<td class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'wpc-share-cart' ); ?>">
											<?php echo apply_filters( 'wpcss_cart_item_quantity', $cart_item['quantity'], $cart_item, $cart_item_key ); ?>
										</td>
										<td class="product-subtotal" data-title="<?php esc_attr_e( 'Subtotal', 'wpc-share-cart' ); ?>">
											<?php echo apply_filters( 'wpcss_cart_item_subtotal', apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $cart_item['data'], $cart_item['quantity'] ), $cart_item, $cart_item_key ), $cart_item, $cart_item_key ); ?>
										</td>
									</tr>
									<?php
								}
							} ?>
							<tr>
								<?php if ( self::get_setting( 'add_selected', 'yes' ) === 'yes' ) { ?>
									<td class="product-checkbox">
										<input type="checkbox" class="wpcss-checkbox-all" checked/>
									</td>
								<?php } ?>
								<td colspan="5">
									<div class="wpcss-actions">
										<?php wp_nonce_field( 'wpcss_add_products', 'wpcss-nonce' ); ?>
										<input type="hidden" name="wpcss-key" value="<?php echo esc_attr( $key ); ?>"/>
										<?php if ( self::get_setting( 'add_selected', 'yes' ) === 'yes' ) { ?>
											<button type="submit" class="button wpcss-add-selected" name="wpcss-action" value="selected"><?php echo self::localization( 'selected', esc_html__( 'Add selected products to cart', 'wpc-share-cart' ) ); ?></button>
										<?php }

										if ( self::get_setting( 'add_all', 'yes' ) === 'yes' ) { ?>
											<button type="submit" class="button wpcss-add-all" name="wpcss-action" value="all"><?php echo self::localization( 'restore', esc_html__( 'Restore cart', 'wpc-share-cart' ) ); ?></button>
										<?php } ?>
									</div>
								</td>
							</tr>
							</tbody>
						</table>
					</form>
					<div class="wpcss-share-actions">
						<?php
						echo self::share_links( $url );

						if ( self::get_setting( 'page_copy', 'yes' ) === 'yes' ) {
							echo '<div class="wpcss-copy">';
							echo '<span class="wpcss-copy-label">' . self::localization( 'share_link', esc_html__( 'Share link:', 'wpc-share-cart' ) ) . '</span>';
							echo '<span class="wpcss-copy-url"><input id="wpcss_copy_url" type="url" value="' . $url_raw . '" readonly/></span>';
							echo '<span class="wpcss-copy-btn"><input id="wpcss_copy_btn" type="button" value="' . self::localization( 'copy_button', esc_html__( 'Copy', 'wpc-share-cart' ) ) . '"/></span>';
							echo '</div>';
						}
						?>
					</div>
					<?php
					return apply_filters( 'wpcss_list_shortcode', ob_get_clean() );
				}

				function register_settings() {
					// settings
					register_setting( 'wpcss_settings', 'wpcss_settings' );

					// localization
					register_setting( 'wpcss_localization', 'wpcss_localization' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', 'WPC Share Cart', 'Share Cart', 'manage_options', 'wpclever-wpcss', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					add_thickbox();
					$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
					?>
					<div class="wpclever_settings_page wrap">
						<h1 class="wpclever_settings_page_title"><?php echo 'WPC Share Cart ' . WPCSS_VERSION; ?></h1>
						<div class="wpclever_settings_page_desc about-text">
							<p>
								<?php printf( esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-share-cart' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
								<br/>
								<a href="<?php echo esc_url( WPCSS_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-share-cart' ); ?></a> |
								<a href="<?php echo esc_url( WPCSS_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-share-cart' ); ?></a> |
								<a href="<?php echo esc_url( WPCSS_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-share-cart' ); ?></a>
							</p>
						</div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
							<div class="notice notice-success is-dismissible">
								<p><?php esc_html_e( 'Settings updated.', 'wpc-share-cart' ); ?></p>
							</div>
						<?php } ?>
						<div class="wpclever_settings_page_nav">
							<h2 class="nav-tab-wrapper">
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-wpcss&tab=settings' ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-share-cart' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-wpcss&tab=localization' ); ?>" class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Localization', 'wpc-share-cart' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-kit' ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-share-cart' ); ?>
								</a>
							</h2>
						</div>
						<div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) {
								if ( isset( $_REQUEST['settings-updated'] ) && $_REQUEST['settings-updated'] === 'true' ) {
									flush_rewrite_rules();
								}

								$link         = self::get_setting( 'link', 'yes' );
								$add_selected = self::get_setting( 'add_selected', 'yes' );
								$add_all      = self::get_setting( 'add_all', 'yes' );
								$keep_data    = self::get_setting( 'keep_data', 'yes' );
								$redirect     = self::get_setting( 'redirect', 'yes' );
								$page_share   = self::get_setting( 'page_share', 'yes' );
								$page_icon    = self::get_setting( 'page_icon', 'yes' );
								$page_copy    = self::get_setting( 'page_copy', 'yes' );
								$page_items   = (array) self::get_setting( 'page_items', [] );
								?>
								<form method="post" action="options.php">
									<table class="form-table">
										<tr class="heading">
											<th colspan="2">
												<?php esc_html_e( 'General', 'wpc-share-cart' ); ?>
											</th>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Share page', 'wpc-share-cart' ); ?></th>
											<td>
												<?php wp_dropdown_pages( [
													'selected'          => self::get_setting( 'page_id', '' ),
													'name'              => 'wpcss_settings[page_id]',
													'show_option_none'  => esc_html__( 'Choose a page', 'wpc-share-cart' ),
													'option_none_value' => '',
												] ); ?>
												<span class="description"><?php printf( esc_html__( 'Add shortcode %s to display the cart contents on a page.', 'wpc-share-cart' ), '<code>[wpcss_list]</code>' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Link to individual product', 'wpc-share-cart' ); ?></th>
											<td>
												<select name="wpcss_settings[link]">
													<option value="yes" <?php selected( $link, 'yes' ); ?>><?php esc_html_e( 'Yes, open in the same tab', 'wpc-share-cart' ); ?></option>
													<option value="yes_blank" <?php selected( $link, 'yes_blank' ); ?>><?php esc_html_e( 'Yes, open in the new tab', 'wpc-share-cart' ); ?></option>
													<option value="yes_popup" <?php selected( $link, 'yes_popup' ); ?>><?php esc_html_e( 'Yes, open quick view popup', 'wpc-share-cart' ); ?></option>
													<option value="no" <?php selected( $link, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-share-cart' ); ?></option>
												</select> <span class="description">If you choose "Open quick view popup", please install and activate <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.</span>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Add selected products', 'wpc-share-cart' ); ?></th>
											<td>
												<select name="wpcss_settings[add_selected]">
													<option value="yes" <?php selected( $add_selected, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-share-cart' ); ?></option>
													<option value="no" <?php selected( $add_selected, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-share-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Enable "Add selected products" buttons?', 'wpc-share-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Restore cart', 'wpc-share-cart' ); ?></th>
											<td>
												<select name="wpcss_settings[add_all]">
													<option value="yes" <?php selected( $add_all, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-share-cart' ); ?></option>
													<option value="no" <?php selected( $add_all, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-share-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Enable "Restore cart" buttons?', 'wpc-share-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Keep product data', 'wpc-share-cart' ); ?></th>
											<td>
												<select name="wpcss_settings[keep_data]">
													<option value="yes" <?php selected( $keep_data, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-share-cart' ); ?></option>
													<option value="no" <?php selected( $keep_data, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-share-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Keep the product data at the sharing moment. If not, when adding selected products or restoring the cart, products will be added to the cart with the current data.', 'wpc-share-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Redirect', 'wpc-share-cart' ); ?></th>
											<td>
												<select name="wpcss_settings[redirect]">
													<option value="yes" <?php selected( $redirect, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-share-cart' ); ?></option>
													<option value="no" <?php selected( $redirect, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-share-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Redirect to the cart page after adding products?', 'wpc-share-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Share buttons', 'wpc-share-cart' ); ?></th>
											<td>
												<select name="wpcss_settings[page_share]">
													<option value="yes" <?php selected( $page_share, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-share-cart' ); ?></option>
													<option value="no" <?php selected( $page_share, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-share-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Enable share buttons?', 'wpc-share-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Share links', 'wpc-share-cart' ); ?></th>
											<td>
												<select multiple name="wpcss_settings[page_items][]">
													<option value="facebook" <?php echo ( in_array( 'facebook', $page_items ) ) ? "selected" : ""; ?>><?php esc_html_e( 'Facebook', 'wpc-share-cart' ); ?></option>
													<option value="twitter" <?php echo ( in_array( 'twitter', $page_items ) ) ? "selected" : ""; ?>><?php esc_html_e( 'Twitter', 'wpc-share-cart' ); ?></option>
													<option value="pinterest" <?php echo ( in_array( 'pinterest', $page_items ) ) ? "selected" : ""; ?>><?php esc_html_e( 'Pinterest', 'wpc-share-cart' ); ?></option>
													<option value="mail" <?php echo ( in_array( 'mail', $page_items ) ) ? "selected" : ""; ?>><?php esc_html_e( 'Mail', 'wpc-share-cart' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Use icon', 'wpc-share-cart' ); ?></th>
											<td>
												<select name="wpcss_settings[page_icon]">
													<option value="yes" <?php selected( $page_icon, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-share-cart' ); ?></option>
													<option value="no" <?php selected( $page_icon, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-share-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Use icon for share link?', 'wpc-share-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Copy link', 'wpc-share-cart' ); ?></th>
											<td>
												<select name="wpcss_settings[page_copy]">
													<option value="yes" <?php selected( $page_copy, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-share-cart' ); ?></option>
													<option value="no" <?php selected( $page_copy, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-share-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Enable copy link to share?', 'wpc-share-cart' ); ?></span>
											</td>
										</tr>
										<tr class="heading">
											<th colspan="2"><?php esc_html_e( 'Suggestion', 'wpc-share-cart' ); ?></th>
										</tr>
										<tr>
											<td colspan="2">
												To display custom engaging real-time messages on any wished positions, please install
												<a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages for WooCommerce</a> plugin. It's free!
											</td>
										</tr>
										<tr>
											<td colspan="2">
												Wanna save your precious time working on variations? Try our brand-new free plugin
												<a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
												<a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
											</td>
										</tr>
										<tr class="submit">
											<th colspan="2">
												<?php settings_fields( 'wpcss_settings' ); ?><?php submit_button(); ?>
											</th>
										</tr>
									</table>
								</form>
							<?php } elseif ( $active_tab === 'localization' ) { ?>
								<form method="post" action="options.php">
									<table class="form-table">
										<tr class="heading">
											<th scope="row"><?php esc_html_e( 'General', 'wpc-share-cart' ); ?></th>
											<td>
												<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-share-cart' ); ?>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Button text', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[button]" value="<?php echo esc_attr( self::localization( 'button' ) ); ?>" placeholder="<?php esc_attr_e( 'Share cart', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Message', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[message]" value="<?php echo esc_attr( self::localization( 'message' ) ); ?>" placeholder="<?php esc_attr_e( 'Share link was generated! Now you can copy below link to share.', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Share on', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[share_on]" value="<?php echo esc_attr( self::localization( 'share_on' ) ); ?>" placeholder="<?php esc_attr_e( 'Share on:', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Share link', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[share_link]" value="<?php echo esc_attr( self::localization( 'share_link' ) ); ?>" placeholder="<?php esc_attr_e( 'Share link:', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Copy button', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[copy_button]" value="<?php echo esc_attr( self::localization( 'copy_button' ) ); ?>" placeholder="<?php esc_attr_e( 'Copy', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Copy message', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[copy_message]" value="<?php echo esc_attr( self::localization( 'copy_message' ) ); ?>" placeholder="<?php esc_attr_e( 'Share link %s was copied to clipboard!', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Add selected', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[selected]" value="<?php echo esc_attr( self::localization( 'selected' ) ); ?>" placeholder="<?php esc_attr_e( 'Add selected products to cart', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Restore cart', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[restore]" value="<?php echo esc_attr( self::localization( 'restore' ) ); ?>" placeholder="<?php esc_attr_e( 'Restore cart', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr class="heading">
											<th scope="row"><?php esc_html_e( 'Columns', 'wpc-share-cart' ); ?></th>
											<td></td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Product', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[column_product]" value="<?php echo esc_attr( self::localization( 'column_product' ) ); ?>" placeholder="<?php esc_attr_e( 'Product', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Price', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[column_price]" value="<?php echo esc_attr( self::localization( 'column_price' ) ); ?>" placeholder="<?php esc_attr_e( 'Price', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Quantity', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[column_quantity]" value="<?php echo esc_attr( self::localization( 'column_quantity' ) ); ?>" placeholder="<?php esc_attr_e( 'Quantity', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Subtotal', 'wpc-share-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="wpcss_localization[column_subtotal]" value="<?php echo esc_attr( self::localization( 'column_subtotal' ) ); ?>" placeholder="<?php esc_attr_e( 'Subtotal', 'wpc-share-cart' ); ?>"/>
											</td>
										</tr>
										<tr class="submit">
											<th colspan="2">
												<?php settings_fields( 'wpcss_localization' ); ?><?php submit_button(); ?>
											</th>
										</tr>
									</table>
								</form>
							<?php } ?>
						</div>
					</div>
					<?php
				}

				function wp_enqueue_scripts() {
					// feather icons
					wp_enqueue_style( 'wpcss-feather', WPCSS_URI . 'assets/libs/feather/feather.css' );

					// main css & js
					wp_enqueue_style( 'wpcss-frontend', WPCSS_URI . 'assets/css/frontend.css', [], WPCSS_VERSION );
					wp_enqueue_script( 'wpcss-frontend', WPCSS_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCSS_VERSION, true );
					wp_localize_script( 'wpcss-frontend', 'wpcss_vars', [
							'ajax_url'    => admin_url( 'admin-ajax.php' ),
							'nonce'       => wp_create_nonce( 'wpcss-security' ),
							'copied_text' => self::localization( 'copy_message', esc_html__( 'Share link %s was copied to clipboard!', 'wpc-share-cart' ) ),
						]
					);
				}

				function admin_enqueue_scripts() {
					wp_enqueue_style( 'wpcss-backend', WPCSS_URI . 'assets/css/backend.css', [], WPCSS_VERSION );
					wp_enqueue_script( 'wpcss-backend', WPCSS_URI . 'assets/js/backend.js', [ 'jquery' ], WPCSS_VERSION, true );
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings = '<a href="' . admin_url( 'admin.php?page=wpclever-wpcss&tab=settings' ) . '">' . esc_html__( 'Settings', 'wpc-share-cart' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WPCSS_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-share-cart' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function share_button() {
					$accounts = $this->get_active_woocommerce_accounts();
					$results = [];
					foreach ( $accounts as $account ) {
						$meta   = get_post_meta( $account->ID, 'nta_wa_account_info', true );
						$avatar = get_the_post_thumbnail_url( $account->ID );
						if ('' !== $meta) {
							$results[] = array_merge(
								array(
									'accountId'   => $account->ID,
									'accountName' => $account->post_title,
									'avatar'      => $avatar !== false ? $avatar : '',
								),
								$meta
							);
						}
					}
					// self::localization( 'button', esc_html__( 'Share cart', 'wpc-share-cart' ) )
					echo '
					<style>
						/* Estilos para el botón y el menú desplegable */
						.dropdown_tienda_china {
							position: relative;
							display: inline-block;
						}

						.dropdown_tienda_china-content {
							display: none;
							position: absolute;
							background-color: #f1f1f1;
							min-width: 160px;
							box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
							z-index: 1;
						}

						.dropdown_tienda_china-content button {
							background-color: #f1f1f1;
							color: black;
							padding: 12px 16px;
							text-decoration: none;
							display: block;
							width: 100%;
							text-align: left;
						}

						.dropdown_tienda_china-content button:hover {
							background-color: #ddd;
						}
					</style>
					<script>
						function toggleDropdown() {
							var dropdown = document.getElementById("myDropdown");
							if (dropdown.style.display === "block") {
								dropdown.style.display = "none";
							} else {
								dropdown.style.display = "block";
							}
						}
					</script>
					<div class="dropdown_tienda_china">
						<button  onclick="toggleDropdown()" type="button"> 
						Comprar Via WhastApp
						</button>
						<div id="myDropdown" class="dropdown_tienda_china-content">
							';
					
							foreach ($results as $account) {
								echo '<button class="button wpcss-btn" data-extra_message="Hola, quisiera ordenar estos productos"  data-phone_number="'.$account['number'].'"  data-hash="' . wc()->cart->get_cart_hash() . '">';
								echo $account['title'];
								echo '</button>';
							}

					echo '
						</div>
					</div>';

					// echo '<button class="button wpcss-btn" data-hash="' . wc()->cart->get_cart_hash() . '">' . self::localization( 'button', esc_html__( 'Share cart', 'wpc-share-cart' ) ) . '</button>';
				}
				

				public function get_posts_maycol($argsQuery = array())
				{
					$defaultArgs = array(
						'post_type' => 'whatsapp-accounts',
						'post_status' => 'publish',
						'numberposts' => -1,
					);

					$args = apply_filters('njt_wa_get_post_type', $defaultArgs);
					$args = wp_parse_args($argsQuery, $args);
					$account_list = get_posts($args);

					return $account_list;
				} 

				public function get_active_woocommerce_accounts()
				{
					return $this->get_posts_maycol(
						array(
							'meta_key' => 'nta_wa_wc_position',
							'orderby' => 'meta_value_num',
							'order' => 'ASC',
							'meta_query' => array(
								array(
									'key' => 'nta_wa_wc_show',
									'value' => 'ON',
									'compare' => '='
								)
						))
					);
				}

				function ajax_share() {
					check_ajax_referer( 'wpcss-security', 'nonce' );

					$url  = '';
					$hash = sanitize_text_field( $_POST['hash'] );

					if ( $key = get_option( 'wpcss_hash_' . $hash ) ) {
						$url = self::get_url( $key );
					} else {
						$key = self::generate_key();
						update_option( 'wpcss_hash_' . $hash, $key );

						$cart = WC()->cart->get_cart();

						$cart_data = [
							'cart'     => $cart,
							'customer' => WC()->cart->get_customer(),
							'coupons'  => WC()->cart->get_applied_coupons(),
							'time'     => time(),
						];

						if ( ! empty( $cart ) ) {
							update_option( 'wpcss_cart_' . $key, $cart_data );
							$url = self::get_url( $key );
						}
					}

					ob_start();
					?>
					<div class="wpcss-popup-text">
						<?php echo self::localization( 'message', esc_html__( 'Share link was generated! Now you can copy below link to share.', 'wpc-share-cart' ) ); ?>
					</div>
					<div class="wpcss-popup-link">
						<input type="url" id="wpcss_copy_url" value="<?php echo esc_url( $url ); ?>" readonly/>
					</div>
					<?php
					echo self::share_links( urlencode( $url ) );
					echo apply_filters( 'wpcss_popup_html', ob_get_clean(), $url );

					wp_die();
				}

				function footer() {
					?>
					<div class="wpcss-area">
						<div class="wpcss-popup">
							<div class="wpcss-popup-inner">
								<span class="wpcss-popup-close"></span>
								<div class="wpcss-popup-content"></div>
							</div>
						</div>
					</div>
					<?php
				}

				function get_page_id() {
					if ( self::get_setting( 'page_id' ) ) {
						return absint( self::get_setting( 'page_id' ) );
					}

					return false;
				}

				function generate_key() {
					$key         = '';
					$key_str     = apply_filters( 'wpcss_key_characters', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' );
					$key_str_len = strlen( $key_str );

					for ( $i = 0; $i < apply_filters( 'wpcss_key_length', 6 ); $i ++ ) {
						$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
					}

					return apply_filters( 'wpcss_generate_key', $key );
				}

				function get_url( $key ) {
					$url = home_url( '/' );

					if ( $page_id = self::get_page_id() ) {
						if ( get_option( 'permalink_structure' ) !== '' ) {
							$url = trailingslashit( get_permalink( $page_id ) ) . $key;
						} else {
							$url = get_permalink( $page_id ) . '&wpcss_id=' . $key;
						}
					}

					return apply_filters( 'wpcss_get_url', $url );
				}

				function sanitize_array( $array ) {
					foreach ( $array as $key => &$value ) {
						if ( is_array( $value ) ) {
							$value = self::sanitize_array( $value );
						} else {
							$value = sanitize_text_field( $value );
						}
					}

					return $array;
				}
			}

			return WPCleverWpcss::instance();
		}
	}
}

if ( ! function_exists( 'wpcss_notice_wc' ) ) {
	function wpcss_notice_wc() {
		?>
		<div class="error">
			<p><strong>WPC Share Cart</strong> requires WooCommerce version 3.0 or greater.</p>
		</div>
		<?php
	}
}
