<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Controller' ) ) {
	class Smart_Manager_Controller {
		public $dashboard_key = '',
				$plugin_path = '',
				$sm_beta_pro_background_updater = '';

		function __construct() {
			if (is_admin() ) {
				add_action ( 'wp_ajax_sm_beta_include_file', array(&$this,'request_handler') );
			}
			$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );

			add_action('admin_init',array(&$this,'call_custom_actions'),11);
			add_action('admin_footer',array(&$this,'sm_footer'));
			//Filter for setting the wp_editor default tab
			add_filter( 'wp_default_editor', array(&$this,'sm_wp_default_editor'),10, 1 );

			// Code for resetting the 'Shop_Order' and 'Shop_Subscription' col models on WC setting update
			add_action( 'woocommerce_update_options_advanced_custom_data_stores', array( &$this, 'migrate_wc_orders_subscriptions_col_model' ) );
			add_action( 'woocommerce_update_options_advanced_features', array( &$this, 'migrate_wc_orders_subscriptions_col_model' ) );
		}

		public function sm_wp_default_editor( $tab ) {
			if ( !empty($_GET['page']) && 'smart-manager' === $_GET['page'] ) {
				$tab = "html";
			}
			return $tab;
		}

		public function sm_footer() {
			if( !empty($_GET['page']) && 'smart-manager' === $_GET['page'] && !( !empty( $_GET['sm_old'] ) && ( 'woo' === $_GET['sm_old'] || 'wpsc' === $_GET['sm_old'] ) ) ) {
				echo '<div id="sm_wp_editor" style="display:none;">';
				wp_editor( '', 'sm_inline_wp_editor', array('default_editor' => 'html') );
				echo '</div>';
			}
		}

		//Function to call custom actions on admin_init		
		public function call_custom_actions() {
			do_action('sm_admin_init');

			add_action( 'edited_term',array( &$this,'terms_added' ), 10, 3 );
			add_action( 'created_term',array( &$this,'terms_added' ), 10, 3 );
			add_action( 'delete_term',array( &$this,'terms_deleted' ), 10, 5 );
			add_action( 'woocommerce_attribute_added',array( &$this,'woocommerce_attributes_updated' ) );
			add_action( 'woocommerce_attribute_updated',array( &$this,'woocommerce_attributes_updated' ) );
			add_action( 'woocommerce_attribute_deleted',array( &$this,'woocommerce_attributes_updated' ) );
			add_action( 'added_post_meta', array( &$this, 'added_post_meta' ), 10, 4 );

			//for background updater
			if( defined('SMPRO') && SMPRO === true && file_exists(SM_PRO_URL . 'classes/class-smart-manager-pro-background-updater.php') ) {
				include_once SM_PRO_URL . 'classes/class-smart-manager-pro-background-updater.php';
				$this->sm_beta_pro_background_updater = Smart_Manager_Pro_Background_Updater::instance();
			}

			// Code for scheduling action for deleting older tasks after x no. of days
			if ( defined('SMPRO') && SMPRO === true && function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'sm_schedule_tasks_cleanup' ) && file_exists( SM_PRO_URL . 'classes/class-smart-manager-pro-task.php' ) ) {
				include_once $this->plugin_path . '/class-smart-manager-base.php';
				include_once SM_PRO_URL . 'classes/class-smart-manager-pro-base.php';
				include_once SM_PRO_URL . 'classes/class-smart-manager-pro-task.php';
				( is_callable( array( 'Smart_Manager_Pro_Task', 'schedule_task_deletion' ) ) ) ? Smart_Manager_Pro_Task::schedule_task_deletion() : '';
			}
		}

		public function woocommerce_attributes_updated() {
			$this->delete_transients( array( 'product' ) );
		}

		public function terms_added( $term, $tt_id, $taxonomy ) {
			global $wp_taxonomies;

			$post_types = ( !empty( $wp_taxonomies[$taxonomy] ) ) ? $wp_taxonomies[$taxonomy]->object_type : array();
			$this->delete_transients( $post_types );
		}

		public function terms_deleted( $term, $tt_id, $taxonomy, $deleted_term, $object_ids ) {
			global $wp_taxonomies;

			$post_types = ( !empty( $wp_taxonomies[$taxonomy] ) ) ? $wp_taxonomies[$taxonomy]->object_type : array();
			$this->delete_transients( $post_types );
		}

		public function added_post_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
			$post_type = get_post_type( $object_id );
			$post_types = ( !empty( $post_type ) ) ? array( $post_type ) : array();
			$this->delete_transients( $post_types );
		}

		public function delete_transients( $post_types = array() ) {
			if( !empty( $post_types ) ) {
				foreach( $post_types as $post_type ) {
					if( get_transient( 'sa_sm_'.$post_type ) ) {
						delete_transient( 'sa_sm_'.$post_type );
					}
				}
			}
		}

		//Function to handle the wp-admin ajax request
		public function request_handler() {
			if (empty($_REQUEST) || empty($_REQUEST['active_module']) || empty($_REQUEST['cmd'])) return;

			check_ajax_referer('smart-manager-security','security');

			if ( !is_user_logged_in() || !is_admin() ) {
				return;
			}

			$pro_flag_class_path = $pro_flag_class_nm = $sm_pro_class_nm = '';

			if( defined('SMPRO') && SMPRO === true ) {
				$plugin_path = SM_PRO_URL .'classes';
				$pro_flag_class_path = 'pro-';
				$pro_flag_class_nm = 'Pro_';
			} else {
				$plugin_path = $this->plugin_path;
			}

			//Including the common utility functions class
			include_once $plugin_path . '/class-smart-manager-'.$pro_flag_class_path.'utils.php';
			$func_nm = $_REQUEST['cmd'];
			if( !empty( $_REQUEST['module'] ) && 'custom_views' === $_REQUEST['module'] ){
				if( class_exists( 'Smart_Manager_Pro_Views' ) ){
					$views_obj = Smart_Manager_Pro_Views::get_instance();
					if( is_callable( array( $views_obj, $func_nm ) ) ) {
						$views_obj->$func_nm();
					}
				}
				return;
			}

			// Code to handle saving of settings
			if( 'smart_manager_settings' === $_REQUEST['active_module'] && is_callable( 'Smart_Manager_Settings', 'update' ) ){
				$settings = ( ! empty( $_REQUEST['settings'] ) ) ? json_decode( stripslashes( $_REQUEST['settings'] ), true ) : array();
				$result = Smart_Manager_Settings::update( $settings );
				wp_send_json( array( 'ACK'=> ( ( ! empty( $result ) ) ? 'Success' : 'Failure' ) ) );
			}

			include_once $this->plugin_path . '/class-smart-manager-base.php';
			$this->dashboard_key = $_REQUEST['active_module'];
			$is_taxonomy_dashboard = ( ! empty( $_REQUEST['is_taxonomy'] ) && ! empty( intval( $_REQUEST['is_taxonomy'] ) ) ) ? true : false;

			$llms_file = $plugin_path . '/'. 'class-smart-manager-'.$pro_flag_class_path.'llms-base.php';
			$tasks_file = $plugin_path . '/' . 'class-smart-manager-' . $pro_flag_class_path . 'task.php';

			if( defined('SMPRO') && SMPRO === true ) {
				$sm_pro_class_nm = 'class-smart-manager-'.$pro_flag_class_path.'base.php';
				include_once $plugin_path . '/'. $sm_pro_class_nm;

				if( is_plugin_active( 'advanced-custom-fields/acf.php' ) || is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ){
					$acf_file = $plugin_path . '/'. 'class-smart-manager-'.$pro_flag_class_path.'acf-base.php';
					if( file_exists( $acf_file ) ){
						include_once $acf_file;
						$acf_class = 'Smart_Manager_'.$pro_flag_class_nm.'ACF_Base';
						$acf_class::instance($this->dashboard_key);
					}
				}

				// Code to include the base class for taxonomy dashboards
				if( ! empty( $is_taxonomy_dashboard ) ){
					$sm_pro_class_nm = 'class-smart-manager-'.$pro_flag_class_path.'taxonomy-base.php';
					include_once $plugin_path . '/'. $sm_pro_class_nm;
				}

				if( is_plugin_active( 'lifterlms/lifterlms.php' ) && file_exists( $llms_file ) ){
					include_once $llms_file;
				}
				if ( isset( $_REQUEST['isTasks'] ) && file_exists( $tasks_file ) ) {
				    include_once $tasks_file;	
				}
			}
			if ( file_exists( $this->plugin_path . '/class-smart-manager-task.php' ) ) {
				include_once $this->plugin_path . '/' . 'class-smart-manager-task.php';	
			}
			if ( file_exists( SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-product-stock-log.php' ) ) {
         		include_once( SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-product-stock-log.php' );
        	}
			//Code for initializing the specific dashboard class

			$file_nm = ( ( ! empty( $is_taxonomy_dashboard ) && ( 'access-privilege' !== $this->dashboard_key ) ) ? 'taxonomy-' : '' ) . str_replace('_', '-', $this->dashboard_key);
			$class_name = '';
			$pro_flag_class_nm .= ( ( ! empty( $is_taxonomy_dashboard ) ) && ( 'access-privilege' !== $this->dashboard_key ) ) ? 'Taxonomy_' : '';

			if (file_exists($plugin_path . '/class-smart-manager-'.$pro_flag_class_path.''.$file_nm.'.php')) {

				$key_array = explode( "_", str_replace( '-', '_', $this->dashboard_key ) );
				$formatted_dashboard_key = array();
				foreach( $key_array as $value ) {
					$formatted_dashboard_key[] = ucwords($value);
				}

				$class_name = 'Smart_Manager_'.$pro_flag_class_nm.''.implode("_",$formatted_dashboard_key);

				if( file_exists( $this->plugin_path . '/class-smart-manager-'.$file_nm.'.php' ) ) {
					include_once $this->plugin_path . '/class-smart-manager-'.$file_nm.'.php';
				}

				if( defined('SMPRO') && SMPRO === true ) {
					$sm_pro_class_nm = 'class-smart-manager-'.$pro_flag_class_path.''.$file_nm.'.php';
					include_once $plugin_path .'/'. $sm_pro_class_nm;
				}
			} else {
				$class_name = (!empty($pro_flag_class_nm)) ? 'Smart_Manager_'.$pro_flag_class_nm.'Base' : 'Smart_Manager_Base';
				if( is_plugin_active( 'lifterlms/lifterlms.php' ) && class_exists( 'Smart_Manager_Pro_LLMS_Base' ) && in_array( $this->dashboard_key, Smart_Manager_Pro_LLMS_Base::$post_types ) ){
					$class_name = 'Smart_Manager_Pro_LLMS_Base';
				}
			}
			if( !empty( $_REQUEST['cmd'] ) && $_REQUEST['cmd'] == 'get_background_progress' ) {
				$class_name = 'class-smart-manager-pro-background-updater.php';
				$sm_pro_class_nm =  'Smart_Manager_Pro_Background_Updater';
			} elseif ( isset( $_REQUEST['isTasks'] ) && ( ( ! empty( $_REQUEST['cmd'] ) && ( 'save_state' === $_REQUEST['cmd'] ) ) ) || ( ! empty( $_REQUEST['isTasks'] ) ) ) {
				if ( ! empty( $is_taxonomy_dashboard ) && is_callable( $class_name, 'actions' ) ) {
					$class_name::actions();
				}
				$class_name = 'Smart_Manager_Task';
			}
			if ( ! empty( $_REQUEST['isTasks'] ) ) {
				$class_name = 'Smart_Manager_Pro_Task';
			} elseif ( 'product_stock_log' === $this->dashboard_key ) {
				$class_name = 'Smart_Manager_Product_Stock_Log';
			}
			$_REQUEST['class_nm'] = $class_name;
			$_REQUEST['class_path'] = $sm_pro_class_nm;
			if( !empty( $this->sm_beta_pro_background_updater ) && !empty( $_REQUEST['cmd'] ) && $_REQUEST['cmd'] == 'get_background_progress' ) {
				is_callable( array( $this->sm_beta_pro_background_updater, $func_nm ) ) ? $this->sm_beta_pro_background_updater->$func_nm() : Smart_Manager::log( 'error', _x( "Method $func_nm is not callable in class Smart_Manager_Pro_Background_Updater.", 'Smart Manager - Ajax request handler background progress', 'smart-manager-for-wp-e-commerce' ) );
			} else if( class_exists( $class_name ) ) {
				$handler_obj = new $class_name($this->dashboard_key);
				( is_callable( array( $handler_obj, $func_nm ) ) ) ? $handler_obj->$func_nm() : Smart_Manager::log( 'error', _x( "Method $func_nm is not callable in class $class_name.", 'Smart Manager - Ajax request handler', 'smart-manager-for-wp-e-commerce' ) );
			}
		}


		/**
		 * Function to re-generate the column model for 'Shop_Order' and 'Shop_Subscription' on WC settings update.
		 */
		public function migrate_wc_orders_subscriptions_col_model() {

			global $wpdb;

			$user_id = get_current_user_id();

			if( empty( $user_id ) ){
				return;
			}

			$order_column_model = get_user_meta( $user_id, 'sa_sm_shop_order', true );
			$subscription_column_model = get_user_meta( $user_id, 'sa_sm_shop_subscription', true );

			if( empty( $order_column_model ) && empty( $subscription_column_model ) ){
				return;
			}

			if( ! class_exists( 'Smart_Manager_Shop_Order' ) && file_exists( $this->plugin_path . '/class-smart-manager-shop-order.php' ) ){
				if( ! class_exists( 'Smart_Manager_Base' ) && file_exists( $this->plugin_path . '/class-smart-manager-base.php' ) ){
					include_once $this->plugin_path . '/class-smart-manager-base.php';
				}
				include_once $this->plugin_path . '/class-smart-manager-shop-order.php';
			}

			if( ! is_callable( array( 'Smart_Manager_Shop_Order', 'migrate_col_model' ) ) ){
				return;
			}

			if( ! empty( $order_column_model ) ) {
				delete_transient( 'sa_sm_shop_order' );
				update_user_meta( $user_id, 'sa_sm_shop_order' , Smart_Manager_Shop_Order::migrate_col_model( $order_column_model ) );
			}

			if( ! empty( $subscription_column_model ) ) {
				delete_transient( 'sa_sm_shop_subscription' );
				update_user_meta( $user_id, 'sa_sm_shop_subscription' , Smart_Manager_Shop_Order::migrate_col_model( $subscription_column_model ) );
			}

			// Code to update custom views
			if( ! ( defined('SMPRO') && true === SMPRO ) ) {
				return;
			}

			if ( $wpdb->prefix. 'sm_views' !== $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->prefix. 'sm_views' ) ) ) {
				return;
			}

			$views = $wpdb->get_results(
				$wpdb->prepare(
								"SELECT id,
										params
									FROM {$wpdb->prefix}sm_views
									WHERE post_type=%s
										OR post_type=%s",
								'shop_order',
								'shop_subscription'
				),
				'ARRAY_A'
			);

			if( empty( $views ) || ! is_array( $views ) ){
				return;
			}

			$view_update_clauses = array();
			foreach( $views as $view ){
				if( empty( $view['id'] ) || empty( $view['params'] ) ){
					continue;
				}

				$view['params'] = json_decode( $view['params'], true );

				if( empty( $view['params'] ) || ! is_array( $view['params'] ) ){
					continue;
				}

				$updated_col_model = Smart_Manager_Shop_Order::migrate_col_model( $view['params'] );
				if( empty( $updated_col_model ) ){
					continue;
				}
				$view_update_clauses[$view['id']] = "WHEN id={$view['id']} THEN '". wp_json_encode($updated_col_model) ."'";
			}

			if( empty( $view_update_clauses ) ){
				return;
			}

			$query = "UPDATE {$wpdb->prefix}sm_views
			SET params = CASE ". implode( ",", $view_update_clauses ) . " END 
			WHERE id IN (". implode( ",", array_keys( $view_update_clauses ) ) .")";
			
			$wpdb->query(
				"UPDATE {$wpdb->prefix}sm_views
				SET params = CASE ". implode( " ", $view_update_clauses ) . " END 
				WHERE id IN (". implode( ",", array_keys( $view_update_clauses ) ) .")"
			);
		}
	}
}
