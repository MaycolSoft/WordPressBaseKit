<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Base' ) ) {
	class Smart_Manager_Base {

		public $dashboard_key = '',
			$dashboard_title = '',
			$post_type = '',
			$default_store_model = array(),
			$terms_val_parent = array(),
			$req_params = array(),
			$terms_sort_join = false,
			$advance_search_operators = array(
												'eq'=> '=',
												'neq'=> '!=',
												'lt'=> '<',
												'gt'=> '>',
												'lte'=> '<=',
												'gte'=> '>='
										),
			$advanced_search_table_types = array(
												'flat' => array( 'posts' => 'ID' ),
												'meta' => array( 'postmeta' => 'post_id' )
										),
			$prev_post_values = array(),
			$prev_postmeta_values = array(),
			$field_names = array(),
			$store_col_model_transient_option_nm = '',
			$task_id = 0,
			$entire_task = false,
			$previous_cond_has_results = false,
			$disable_task_details_update = false,
			$plugin_path = '';
			public static $update_task_details_params = array(),
			$previous_vals = array();
		// include_once $this->plugin_path . '/class-smart-manager-utils.php';

		function __construct($dashboard_key) {
			$this->dashboard_key = $dashboard_key;
			$this->post_type = $dashboard_key;
			$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );
			$this->req_params  	= (!empty($_REQUEST)) ? $_REQUEST : array();
			$this->dashboard_title = ( !empty( $this->req_params['active_module_title'] ) ) ? $this->req_params['active_module_title'] : 'Post';
			$this->advanced_search_table_types = apply_filters( 'sm_search_table_types', $this->advanced_search_table_types ); //filter to add custom tables to table types
			$this->store_col_model_transient_option_nm = 'sa_sm_' . $this->dashboard_key;
			add_filter( 'posts_join_paged', array( &$this, 'sm_query_join' ), 99, 2 );
			add_filter( 'posts_where', array( &$this, 'sm_query_post_where_cond' ), 99, 2 );
			add_filter( 'posts_groupby', array( &$this, 'sm_query_group_by' ), 99, 2 );
			add_filter( 'posts_orderby', array( &$this, 'sm_query_order_by' ), 99, 2 );
			add_action( 'sm_search_posts_conditions_array_complete', array( &$this, 'get_matching_children_advanced_search' ) );
			add_action( 'sm_search_posts_condition_start', array( &$this, 'modify_posts_advanced_search_condition' ), 10, 2 );
			add_action( 'sm_search_query_postmeta_from', array( &$this, 'modify_postmeta_advanced_search_from' ), 10, 2 );
		}

		/**
		 * Adds custom JOIN clauses to the SQL query.
		 *
		 * @param string $join The existing JOIN clause.
		 * @param WP_Query|null $wp_query_obj The WP_Query object, if available.
		 * @return string The modified JOIN clause.
		 */
		public function sm_query_join ( $join = '', $wp_query_obj = null ) {
			return $this->get_join_clause_for_search( array( 'join' => $join, 'wp_query_obj' => $wp_query_obj ) );
		}

		/**
		 * Modifies the WHERE clause of the WP_Query object for search conditions.
		 *
		 * @param string $where The existing WHERE clause.
		 * @param WP_Query|null $wp_query_obj The WP_Query object.
		 * @return string The modified WHERE clause.
		 */
		public function sm_query_post_where_cond ( $where = '', $wp_query_obj = null ) {
			if ( empty( $where ) ) {
				return $where;
			}
			//Code for handling search.
			$where_params = $this->get_where_clause_for_search( array( 'where' => $where ) );
			return ( ! empty( $where_params['where'] ) ) ? $where_params['where'] : $where;
		}

		/**
		 * Adds custom GROUP BY clauses to the SQL query.
		 *
		 * @param string $group_by The existing GROUP BY clause.
		 * @param WP_Query|null $wp_query_obj The WP_Query object, if available.
		 * @return string The modified GROUP BY clause.
		 */
		public function sm_query_group_by ( $group_by = '', $wp_query_obj = null ) {
			return $this->get_group_by_clause_for_search( array( 'group_by' => $group_by ) );
		}

		/**
		 * Modifies the ORDER BY clause of a WP_Query object.
		 *
		 * @param string $order_by The ORDER BY clause to be used in the query.
		 * @param WP_Query|null $wp_query_obj The WP_Query object to modify. Default is null.
		 * @return string The modified ORDER BY clause.
		 */
		public function sm_query_order_by ( $order_by = '', $wp_query_obj = null ) {
			return $this->get_order_by_clause_for_sort( array( 'order_by' => $order_by, 'wp_query_obj' => $wp_query_obj ) );
		}

		public function get_type_from_data_type( $data_type = '' ){
			
			$type = 'text';

			if( empty( $data_type ) ){
				return $type;
			}

			$type_strpos = strrpos( $data_type,'(' );
			if( $type_strpos !== false ) {
				$type = substr( $data_type, 0, $type_strpos );
			} else {
				$types = explode( " ", $data_type ); // for handling types with attributes (biginit unsigned)
				$type = ( ! empty( $types ) ) ? $types[0] : $data_type; 
			}

			switch( $type ){
				case (substr($type,-3) == 'int'):
					$type = 'numeric';
					break;
				case (substr($type,-4) == 'char' || substr($type,-4) == 'text'):
					$type = ( $type == 'longtext' ) ? 'sm.longstring' : 'text';
					break;
				case ( substr($type,-4) == 'blob' ):
					$type = 'sm.longstring';
					break;
				case ( $type == 'datetime' || $type == 'timestamp' ):
					$type = 'sm.datetime';
					break;
				case ( $type == 'date' || $type == 'year' ):
					$type = 'sm.date';
					break;
				case ( $type == 'decimal' || $type == 'float' || $type == 'double' || $type == 'real' ):
					$type = 'numeric';
					break;
				case ( $type == 'boolean' ):
					$type = 'checkbox';
					break;
				default:
					$type = 'text';
			}

			return $type;
		}

		public function get_type_from_value( $value = '' ) {
			$type = 'text';

			if( empty( $value ) ){
				return $type;
			}

			$checkbox_values = array( 'yes', 'no', 'true', 'false' );

			switch( $value ){
				case ( ! empty( in_array( $value, $checkbox_values, true ) ) || ( is_numeric( $value ) && ( $value === '0' || $value === '1' ) ) ):
					$type = 'checkbox';
					break;
				case ( is_numeric( $value ) ):
					if( function_exists('isTimestamp') ) {
						if( isTimestamp( $value ) ) {
							$type = 'sm.datetime';
							break;
						}
					}
				
					if( $type != 'sm.datetime' ) {
						$type = 'numeric';
					}
					break;
				case (is_serialized($value) === true):
					$type = 'sm.serialized';
					break;
				case ( DateTime::createFromFormat('Y-m-d H:i:s', $value) !== false ):
					$type = 'sm.datetime';
					break;
				case ( DateTime::createFromFormat('Y-m-d', $value) !== false ):
					$type = 'sm.date';
					break;	
				default:
					$type = 'text';
			}
			return $type;
		}

		public function get_col_type( $data_type = '', $value = '' ){
			return ( !empty( $data_type ) ) ? $this->get_type_from_data_type( $data_type ) : $this->get_type_from_value( $value );
		}

		public function get_default_column_model( $args = array() ){

			global $wpdb;

			$column = array();
		
			if( empty( $args ) ){
				return $column;
			}
		
			if( empty( $args['table_nm'] ) || empty( $args['col'] ) ){
				return $column;
			}

			$col = $args['col'];
			unset( $args['col'] );
			$table_nm = $args['table_nm'];
			unset( $args['table_nm'] );
			
			$visible_cols = array();
			if( ! empty( $args['visible_cols'] ) ){
				$visible_cols = $args['visible_cols'];
				unset( $args['visible_cols'] );
			}

			$is_meta = false;
			if( ! empty( $args['is_meta'] ) ){
				$is_meta = true;
				unset( $args['is_meta'] );
			}

			$src = $table_nm .'/'. ( ( !empty( $is_meta ) ) ? 'meta_key='.$col.'/meta_value='.$col : $col );
			$name = ( ! empty( $args['name'] ) ) ? $args['name'] : __( ucwords( str_replace( '_', ' ', $col ) ), 'smart-manager-for-wp-e-commerce' );
			
			if( isset( $args['name'] ) ) {
				unset( $args['name'] );
			}

			// Code to get the col type
			$data_type = '';
			if( ! empty( $args['db_type'] ) ){
				$data_type = $args['db_type'];
				unset( $args['db_type'] );
			}

			$col_value = '';
			if( ! empty( $args['col_value'] ) ){
				$col_value = $args['col_value'];
				unset( $args['col_value'] );
			}
			
			$uneditable_types = array( 'sm.longstring' );
			$type = $this->get_col_type( $data_type, $col_value );

			if( ! empty( $args['values'] ) && empty( $args['search_values'] ) ) {
				$args['search_values'] = array();
				foreach( $args['values'] as $key => $value ) {
					$args['search_values'][] = array( 'key' => $key, 'value' => $value );
				}
			}

			$default_widths = apply_filters( 'sm_default_col_widths', array(
				'sm.image'		=> 50,
				'numeric'		=> 50,
				'checkbox'		=> 30,
				'sm.datetime'	=> 105,
				'text'			=> 130,
				'sm.longstring'	=> 150,
				'sm.serialized'	=> 200
			) ); 

			$column = array_merge( array(
								'src'				=> $src,
								'data'				=> sanitize_title( str_replace( array( '/', '=' ), '_', $src ) ), // generate slug using the wordpress function if not given 
								'name'				=> $name,
								'key'				=> $name,
								'type'				=> $type,
								'editor'			=> ( 'numeric' === $type ) ? 'customNumericEditor' : $type,
								'hidden'			=> false,
								'editable'			=> ( empty( in_array( $type, $uneditable_types ) ) ) ? true : false,
								'batch_editable'	=> true,
								'sortable'			=> true,
								'resizable'			=> true,
								'allow_showhide'	=> true,
								'exportable'		=> true,
								'searchable'		=> true,
								'frozen'			=> false,  //For disabling frozen
								'wordWrap'			=> false,  //For disabling word-wrap
								'save_state'		=> true,
								'editor_schema'		=> false,
								'align'				=> ( 'numeric' === $type ) ? 'right' : 'left',
								'table_name'		=> $wpdb->prefix . $table_nm,
								'col_name'			=> ( ! empty( $args['col_name'] ) ) ? $args['col_name'] : $col,
								'width'				=> ( ! empty( $default_widths[$type] ) ) ? $default_widths[$type] : 200,
								'values'			=> array(),
								'search_values'		=> array(),
								'category'			=> '',
								'placeholder'		=> ''	
			), $args );

			if ( strpos($col, '_phone') !== false || strpos($col, '_tel') !== false || strpos($col, 'phone_') !== false || strpos($col, 'tel_') !== false ) {
				$column['validator'] = 'customPhoneTextEditor';
			}

			if( ( ! empty( $is_meta ) && ( '_thumbnail_id' === $col || 'thumbnail_id' === $col ) ) || 'sm.image' === $type ){
				$column['name'] = $args['key'] = __('Featured Image', 'smart-manager-for-wp-e-commerce');
				$column['type'] = 'sm.image';
				$column['align']= 'center';
				$column['search_type']= 'numeric';
				$column['editable']= false;
				$column['sortable']= false;
			}

			if( 'checkbox' === $type ){
				if( $col_value == 'yes' || $col_value == 'no' ){
					$column['checkedTemplate'] = 'yes';
					$column['uncheckedTemplate'] = 'no';
				} else if( $col_value === '0' || $col_value === '1' ){
					$column['checkedTemplate'] = 1;
					$column['uncheckedTemplate'] = 0;
				}
			}

			if( function_exists('isTimestamp') ) {
				if( isTimestamp( $col_value ) && 'sm.datetime' === $type ) {
					$column['date_type'] = 'timestamp';
				}
			}

			if( ! empty( $visible_cols ) && is_array( $visible_cols ) ){
				if( ! empty( $column['position'] ) ) {
					unset( $column['position'] );
				}
				$position = array_search( $column['data'], $visible_cols );
				if( false !== $position ) {
					$column['position'] = $position + 1;
					$column['hidden'] = false;
				} else {
					$column['hidden'] = true;
				}
			}

			return $column;
		}

		public function get_default_store_model() {

			global $wpdb;

			$col_model = array();

			$ignored_col = array('post_type');
			$default_col_positions = array( 'ID', 'post_title', 'post_content', 'post_status', 'post_date', 'post_name' );
			$visible_cols = array( 'ID', 'post_title', 'post_date', 'post_name', 'post_status', 'post_content' );
			$hidden_cols = array( '_edit_lock','_edit_last' );
			$col_titles = array(
								'post_date' 	=> $this->dashboard_title . ' Created Date',
								'post_date_gmt' => $this->dashboard_title . ' Created Date Gmt'
			);

			$query_posts_col = "SHOW COLUMNS FROM {$wpdb->prefix}posts";
			$results_posts_col = $wpdb->get_results($query_posts_col, 'ARRAY_A');
			$posts_num_rows = $wpdb->num_rows;
			$last_position = 0;

			if ($posts_num_rows > 0) {
				foreach ($results_posts_col as $posts_col) {
					
					$field_nm = (!empty($posts_col['Field'])) ? $posts_col['Field'] : '';

					if( in_array($field_nm, $ignored_col) ) {
						continue;
					}

					$args = array(
						'table_nm' 	=> 'posts',
						'col'		=> $field_nm,
						'name'		=> ( ! empty( in_array( $field_nm, $col_titles ) ) ) ? $col_titles[$field_nm] : '',
						'hidden'	=> ( empty( in_array( $field_nm, $visible_cols ) ) ) ? true : false,
						'db_type'	=> ( ! empty( $posts_col['Type'] ) ) ? $posts_col['Type'] : ''
					);

					//Code for handling extra meta for the columns
					if ($field_nm == 'ID') {
						$args['editor'] = false;
						$args['batch_editable'] = false;
					}else if ($field_nm == 'post_status') {
						$args['type'] = 'dropdown';
						$args['strict'] = true;
						$args['allowInvalid'] = false;

						if ( 'page' === $this->dashboard_key ) {
							$args['values'] = get_page_statuses();
						} else {
							$statuses = get_post_stati( array(), 'object' );
							$args['values'] = array();

							// Code for creating unused_statuses array
							$unused_post_statuses = array( 'inherit', 'trash', 'auto-draft', 'in-progress', 'failed', 'request-pending', 'request-confirmed', 'request-failed', 'request-completed' );
							if ( function_exists( 'wc_get_order_statuses' ) ) {
								$unused_post_statuses = array_merge( $unused_post_statuses, array_keys( wc_get_order_statuses() ) );
							}
							if ( function_exists( 'wcs_get_subscription_statuses' ) ) {
								$unused_post_statuses = array_merge( $unused_post_statuses, array_keys( wcs_get_subscription_statuses() ) );
							}
							$unused_post_statuses = apply_filters( 'sm_unused_post_statuses', $unused_post_statuses );
							foreach( $statuses as $status ) {
								if ( in_array( $status->name, $unused_post_statuses ) ) {
									continue;
								}
								$args['values'][$status->name] = $status->label;
							}
						}
						
						$args['defaultValue'] = 'draft';

						$args['editor'] = 'select';
						$args['selectOptions'] = $args['values'];
						$args['renderer'] = 'selectValueRenderer';
					}else if ( 'post_excerpt' === $field_nm ) {
						$args['type'] = 'sm.longstring';
					}

					// Code for setting the default column positions
					$position = array_search( $field_nm, $default_col_positions );
					if( false !== $position ){
						$args['position'] = $position + 1;
						$last_position++;
					}

					$col_model[] = $this->get_default_column_model( $args );
				}
			}
			//Code to get columns from postmeta table

			$post_type_cond = (is_array($this->post_type)) ? " AND {$wpdb->prefix}posts.post_type IN ('". implode("','", $this->post_type) ."')" : " AND {$wpdb->prefix}posts.post_type = '". $this->post_type ."'";

			$query_postmeta_col = "SELECT DISTINCT {$wpdb->prefix}postmeta.meta_key,
											{$wpdb->prefix}postmeta.meta_value
										FROM {$wpdb->prefix}postmeta 
											JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.id = {$wpdb->prefix}postmeta.post_id)
										WHERE {$wpdb->prefix}postmeta.meta_key != '' 
											AND {$wpdb->prefix}postmeta.meta_key NOT LIKE 'free-%'
											AND {$wpdb->prefix}postmeta.meta_key NOT LIKE '_oembed%'
											AND {$wpdb->prefix}postmeta.meta_key NOT REGEXP '^[a-f0-9]{32}$'
											$post_type_cond
										GROUP BY {$wpdb->prefix}postmeta.meta_key";
			$results_postmeta_col = $wpdb->get_results ($query_postmeta_col , 'ARRAY_A');
			$num_rows = $wpdb->num_rows;

			if ($num_rows > 0) {

				$meta_keys = array();

				foreach ($results_postmeta_col as $key => $postmeta_col) {
					if ( empty( $postmeta_col['meta_value'] ) || $postmeta_col['meta_value'] == '1' || $postmeta_col['meta_value'] == '0.00' ) {
						$meta_keys [] = $postmeta_col['meta_key']; //TODO: if possible store in db instead of using an array
					}

					unset($results_postmeta_col[$key]);
					$results_postmeta_col[$postmeta_col['meta_key']] = $postmeta_col;
				}

				//not in 0 added for handling empty date columns
				if (!empty($meta_keys)) {
					$query_meta_value = "SELECT {$wpdb->prefix}postmeta.meta_key,
													{$wpdb->prefix}postmeta.meta_value
												FROM {$wpdb->prefix}postmeta 
													JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.id = {$wpdb->prefix}postmeta.post_id)
												WHERE {$wpdb->prefix}posts.post_type  = '". $this->dashboard_key ."'
													AND {$wpdb->prefix}postmeta.meta_value NOT IN ('','0','0.00','1')
													AND {$wpdb->prefix}postmeta.meta_key IN ('".implode("','",$meta_keys)."')
												GROUP BY {$wpdb->prefix}postmeta.meta_key";
					$results_meta_value = $wpdb->get_results ($query_meta_value , 'ARRAY_A');
					$num_rows_meta_value = $wpdb->num_rows;

					if ($num_rows_meta_value > 0) {
						foreach ($results_meta_value as $result_meta_value) {
							if (isset($results_postmeta_col [$result_meta_value['meta_key']])) {
								$results_postmeta_col [$result_meta_value['meta_key']]['meta_value'] = $result_meta_value['meta_value'];
							}
						}
					}
				}

				//Filter to add custom postmeta columns for custom plugins
				$results_postmeta_col = apply_filters('sm_default_dashboard_model_postmeta_cols', $results_postmeta_col);
				$meta_count = 0;

				//Code for pkey column for postmeta

				$col_model[] = $this->get_default_column_model( array(
					'table_nm' 			=> 'postmeta',
					'col'				=> 'post_id',
					'type'				=> 'numeric',
					'hidden'			=> true,
					'allow_showhide'	=> false,
					'editor'			=> false,
				) );

				foreach ($results_postmeta_col as $postmeta_col) {
					$meta_key = ( !empty( $postmeta_col['meta_key'] ) ) ? $postmeta_col['meta_key'] : '';
					$meta_value = ( !empty( $postmeta_col['meta_value'] ) || $postmeta_col['meta_value'] == 0 ) ? $postmeta_col['meta_value'] : '';

					$args = array(
						'table_nm' 	=> 'postmeta',
						'col'		=> $meta_key,
						'is_meta'	=> true,
						'col_value'	=> $meta_value,
						'name'		=> ( ! empty( in_array( $meta_key, $col_titles ) ) ) ? $col_titles[$meta_key] : '',
						'hidden'	=> ( ! empty( in_array( $meta_key, $hidden_cols ) ) || $meta_count > 5 ) ? true : false
					);

					// Handling for _thumnail_id => image column
					// if( '_thumbnail_id' === $meta_key ){
					// 	$args['name'] = $args['key']= __('Featured Image', 'smart-manager-for-wp-e-commerce');
					// 	$args['align']= 'center';
					// 	$args['type']= 'sm.image';
					// 	$args['searchable']= true;
					// 	$args['search_type']= 'numeric';
					// 	$args['editable']= false;
					// 	$args['batch_editable']= true;
					// 	$args['sortable']= false;
					// 	$args['resizable']= true;
					// }

					if( empty( $args['hidden'] ) ){
						$last_position++;
					}

					$col_model[] = $this->get_default_column_model( $args );

					$meta_count++;
				}
			}

			//Code to get columns from terms

			//Code to get all relevant taxonomy for the post type
			$taxonomy_nm = get_object_taxonomies($this->post_type);

			if (!empty($taxonomy_nm)) {
				$terms_count = 0;
				//Code for pkey column for terms
				$col_model[] = $this->get_default_column_model( array(
					'table_nm' 			=> 'terms',
					'col'				=> 'object_id',
					'type'				=> 'numeric',
					'hidden'			=> true,
					'allow_showhide'	=> false,
					'editor'			=> false,
				) );

				$taxonomy_terms = get_terms($taxonomy_nm, array('hide_empty'=> 0,'orderby'=> 'name'));
				if ( ! empty( $taxonomy_terms ) ) {
					$results = $this->get_parent_term_values(
						array(
							'taxonomy_obj'      => $taxonomy_terms,
							'include_taxonomy'  => 'all' // include all taxonomy.
						)
					);
				}

				//Code for defining the col model for the terms
				foreach ($taxonomy_nm as $taxonomy) {

					$args = array(
						'table_nm' 	=> 'terms',
						'col'		=> $taxonomy,
						'name'		=> ( ! empty( in_array( $field_nm, $col_titles ) ) ) ? $col_titles[$field_nm] : '',
						'hidden'	=> ( $terms_count > 5 ) ? true : false
					);
					if ( ! isset( $results['terms_val'] ) || ! isset( $results['terms_val_search'] ) ) {
						continue;
					}
					if ( ! empty( $results['terms_val'][$taxonomy] ) ) {
						$args['type'] 			= 'sm.multilist';
						$args['strict'] 		= true;
						$args['allowInvalid'] 	= false;
						$args['editable']		= false;
						$args['values'] 		= $results['terms_val'][$taxonomy];

						if( ! empty( $results['terms_val_search'][$taxonomy] ) ){
							$args['search_values'] = array();
							foreach( $results['terms_val_search'][$taxonomy] as $key => $value ) {
								$args['search_values'][] = array( 'key' => $key, 'value' => $value );
							}
						}
					}

					if( empty( $args['hidden'] ) ){
						$last_position++;
					}
					$col_model[] = $this->get_default_column_model( $args );
					$terms_count++;
				}
			}

			$col_model[] = $this->get_default_column_model( array(
				'table_nm' 			=> 'custom',
				'col'				=> 'edit_link',
				'renderer'			=> 'html',
				'name'				=> __('Edit', 'smart-manager-for-wp-e-commerce'),
				'sortable'			=> false,
				'editor'			=> false,
				'searchable' 		=> false,
				'editable' 			=> false,
				'batch_editable' 	=> false,
				'position' 			=> ++$last_position,
				'width'				=> 30
			) );

			if( !empty( $this->req_params['is_public'] ) ) {
				$col_model[] = $this->get_default_column_model( array(
					'table_nm' 			=> 'custom',
					'col'				=> 'view_link',
					'renderer'			=> 'html',
					'name'				=> __('View', 'smart-manager-for-wp-e-commerce'),
					'sortable'			=> false,
					'editor'			=> false,
					'searchable' 		=> false,
					'editable' 			=> false,
					'batch_editable' 	=> false,
					'position' 			=> ++$last_position,
					'width'				=> 30
				) );
			}

			//defining the default col model

			$this->default_store_model =  array( 
						'display_name' => __(ucwords(str_replace('_', ' ', $this->dashboard_key)), 'smart-manager-for-wp-e-commerce'),
						'tables' => array(
										'posts' 				=> array(
																		'pkey' => 'ID',
																		'join_on' => '',
																		'where' => array( 
																						'post_type' 	=> $this->post_type,
																						'post_status' 	=> 'any' // will get all post_status except 'trash' and 'auto-draft'
																						
																						// 'post_status' 	=> array('publish', 'draft') // comma seperated for multiple values
																						//For any other whereition specify, colname => colvalue
																						)
																	),

										'postmeta' 				=> array(
																		'pkey' => 'post_id',
																		'join_on' => 'postmeta.post_ID = posts.ID', // format current_table.pkey = joinning table.pkey
																		'where' => array( // provide a wp_query [meta_query]
																					// 'relation' => 'AND', // AND or OR
																					// 	array(
																					// 		'key'     => '',
																					// 		'value'   => '',
																					// 		'compare' => '',
																					// 	),
																					// 	array(
																					// 		'key'     => '',
																					// 		'value'   => 0,
																					// 		'type'    => '',
																					// 		'compare' => '',
																					// 	)
																					)
																	),

										'term_relationships' 	=> array(
																		'pkey' => 'object_id',
																		'join_on' => 'term_relationships.object_id = posts.ID',
																		'where' => array()
																	),

										'term_taxonomy' 		=> array(
																		'pkey' => 'term_taxonomy_id',
																		'join_on' => 'term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id',
																		'where' => array()
																	),

										'terms' 				=> array(
																		'pkey' => 'term_id',
																		'join_on' => 'terms.term_id = term_taxonomy.term_id',
																		'where' => array(
																				// 'relation' => 'AND', // AND or OR
																				// array(
																				// 	'taxonomy' => '',
																				// 	'field'    => '',
																				// 	'terms'    => ''
																				// ),
																			)
																	)

										), 
						'columns' => $col_model,
						'sort_params' 	=> array ( //WP_Query array structure
												'orderby' => 'ID', //multiple list separated by space
												'order' => 'DESC',
												'default' => true ),

						// 'sort_params' 		=> array( 'post_parent' => 'ASC', 'ID' => 'DESC' ),
						'per_page_limit' 	=> '', // blank, 0, -1 all values refer to infinite scroll
						'treegrid'			=> false // flag for setting the treegrid
			);
		}

		//Function to handle the user specific column model mapping to store model
		public function map_column_to_store_model( $store_model = array(), $column_model_transient = array() ) {
			if( !empty( $store_model['columns'] ) ) {

				$enabled_cols = $enabled_cols_position_blank = $disabled_cols = array();

				$column_transient = ( ! empty( $column_model_transient['columns'] ) ) ? $column_model_transient['columns'] : array();
				$column_transient_formatted = array();
				if( ! empty( $column_transient ) ){
					array_walk(
						$column_transient,
						function ( $col_obj, $col ) use( &$column_transient_formatted ) {
							$column_transient_formatted[ strtolower( $col ) ] = $col_obj;
						}
					);
				}

				$saved_column_titles = ( defined('SMPRO') && true === SMPRO && ! empty( $this->store_col_model_transient_option_nm ) ) ? get_option( $this->store_col_model_transient_option_nm .'_columns', array() ) : array();

				foreach( $store_model['columns'] as $key => $col ) {

					$col_data = ( ! empty( $col['data'] ) ) ? strtolower( $col['data'] ) : ''; //did if the columns are stored as uppercase

					// Code for handling column titles
					if( ( defined('SMPRO') && true === SMPRO ) && ! empty( $saved_column_titles ) && ! empty( $col_data ) && ! empty( $saved_column_titles[ $col_data ] ) ) {
						$store_model['columns'][$key]['name'] = $store_model['columns'][$key]['key'] = $store_model['columns'][$key]['name_display'] = $saved_column_titles[ $store_model['columns'][$key]['data'] ];
					}

					$store_model['columns'][$key]['width'] = ( !empty( $store_model['columns'][$key]['width'] ) ) ? $store_model['columns'][$key]['width'] : '';
					$store_model['columns'][$key]['position'] = ( !empty( $store_model['columns'][$key]['position'] ) ) ? $store_model['columns'][$key]['position'] : '';

					if( !empty( $col_data ) && !empty( $column_transient_formatted[ $col_data ] ) ) {
						$store_model['columns'][$key]['hidden'] = false; 
						$store_model['columns'][$key]['width'] = ( !empty( $column_transient_formatted[ $col_data ]['width'] ) ? $column_transient_formatted[ $col_data ]['width'] : $store_model['columns'][$key]['width'] );
						$store_model['columns'][$key]['position'] = ( !empty( $column_transient_formatted[ $col_data ]['position'] ) ? $column_transient_formatted[ $col_data ]['position'] : $store_model['columns'][$key]['position'] );

						if( !empty( $store_model['columns'][$key]['position'] ) ) {
							$enabled_cols[ (int)$store_model['columns'][$key]['position'] ] = $store_model['columns'][$key];
						} else {
							$enabled_cols_position_blank[] = $store_model['columns'][$key];
						}
						
					} else {
						$store_model['columns'][$key]['hidden'] = true;
						$disabled_cols[] = $store_model['columns'][$key];
					}
				}

				usort($enabled_cols, function ($item1, $item2) {
				    if ($item1['position'] == $item2['position']) return 0;
				    return $item1['position'] < $item2['position'] ? -1 : 1;
				});

				$store_model['columns'] = array_merge( $enabled_cols, $enabled_cols_position_blank, $disabled_cols );
			}			

			$store_model['sort_params'] = ( !empty( $store_model['sort_params'] ) ) ? $store_model['sort_params'] : array();
			$store_model['sort_params'] = ( !empty( $column_model_transient['sort_params'] ) ? $column_model_transient['sort_params'] : $store_model['sort_params'] );

			$store_model = apply_filters( 'sm_map_column_state_to_store_model', $store_model, $column_model_transient );

			return $store_model;
		}

		//Function to get the dashboard model
		public function get_dashboard_model( $return_store_model = false ) {
			global $wpdb, $current_user;
			$col_model = array();
			$old_col_model = array();
			$search_params = array();
			$column_model_transient = ( ! empty( $this->store_col_model_transient_option_nm ) ) ? get_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, true ) : array();
			// Code for handling views
			if( ( defined('SMPRO') && true === SMPRO ) && ! empty( $this->req_params['is_view'] ) && ! empty( $this->req_params['active_view'] ) ) {
				if( class_exists( 'Smart_Manager_Pro_Views' ) ) {
					$view_obj = Smart_Manager_Pro_Views::get_instance();
					if( is_callable( array( $view_obj, 'get' ) ) ){
						$view_slug = $this->req_params['active_view'];
						$view_data = $view_obj->get($view_slug);
						if( ! empty( $view_data ) ) {
							$this->dashboard_key = $view_data['post_type'];
							$column_model_transient = json_decode( $view_data['params'], true );
							if( !empty( $column_model_transient['search_params'] ) ) {
								if( ! empty( $column_model_transient['search_params']['isAdvanceSearch'] ) ) { // For advanced search
									if( ! empty( $column_model_transient['search_params']['params'] ) && is_array( $column_model_transient['search_params']['params'] ) ) {
										// code for porting from old structure
										$search_query = $column_model_transient['search_params']['params'];
										if( empty( $search_query[0]['condition'] ) ){

											$rule_groups = array();
											$search_operators = array_flip( $this->advance_search_operators );

											foreach( $search_query as $query ) {

												if( empty( $query ) || ! is_array( $query ) ) {
													continue;
												}

												$rules = array();

												// iterate over each rule
												foreach( $query as $rule ) {
													$rules[] = array(
																	'type' => $rule['table_name'] .'.'. $rule['col_name'],
																	'operator' => strtolower( ( ! empty( $search_operators[ $rule['operator'] ] ) ) ? $search_operators[ $rule['operator'] ] : $rule['operator'] ),
																	'value' => $rule['value']
													);
												}

												$rule_groups[] = array( 'condition' => "AND", 'rules' => $rules );
											}

											$column_model_transient['search_params']['params'] = array( array( 'condition' => 'OR', 'rules' => $rule_groups ) );

											// code to upate the view new structure at db level
											$result = $wpdb->query( // phpcs:ignore
												$wpdb->prepare( // phpcs:ignore
													"UPDATE {$wpdb->prefix}sm_views
																		SET params = %s
																		WHERE slug = %s",
													json_encode( $column_model_transient ),
													$view_slug
												)
											);
										}
									}
								}
								$search_params = $column_model_transient['search_params'];
							}
						}
					}
				}
			}

			// Load from cache
			$store_model_transient = ( ! empty( $this->store_col_model_transient_option_nm ) ) ? get_transient( $this->store_col_model_transient_option_nm ) : '';
			if( ! empty( $store_model_transient ) && ! is_array( $store_model_transient ) ) {
				$store_model_transient = json_decode( $store_model_transient, true );
			}

			// Code to move the column transients at user meta level
			// since v5.0.0
			if ( empty( $column_model_transient ) && ( empty( $this->req_params['isTasks'] ) ) ) {
				$key = 'sm_beta_' . $current_user->user_email . '_' . $this->dashboard_key;
				$column_model_transient  = get_option( $key );
				if ( ! empty( $column_model_transient ) && ( ! empty( $this->store_col_model_transient_option_nm ) )) {
					update_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, $column_model_transient );
					delete_option( $key );
				}
			}
			if ( empty( $column_model_transient ) && ( empty( $this->req_params['isTasks'] ) ) ) { //for getting the old structure
				$column_model_transient = get_transient( 'sa_sm_' . $current_user->user_email . '_' . $this->dashboard_key );
				if ( ! empty( $column_model_transient ) ) {
					delete_transient( 'sa_sm_' . $current_user->user_email . '_' . $this->dashboard_key );
				}
			}

			if( !empty( $column_model_transient ) && !empty( $column_model_transient['tables'] ) ) { //for porting the old structure

				$saved_col_model = $column_model_transient;
				$column_model_transient = sa_sm_generate_column_state( $saved_col_model );

				if( empty( $store_model_transient ) ) {
					$store_model_transient = $saved_col_model;
				}
			}

			//Check if upgrading from old mapping
			if( false !== $store_model_transient ) {
				if( empty( $store_model_transient['columns'][0]['data'] ) || false === get_option( '_sm_update_414' ) || false === get_option( '_sm_update_419'.'_'.$this->dashboard_key ) ) {

					if( ! empty( $store_model_transient['columns'] ) ) {
						foreach( $store_model_transient['columns'] as $col ) {
							if( empty( $col['src'] ) ){
								continue;
							}
							$old_col_model[ $col['src'] ] = $col;
						}
					}

					delete_transient( 'sa_sm_'.$this->dashboard_key );

					if( false === get_option( '_sm_update_414' ) ) {
						update_option( '_sm_update_414', 1, 'no' );
					}

					if( false === get_option( '_sm_update_419'.'_'.$this->dashboard_key ) ) {
						update_option( '_sm_update_419'.'_'.$this->dashboard_key, 1, 'no' );
					}
				}

				if( false === get_option( '_sm_update_411' ) ) { //Code for handling mapping changes in v4.1.1
					foreach( $store_model_transient['columns'] as $key => $col ) {
						if( $this->dashboard_key == 'user' && !empty( $col['col_name'] ) && $col['col_name'] == 'wp_capabilities' ) {
							$store_model_transient['columns'][$key]['col_name'] = $wpdb->prefix.'capabilities';
						}

						if( $this->dashboard_key == 'product' && !empty( $col['col_name'] ) && $col['col_name'] == 'post_name' ) {
							$store_model_transient['columns'][$key]['key'] = $store_model_transient['columns'][$key]['name_display'] = __('Slug', 'smart-manager-for-wp-e-commerce');
						}

						if( !empty( $col['col_name'] ) && $col['col_name'] == 'post_excerpt' ) {
							$store_model_transient['columns'][$key]['type'] = $store_model_transient['columns'][$key]['editor'] = 'sm.longstring';
						}
					}

					if( !empty( $store_model_transient['sort_params'] ) ) {
						if( $store_model_transient['sort_params']['orderby'] == 'ID' && $store_model_transient['sort_params']['order'] == 'DESC' ) {
							$store_model_transient['sort_params']['default'] = true;
						}
					}

					delete_transient( 'sa_sm_'.$this->dashboard_key );
					update_option( '_sm_update_411', 1, 'no' );
				}

				if( false === get_option( '_sm_update_415' ) ) { //Code for handling mapping changes in v4.1.1
					
					$add_view_col = true;
					foreach( $store_model_transient['columns'] as $key => $col ) {
						if( $this->dashboard_key == 'product' && !empty( $col['col_name'] ) && $col['col_name'] == 'product_shop_url' ) {
							unset( $store_model_transient['columns'][$key] );
							$store_model_transient['columns'] = array_values($store_model_transient['columns']);
						}
						
						if( !empty( $col['data'] ) && $col['data'] == 'custom_view_link' ) {
							$add_view_col = false;
						}
					}

					if( !empty( $add_view_col ) ) {

						$link_type = ( !empty( $this->req_params['is_public'] ) ) ? 'view' : 'edit'; 

						$index = sizeof( $store_model_transient['columns'] );

						$store_model_transient['columns'][$index] = array(
																		'src' => 'custom/link',
																		'data' => 'custom_'. $link_type .'_link',
																		'name' => ucwords($link_type),
																		'type' => 'text',
																		'renderer'=> 'html',
																		'frozen' => false,
																		'sortable' => false,
																		'exportable' => true,
																		'searchable' => false,
																		'editable' => false,
																		'batch_editable' => false,
																		'hidden' => true,
																		'allow_showhide' => true
																	);
					}

					delete_transient( 'sa_sm_'.$this->dashboard_key );
					update_option( '_sm_update_415', 1, 'no' );
				}

				if( false === get_option( '_sm_update_425' ) ) { //Code for handling mapping changes in v4.2.5
					if( $this->dashboard_key == 'product' ) {
						foreach( $store_model_transient['columns'] as $key => $col ) {
							if( !empty( $col['data'] ) && $col['data'] == 'custom_product_attributes' ) {
								$store_model_transient['columns'][$key]['allow_showhide'] = true;
								$store_model_transient['columns'][$key]['exportable'] = true;
							}

							if( !empty( $col['data'] ) && $col['data'] == 'postmeta_meta_key__product_attributes_meta_value__product_attributes' ) {
								$store_model_transient['columns'][$key]['hidden']= true;
								$store_model_transient['columns'][$key]['allow_showhide']= false;
								$store_model_transient['columns'][$key]['exportable']= false;
							}
						}
						delete_transient( 'sa_sm_'.$this->dashboard_key );
						update_option( '_sm_update_425', 1, 'no' );
					}
				}

				if( false === get_option( '_sm_update_426' ) ) { //Code for handling mapping changes in v4.2.6
					if( $this->dashboard_key == 'shop_subscription' ) {
						delete_transient( 'sa_sm_'.$this->dashboard_key );
						update_option( '_sm_update_426', 1, 'no' );
					}
				}

				if( false === get_option( '_sm_update_427' ) ) { //Code for handling mapping changes in v4.2.7
					if( $this->dashboard_key != 'user' ) {
						foreach( $store_model_transient['columns'] as $key => $col ) {
							if( !empty( $col['col_name'] ) && $col['col_name'] == 'post_status' && empty( $col['colorCodes'] ) ) {
		
								if( $this->dashboard_key == 'shop_order' ) {

									$color_codes = array( 'green' => array( 'wc-completed', 'wc-processing' ),
															'red' => array( 'wc-cancelled', 'wc-failed', 'wc-refunded' ),
															'orange' => array( 'wc-on-hold', 'wc-pending' ) );

								} else if( $this->dashboard_key == 'shop_subscription' ) {

									$color_codes = array( 'green' => array( 'wc-active' ),
															'red' => array( 'wc-expired', 'wc-cancelled' ),
															'orange' => array( 'wc-on-hold', 'wc-pending' ),
															'blue' => array( 'wc-switched', 'wc-pending-cancel' ) );

								} else {
									$color_codes = array();
								}
								
								$store_model_transient['columns'][$key]['colorCodes'] = $color_codes;

								delete_transient( 'sa_sm_'.$this->dashboard_key );
								update_option( '_sm_update_427', 1, 'no' );
							}

							if( $this->dashboard_key == 'product' && !empty( $col['col_name'] ) && ( in_array($col['col_name'], array( '_stock_status', '_backorders' )) ) && empty( $col['colorCodes'] ) ) {
								if( $col['col_name'] == '_stock_status' ) {
									$color_codes = array( 'green' => array( 'instock' ),
															'red' => array( 'outofstock' ),
															'blue' => array( 'onbackorder' ) );
								} else {
									$color_codes = array( 'green' => array( 'yes', 'notify' ),
															'red' => array( 'no' ),
															'blue' => array() );
								}

								$store_model_transient['columns'][$key]['colorCodes'] = $color_codes;

								delete_transient( 'sa_sm_'.$this->dashboard_key );
								update_option( '_sm_update_427', 1, 'no' );

							}
						}
					}
				}

				if( false === get_option( '_sm_update_4210'.'_'.$this->dashboard_key ) ) { //Code for handling mapping changes in v4.2.10
					if( $this->dashboard_key == 'shop_order' ) {
						$custom_columns = array( 'shipping_method', 'coupons_used', 'line_items', 'details' );
						foreach( $store_model_transient['columns'] as $key => $col ) {
							$data = ( !empty( $col['data'] ) ) ? substr( $col['data'], 7 ) : '';
							if( !empty( $data ) && in_array( $data , $custom_columns ) ) {
								$store_model_transient['columns'][$key]['editor'] = false;
							}
						}

						delete_transient( 'sa_sm_'.$this->dashboard_key );
					} else if( $this->dashboard_key == 'product' ) {
						delete_transient( 'sa_sm_'.$this->dashboard_key );
					} else if( $this->dashboard_key == 'wc_booking' ) {
						delete_transient( 'sa_sm_'.$this->dashboard_key );
					} else if( $this->dashboard_key == 'wc_membership_plan' ) {
						delete_transient( 'sa_sm_'.$this->dashboard_key );
					} else if( $this->dashboard_key == 'wc_user_membership' ) {
						delete_transient( 'sa_sm_'.$this->dashboard_key );
					}
					update_option( '_sm_update_4210'.'_'.$this->dashboard_key, 1, 'no' );
				}

				// Have to delete transient of those dashboard which are having atleaset on zero_one checkbox model because checkedtemplate & uncheckedtemplate logic is swapped in version 4.2.11
				if( false === get_option( '_sm_update_4211'.'_'.$this->dashboard_key ) ) { //Code for handling mapping changes in v4.2.11
					$is_checkbox = false;
					if ( ! empty( $store_model_transient['columns'] ) ) {
						foreach( $store_model_transient['columns'] as $key => $col ) {
							if ( ! empty( $col['type'] ) && 'checkbox' === $col['type'] && isset( $col['checkedTemplate'] ) && absint( $col['checkedTemplate'] ) === 0 ) {
								$is_checkbox = true;
								break;
							}
						}
					}
					if ( true === $is_checkbox ) {
						delete_transient( 'sa_sm_'.$this->dashboard_key );
					}
					update_option( '_sm_update_4211'.'_'.$this->dashboard_key, 1, 'no' );
				}

				if( false === get_option( '_sm_update_44'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					update_option( '_sm_update_44'.'_'.$this->dashboard_key, 1, 'no' );
				}

				if( false === get_option( '_sm_update_442_users' ) ) {
					delete_transient( 'sa_sm_users' );
					update_option( '_sm_update_442_users', 1, 'no' );
				}

				if( false === get_option( '_sm_update_443_product' ) ) {
					delete_transient( 'sa_sm_product' );
					update_option( '_sm_update_443_product', 1, 'no' );
				}

				if( false === get_option( '_sm_update_461'.'_'.$this->dashboard_key ) ) { //Code for handling date cols mapping changes in v4.6.1
					$date_cols = array( 'posts_post_date', 'posts_post_date_gmt', 'posts_post_modified', 'posts_post_modified_gmt' );
					foreach( $store_model_transient['columns'] as $key => $col ) {
						$data = ( !empty( $col['data'] ) ) ? $col['data'] : '';
						if( !empty( $data ) && in_array( $data , $date_cols ) ) {
							$display_name = $this->dashboard_title . ' '. ( ( strpos( $data, 'modified' ) !== false ) ? 'Modified' : 'Created' ) . ' Date' . ( ( strpos( $data, 'gmt' ) !== false ) ? ' GMT' : '' );
							$store_model_transient['columns'][$key]['searchable'] = true;
							$store_model_transient['columns'][$key]['name'] = $display_name;
							$store_model_transient['columns'][$key]['key'] = $store_model_transient['columns'][$key]['name'];
						}
					}
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					update_option( '_sm_update_461'.'_'.$this->dashboard_key, 1, 'no' );
				}

				if( false === get_option( '_sm_update_520'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					update_option( '_sm_update_520'.'_'.$this->dashboard_key, 1, 'no' );
				}

				if( false === get_option( '_sm_update_530_shop_order' ) ) {
					delete_transient( 'sa_sm_shop_order' );
					update_option( '_sm_update_530_shop_order', 1, 'no' );
				}

				if( false === get_option( '_sm_update_5110_users' ) ) {
					delete_transient( 'sa_sm_users' );
					update_option( '_sm_update_5110_users', 1, 'no' );
				}

				if( false === get_option( '_sm_update_5120_users' ) ) {
					delete_transient( 'sa_sm_users' );
					update_option( '_sm_update_5120_users', 1, 'no' );
				}

				if( false === get_option( '_sm_update_5120_product' ) ) {
					delete_transient( 'sa_sm_product' );
					update_option( '_sm_update_5120_product', 1, 'no' );
				}

				if( false === get_option( '_sm_update_5140'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					update_option( '_sm_update_5140'.'_'.$this->dashboard_key, 1, 'no' );
				}

				if( false === get_option( '_sm_update_5160'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					update_option( '_sm_update_5160'.'_'.$this->dashboard_key, 1, 'no' );
				}

				if( false === get_option( '_sm_update_5180'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					update_option( '_sm_update_5180'.'_'.$this->dashboard_key, 1, 'no' );
				}

				if( false === get_option( '_sm_update_5190'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_5190'.'_'.$this->dashboard_key, 1, 'no' );
				}

				if( false === get_option( '_sm_update_5191'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_5191'.'_'.$this->dashboard_key, 1, 'no' );
				}

				if( false === get_option( '_sm_update_5250_product' ) ) {
					delete_transient( 'sa_sm_product' );
					update_option( '_sm_update_5250_product', 1, 'no' );
				}

				if( false === get_option( '_sm_update_5260_product' ) ) {
					delete_transient( 'sa_sm_product' );
					update_option( '_sm_update_5260_product', 1, 'no' );
				}
				if( false === get_option( '_sm_update_600_user' ) ) {
					delete_transient( 'sa_sm_user' );
					$store_model_transient = false;
					update_option( '_sm_update_600_user', 1, 'no' );
				}
				if( false === get_option( '_sm_update_620'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_620'.'_'.$this->dashboard_key, 1, 'no' );
				}
				if( false === get_option( '_sm_update_630'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_630'.'_'.$this->dashboard_key, 1, 'no' );
				}
				if( false === get_option( '_sm_update_670_shop_order' ) ) {
					delete_transient( 'sa_sm_shop_order' );
					$store_model_transient = false;
					update_option( '_sm_update_670_shop_order', 1, 'no' );
				}
				if( false === get_option( '_sm_update_700'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_700'.'_'.$this->dashboard_key, 1, 'no' );
				}
				if( false === get_option( '_sm_update_701'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_701'.'_'.$this->dashboard_key, 1, 'no' );
				}
				if ( false === get_option( '_sm_update_740_user' ) ) {
					delete_transient( 'sa_sm_user' );
					$store_model_transient = false;
					update_option( '_sm_update_740_user', 1, 'no' );
				}
				if( false === get_option( '_sm_update_820'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_820'.'_'.$this->dashboard_key, 1, 'no' );
				}
				if( false === get_option( '_sm_update_870'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_'.$this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_870'.'_'.$this->dashboard_key, 1, 'no' );
				}
				if( false === get_option( '_sm_update_880'.'_'.$this->dashboard_key ) ) {
					delete_transient( 'sa_sm_' . $current_user->user_email . '_' . $this->dashboard_key . '_tasks' );
					$store_model_transient = false;
					update_option( '_sm_update_880'.'_'.$this->dashboard_key, 1, 'no' );
				}
				if ( ! empty( Smart_Manager::$sm_is_woo79 ) && ! empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) && false === get_option( '_sm_update_813_hpos_migrate' ) && ! empty( $GLOBALS['smart_manager_controller'] ) && $GLOBALS['smart_manager_controller'] instanceof Smart_Manager_Controller && is_callable( array( $GLOBALS['smart_manager_controller'], 'migrate_wc_orders_subscriptions_col_model' ) ) ) {
					$GLOBALS['smart_manager_controller']->migrate_wc_orders_subscriptions_col_model();
					update_option( '_sm_update_813_hpos_migrate', 1, 'no' );
				}
				if ( false === get_option( '_sm_update_8150_user' ) ) {
					delete_transient( 'sa_sm_user' );
					$store_model_transient = false;
					update_option( '_sm_update_8150_user', 1, 'no' );
				}
				if ( in_array( $this->dashboard_key, array( 'user', 'product' ) ) && ( false === get_option( '_sm_update_8160_' . $this->dashboard_key ) ) ) {
					delete_transient( 'sa_sm_' . $this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_8160_' . $this->dashboard_key, 1, 'no' );
				}
				if ( false === get_option( '_sm_update_8190_product_stock_log' ) ) {
					delete_transient( 'sa_sm_product_stock_log_tasks' );
					delete_user_meta( get_current_user_id(), 'sa_sm_product_stock_log_tasks' );
					$store_model_transient = false;
					update_option( '_sm_update_8190_product_stock_log', 1, 'no' );
				}
				if ( false === get_option( '_sm_update_8240_shop_coupon' ) ) {
					delete_transient( 'sa_sm_shop_coupon' );
					$store_model_transient = false;
					update_option( '_sm_update_8240_shop_coupon', 1, 'no' );
				}
				if ( false === get_option( '_sm_update_8250_product' ) ) {
					delete_transient( 'sa_sm_product' );
					$store_model_transient = false;
					update_option( '_sm_update_8250_product', 1, 'no' );
				}
				if ( false === get_option( '_sm_update_8260_product' ) ) {
					delete_transient( 'sa_sm_product' );
					$store_model_transient = false;
					update_option( '_sm_update_8260_product', 1, 'no' );
				}
				if ( false === get_option( '_sm_update_8340_product' ) ) {
					delete_transient( 'sa_sm_product' );
					$store_model_transient = false;
					update_option( '_sm_update_8340_product', 1, 'no' );
				}
				if ( false === get_option( '_sm_update_8360_product' ) ) {
					delete_transient( 'sa_sm_product' );
					$store_model_transient = false;
					update_option( '_sm_update_8360_product', 1, 'no' );
				}
				if ( false === get_option( '_sm_update_8390_product_stock_log' ) ) {
					delete_transient( 'sa_sm_product_stock_log_tasks' );
					$wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                            'sa_sm_product_stock_log_tasks'
                        )
                    );
					$store_model_transient = false;
					update_option( '_sm_update_8390_product_stock_log', 1, 'no' );
				}
				if ( in_array( $this->dashboard_key, array( 'shop_order', 'shop_subscription' ) ) && ( false === get_option( '_sm_update_8400_' . $this->dashboard_key ) ) ) {
					delete_transient( 'sa_sm_' . $this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_8400_' . $this->dashboard_key, 1, 'no' );
				}
				if ( in_array( $this->dashboard_key, array( 'shop_order', 'shop_subscription' ) ) && ( false === get_option( '_sm_update_8410_' . $this->dashboard_key ) ) ) {
					delete_transient( 'sa_sm_' . $this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_8410_' . $this->dashboard_key, 1, 'no' );
				}
				if ( in_array( $this->dashboard_key, array( 'shop_order', 'shop_subscription' ) ) && ( false === get_option( '_sm_update_8430_' . $this->dashboard_key ) ) ) {
					delete_transient( 'sa_sm_' . $this->dashboard_key );
					$store_model_transient = false;
					update_option( '_sm_update_8430_' . $this->dashboard_key, 1, 'no' );
				}
			}

			$store_model = $store_model_transient;

			// Valid cache not found
			if ( false === $store_model ) {
				$load_default_store_model = apply_filters('sm_beta_load_default_store_model', true);
				if( ! empty( $load_default_store_model ) ){
					$this->get_default_store_model();
				}
				
				//Filter to modify the default dashboard model
				$this->default_store_model = apply_filters('sm_default_dashboard_model', $this->default_store_model);

				$store_model = ( !empty( $this->default_store_model ) ) ? $this->default_store_model : array();

				if( ! empty( $store_model['columns'] ) ){
					foreach ($store_model['columns'] as $key => $value) {
						$store_model['columns'][$key]['save_state'] = true;
					}
				}
			}
			//Filter to modify the dashboard model
			$store_model = apply_filters('sm_dashboard_model', $store_model, $store_model_transient);
			//Code for porting to new mapping
			if( !empty($old_col_model) ) {

				$new_col_model = $store_model['columns'];

				foreach( $new_col_model as $index => $new_col ) {
					if( !empty( $new_col['src'] ) && !empty( $old_col_model[$new_col['src']] ) ) {
						$new_col_model[$index]['width'] = 80;

						$new_col_model[$index]['width'] = ( !empty( $old_col_model[$new_col['src']]['width'] ) ) ? $old_col_model[$new_col['src']]['width'] : $new_col_model[$index]['width'];
						$new_col_model[$index]['hidden'] = ( !empty( $old_col_model[$new_col['src']]['hidden'] ) ) ? $old_col_model[$new_col['src']]['hidden'] : $new_col_model[$index]['hidden'];

						//Code for posting the column position
						if( !isset( $old_col_model[$new_col['src']]['position'] ) && isset( $new_col_model[$index]['position'] ) ) { //unset the position if not there
							unset( $new_col_model[$index]['position'] );
						} else if( isset( $old_col_model[$new_col['src']]['position'] ) ) {
							$new_col_model[$index]['position'] = $old_col_model[$new_col['src']]['position'];
						}
					}
				}

				$store_model['columns'] = $new_col_model;
			}

			//code to show/hide columns as per stored transient only if atleast one column is enabled
			if( !empty( $column_model_transient ) && ! empty( $column_model_transient['columns'] ) ) {
				$store_model = $this->map_column_to_store_model( $store_model, $column_model_transient );
			} else { //for setting the custom column dashboard transient for the user
				$column_model_transient = sa_sm_generate_column_state( $store_model );
			}
			//Code for re-arranging the columns in the final column model based on the set position
			$final_column_model = (!empty($store_model['columns'])) ? $final_column_model = &$store_model['columns'] : '';
			if (!empty($final_column_model)) {

				$priority_columns = array();

				foreach ($final_column_model as $key => &$column_model) {

					//checking for multilist datatype
					if (!empty($column_model['type']) && $column_model['type'] == 'sm.multilist') {

						$col_exploded = (!empty($column_model['src'])) ? explode("/", $column_model['src']) : array();
						
						if ( sizeof($col_exploded) > 2) {
							$col_meta = explode("=",$col_exploded[1]);
							$col_nm = $col_meta[1];
						} else {
							$col_nm = $col_exploded[1];
						}

						$column_model['values'] = (!empty($this->terms_val_parent[$col_nm])) ? $this->terms_val_parent[$col_nm] : $column_model['values'];
					}

					if( !isset( $column_model['position']) ) continue;
						
					$priority_columns[] = $column_model;
					unset( $final_column_model[$key] );
				}

				if ( !empty($priority_columns) || !empty( $final_column_model ) ) {

					usort( $priority_columns, "sm_position_compare" ); //code for sorting as per the position

					$final_column_model = array_values($final_column_model);

					foreach ($final_column_model as $col_model) {
						$priority_columns[] = $col_model;
					}

					ksort($priority_columns);
					$store_model['columns'] = $priority_columns;
				}
			}
			// Valid cache not found
			if ( ( ! empty( $this->store_col_model_transient_option_nm ) ) && false === get_transient( $this->store_col_model_transient_option_nm ) ) {
				set_transient( $this->store_col_model_transient_option_nm, wp_json_encode( $store_model ), WEEK_IN_SECONDS );
			}

			if ( ( ! empty( $this->store_col_model_transient_option_nm ) ) && empty( get_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, true ) ) ) {
				update_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, $column_model_transient );
			}

			// Code to handle display of 'trash' value for 'post_status' -- not to be saved in transient
			if( ! empty( $this->is_show_trash_records() ) && 'user' !== $this->dashboard_key ) {
				foreach( $store_model['columns'] as $key => $col ) {
					if( ! empty( $col['col_name'] ) && 'post_status' === $col['col_name'] ){
						$store_model['columns'][$key]['values']['trash'] = __( 'Trash', 'smart-manager-for-wp-e-commerce' );
						$store_model['columns'][$key]['selectOptions']['trash'] = __( 'Trash', 'smart-manager-for-wp-e-commerce' );
						$store_model['columns'][$key]['search_values'][] = array( 'key' => 'trash', 'value' => __( 'Trash', 'smart-manager-for-wp-e-commerce' ) );

						// Code for handling color code for 'trash' if enabled
						if( ! empty( $store_model['columns'][$key]['colorCodes'] ) ){
							if( ! is_array( $store_model['columns'][$key]['colorCodes']['red'] ) ){
								$store_model['columns'][$key]['colorCodes']['red'] = array();
							}
							$store_model['columns'][$key]['colorCodes']['red'][] = 'trash';
						}
						break;
					}
				}
			}

			do_action('sm_dashboard_model_saved');

			if( ! empty( $search_params ) ) {
				$store_model['search_params'] = $search_params;
			}

			if( !$return_store_model ) {
				wp_send_json( $store_model );
			} else {
				return $store_model;
			}
			
		}

		public function process_search_cond($params = array()) {

			global $wpdb, $wp_version;


			if( empty($params) || empty($params['search_query']) ) {
				return;
			}

			$rule_groups = ( ! empty( $params['search_query'] ) ) ? $params['search_query'][0]['rules'] : array();

			if( empty( $rule_groups ) ) {
				return;
			}

			$wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp"); // query to reset advanced search temp table

            $advanced_search_query = array();
            $i = 0;

			$search_cols_type = ( ! empty( $params['search_cols_type'] ) ) ? $params['search_cols_type'] : array();

			$non_flat_table_types = ( ! empty( $this->advanced_search_table_types['meta'] ) ) ? array_merge( array( 'terms' ), array_keys( $this->advanced_search_table_types['meta'] ) ) : array( 'terms' );
            foreach ( $rule_groups as $rule_group ) {

                if ( ! empty( $rule_group )) {

						$advanced_search_query[$i] = array();
						
						if( ! empty( $this->advanced_search_table_types ) ){
							if( ! empty( $this->advanced_search_table_types['flat'] ) ){
								foreach( array_keys( $this->advanced_search_table_types['flat'] ) as $table ){
									$advanced_search_query[$i]['cond_'. $table] = '';
								}
							}

							if( ! empty( $non_flat_table_types ) ){
								foreach( $non_flat_table_types as $table ){
									$advanced_search_query[$i]['cond_'. $table] = '';
									$advanced_search_query[$i]['cond_'. $table .'_col_name'] = '';
									$advanced_search_query[$i]['cond_'. $table .'_col_value'] = '';
									$advanced_search_query[$i]['cond_'. $table .'_operator'] = '';
								}
							}
						}

						$rule_group = apply_filters('sm_before_search_string_process', $rule_group);
						$rules = ( ! empty( $rule_group['rules'] ) ) ? $rule_group['rules'] : array();

                        foreach( $rules as $rule ) {

							if( ! empty( $rule['type'] ) ) {
								$field = explode( '.', $rule['type'] );
								$rule['table_name'] = ( ! empty( $field[0] ) ) ? $field[0] : '';
								$rule['col_name'] = ( ! empty( $field[1] ) ) ? $field[1] : '';
							}

                            $search_col = (!empty($rule['col_name'])) ? $rule['col_name'] : '';
							$selected_search_operator = (!empty($rule['operator'])) ? $rule['operator'] : '';
							$search_operator = ( ! empty( $this->advance_search_operators[$selected_search_operator] ) ) ? $this->advance_search_operators[$selected_search_operator] : $selected_search_operator;
                            $search_data_type = ( ! empty( $search_cols_type[$rule['type']] ) ) ? $search_cols_type[$rule['type']] : 'text';
                            $search_value = (isset($rule['value']) && $rule['value'] != "''") ? $rule['value'] : ( ( in_array( $search_data_type, array( "number", "numeric" ) ) ) ? "''" : '');

                            if( !empty( $params['data_col_params'] ) ) {
                            	$search_value = ( in_array( $search_col, $params['data_col_params']['data_cols_timestamp'] ) || in_array( $search_col, $params['data_col_params']['data_date_cols_timestamp'] ) || in_array( $search_col, $params['data_col_params']['data_time_cols_timestamp'] ) ) ? strtotime( $search_value ) : $search_value;

								// Added code to convert value to UTC for data which is displayed in site timezone
								$search_value = ( in_array( $search_col, $params['data_col_params']['data_cols_display_date_in_site_timezone'] ) ) ? sa_sm_get_utc_timestamp_from_site_date( $search_value ) : $search_value;
                            }

							// Code to create advanced search condition
							$table_name = ( ! empty( $rule['table_name'] ) ) ? substr( $rule['table_name'], strlen( $wpdb->prefix ) ) : '';
							
							if( empty( $table_name ) ){
								continue;
							}

							if( 'terms' === $table_name && "''" === $search_value ){ // For handling taxonomy empty strings
								switch( $search_operator ){
									case 'is':
										$search_operator = 'is not';
										break;
									case 'is not':
										$search_operator = 'is';
										break;
								}
							}
							
							$search_params = array('search_string' => $rule,
													'search_col' => $search_col,
													'search_operator' => $search_operator, 
													'search_data_type' => $search_data_type, 
													'search_value' => $search_value,
													'selected_search_operator' => $selected_search_operator,
													'SM_IS_WOO30' => (!empty($params['SM_IS_WOO30'])) ? $params['SM_IS_WOO30'] : '',
													'post_type' => (!empty($params['post_type'])) ? $params['post_type'] : array());
                            if ( ! empty( $this->advanced_search_table_types['flat'] ) && in_array( $table_name, array_keys( $this->advanced_search_table_types['flat'] ) ) ) {
								$advanced_search_query[$i] = $this->create_flat_table_search_query( array(
									'table_nm'	=> $table_name,
									'search_query' => $advanced_search_query[$i],
									'search_params' => $search_params,
									'rule'			=> $rule
								) );
                            } else if ( ! empty( $this->advanced_search_table_types['meta'] ) && in_array( $table_name, array_keys( $this->advanced_search_table_types['meta'] ) ) ) {
								$advanced_search_query[$i] = $this->create_meta_table_search_query( array(
									'table_nm'	=> $table_name,
									'search_query' => $advanced_search_query[$i],
									'search_params' => array_merge( $search_params, array( 'is_meta_table' => true, 'pkey' => ( ! empty( $params['pkey'] ) ) ? $params['pkey'] : 'post_id',
									'join_table' => ( ! empty( $params['join_table'] ) ) ? $params['join_table'] : 'posts',
									'type' => ( ! empty( $params['type'] ) ) ? $params['type'] : 'post_type' ) ),
									'rule'			=> $rule,
								) );
                            } else if ( !in_array( $table_name, array_keys( $this->advanced_search_table_types['flat'] ) ) && 'terms' === $table_name ) {
                                $advanced_search_query[$i] = $this->create_terms_table_search_query( array(
									'search_query' => $advanced_search_query[$i],
									'search_params' => $search_params,
									'rule'			=> $rule
								) );
                            }
                            $advanced_search_query[$i] = apply_filters( 'sm_search_query_formatted', $advanced_search_query[$i], $search_params );
                        }

						if( ! empty( $advanced_search_query[$i] ) ){
							foreach( $advanced_search_query[$i] as $key => $value ){
								if ( ( is_array( $value ) ) || ( " && " !== substr( $value, -4 ) ) ) {
									continue;
								}
								$advanced_search_query[$i][$key] = ( ! empty( $value ) ) ? substr( $value, 0, -4 ) : '';
							}
						}
                    }
                    $i++;
				}

                //Code for handling advanced search conditions
		        if( ! empty( $advanced_search_query ) ) {

		            $index_search_string = 1; // index to keep a track of flags in the advanced search temp 
		            $search_params = array();
		            foreach( $advanced_search_query as &$advanced_search_query_string ) {
		            	$this->previous_cond_has_results = true;
						foreach( $advanced_search_query_string as $key => $value ){

							if( empty( $value ) ){
								continue;
							}

							$key = substr( $key, strlen( 'cond_' ) );
							$search_vals = ( ! empty( $advanced_search_query_string[ 'cond_'. $key . '_col_vals'] ) ) ? $advanced_search_query_string[ 'cond_'. $key . '_col_vals'] : array();
							$selected_search_operators = ( ! empty( $advanced_search_query_string[ 'cond_'. $key . '_selected_search_operators'] ) ) ? $advanced_search_query_string[ 'cond_'. $key . '_selected_search_operators'] : array();
							$search_params = array(
								'search_vals'            => $search_vals,
								'selected_search_operators' => $selected_search_operators
							);
							if ( ! empty( $this->advanced_search_table_types['flat'] ) && in_array( $key, array_keys( $this->advanced_search_table_types['flat'] ) ) ) {
								$this->process_flat_table_search_query( array_merge( $params, array(
									'search_query' 			=> $advanced_search_query_string,
									'search_query_index' 	=> $index_search_string,
									'table_nm'				=> $key,
									'key_col'				=> $this->advanced_search_table_types['flat'][$key]
								), $search_params ) );
							} else if ( ! empty( $this->advanced_search_table_types['meta'] ) && in_array( $key, array_keys( $this->advanced_search_table_types['meta'] ) ) ) {
								$this->process_meta_table_search_query( array_merge( $params, array(
									'search_query' 			=> $advanced_search_query_string,
									'search_query_index' 	=> $index_search_string,
									'table_nm'				=> $key,
									'meta_key_col'			=> $this->advanced_search_table_types['meta'][$key]
								), $search_params ) );
							} else if ( !in_array( $key, array_keys( $this->advanced_search_table_types['flat'] ) ) && 'terms' === $key ) {
								$this->process_terms_table_search_query( array_merge( $params, array( 
									'search_query' 			=> $advanced_search_query_string,
									'search_query_index' 	=> $index_search_string
								), $search_params ) );
							}
						}
		                $index_search_string++;
		            }
		        }

				do_action( 'sm_advanced_search_processing_complete' ); //action for doing any post processing work
		}

		/**
	     * Function to get the data model for the dashboard.
		 * @param  array $col_model column model array.
		 * @return array $data_model updated data model array.
		 */
		public function get_data_model( $col_model = array() ) {
			global $wpdb, $current_user;
			$data_model = array();
			$is_view = ( ! empty ( $this->req_params['is_view'] ) ) ? $this->req_params['is_view'] : '';
			$dashboard_type = ( ! empty( $this->req_params['is_taxonomy'] ) ) ? 'taxonomy' : 'post_type';
			$dashboard_type = ( ! empty( $is_view ) ) ? 'view' : $dashboard_type;
			$dashboard_slug = ( ! empty ( $this->req_params['active_module'] ) ) ? $this->req_params['active_module'] : '';
			$view_slug = ( ! empty ( $this->req_params['active_view'] ) ) ? $this->req_params['active_view'] : '';
			$slug = ( ! empty( $is_view ) ) ? $view_slug : $dashboard_slug;
			if ( ! empty( $dashboard_type ) ) {
				// Code to update the recent accessed dashboards
				sa_sm_update_recent_dashboards( ( ( 'taxonomy' === $dashboard_type ) ? 'taxonomies' : $dashboard_type.'s' ), $slug );
				// code to update recently accessed dashboard type
				update_user_meta( get_current_user_id(), 'sa_sm_recent_dashboard_type', $dashboard_type );
			}
			$column_model_transient = ( ! empty( $this->store_col_model_transient_option_nm ) ) ? get_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, true ) : array();
			// Code for handling views
			$is_view_contain_search_params = false;
			if( ( defined('SMPRO') && true === SMPRO ) && ! empty( $this->req_params['is_view'] ) && ! empty( $this->req_params['active_view'] ) ) {
				if( class_exists( 'Smart_Manager_Pro_Views' ) ) {
					$view_obj = Smart_Manager_Pro_Views::get_instance();
					if( is_callable( array( $view_obj, 'get' ) ) ){
						$view_slug = $this->req_params['active_view'];
						$view_data = $view_obj->get($view_slug);
						if( ! empty( $view_data ) ) {
							$this->dashboard_key = $view_data['post_type'];
							$column_model_transient = json_decode( $view_data['params'], true );
							
							if( !empty( $column_model_transient['search_params'] ) ) {
								if( ! empty( $column_model_transient['search_params']['isAdvanceSearch'] ) && "true" == $column_model_transient['search_params']['isAdvanceSearch'] ) { // For advanced search
									if( ! empty( $column_model_transient['search_params']['params'] ) && is_array( $column_model_transient['search_params']['params'] ) ) {
										// array_walk(
										// 	$column_model_transient['search_params']['params'],
										// 	function ( &$value ) {
										// 		$value = ( ! empty( $value ) ) ? addslashes( json_encode( $value ) ) : '';
										// 	}
										// );
										$this->req_params['advanced_search_query'] = addslashes( json_encode( $column_model_transient['search_params']['params']) );
										$is_view_contain_search_params = ( ! empty( $this->req_params['advanced_search_query'] ) && '[]' !== $this->req_params['advanced_search_query'] ) ? true : $is_view_contain_search_params;
									}
								} else { //for simple search
									$this->req_params['search_text'] = $column_model_transient['search_params']['params'];
									$is_view_contain_search_params = ( ! empty( $this->req_params['search_text'] ) ) ? true : $is_view_contain_search_params;
								}
							}
						}
					}
				}
			}

			// code for assigning the sort params
			if( ! empty( $column_model_transient ) && empty( $this->req_params['sort_params'] ) ){
				$this->req_params['sort_params'] = ( ! empty( $column_model_transient['sort_params'] ) ) ? $column_model_transient['sort_params'] : array();
			}
			
			$store_model_transient = ( ! empty( $this->store_col_model_transient_option_nm ) ) ? get_transient( $this->store_col_model_transient_option_nm ) : '';

			if( ! empty( $store_model_transient ) && !is_array( $store_model_transient ) ) {
				$store_model_transient = json_decode( $store_model_transient, true );
			} else {
				$store_model_transient = $this->get_dashboard_model( true );
			}

			if( !empty( $column_model_transient ) && !empty( $store_model_transient ) ) {
				$store_model_transient = $this->map_column_to_store_model( $store_model_transient, $column_model_transient );
			}
			$col_model = ( ! empty( $this->req_params[ 'columnsToBeExported' ] ) && ! empty( $col_model ) && is_array( $col_model ) ) ? $col_model : ( ( ! empty( $store_model_transient['columns'] ) ) ? $store_model_transient['columns'] : array() );

			$required_cols = apply_filters('sm_required_cols', array());

			$load_default_data_model = apply_filters('sm_beta_load_default_data_model', true);

			//Code for getting the relevant columns
			$data_cols_dropdown = array();
			$data_cols_multilist = array();
			$data_cols_longstring = array();
			$data_cols_serialized = array();
			$data_cols_checkbox = array();
			$data_cols_unchecked_template = array();
			$data_cols_timestamp = array();
			$data_date_cols_timestamp = array();
			$data_time_cols_timestamp = array();
			$data_cols_display_date_in_site_timezone = array();
			$data_cols_datetime = array();
			$data_cols_multi_select2 = array();
			$numeric_postmeta_cols_decimal_places = array();
			$view_edit_cols = array( 'custom_view_link', 'custom_edit_link' );

			$data_cols = array('ID');
			$postmeta_cols = array();
			$numeric_postmeta_cols = array();
			$image_postmeta_cols = array();
			$multiple_image_postmeta_cols = array();
			$terms_visible_cols = array();
			$visible_cols = array(); //array for all visible cols

			$search_cols_type = array(); //array for col & its type for advanced search

			if (!empty($col_model)) {
				foreach ($col_model as $col) {

					$validator = ( !empty( $col['validator'] ) ) ? $col['validator'] : '';
					$type = ( !empty( $col['type'] ) ) ? $col['type'] : '';

					if( ! empty( $col['table_name'] ) && ! empty( $col['col_name'] ) ){
						// added $validator condition for spl cols like '_regular_price', '_sale_price', etc.
						$search_cols_type[ $col['table_name'] .'.'. $col['col_name'] ] = ( ( "customNumericTextEditor" === $validator && "text" == $type ) || ( ! empty( $col['date_type'] ) && 'timestamp' === $col['date_type'] ) ) ? 'numeric' : $type;
						$search_cols_type[ $col['table_name'] .'.'. $col['col_name'] ] = ( ! empty( $col['search_type'] ) ) ? $col['search_type'] : $search_cols_type[ $col['table_name'] .'.'. $col['col_name'] ]; //Code to handle sp. search data type passed for any col
					}

					if( !empty( $col['hidden'] ) && !empty( $col['data'] ) && array_search($col['data'], $required_cols) === false ) {
						continue;
					}

					$col_exploded = (!empty($col['src'])) ? explode("/", $col['src']) : array();

					if (empty($col_exploded)) continue;
					
					$visible_cols[] = $col;

					if ( sizeof($col_exploded) > 2) {
						$col_meta = explode("=",$col_exploded[1]);
						$col_nm = $col_meta[1];
					} else {
						$col_nm = $col_exploded[1];
					}

					$editor = ( !empty( $col['editor'] ) ) ? $col['editor'] : '';
					$data_cols[] = ( in_array( $col['data'], $view_edit_cols ) ) ? $col['data'] : $col_nm;

					if( !empty( $col_exploded[0] ) && $col_exploded[0] == 'postmeta' && $col_nm != 'post_id' ) {
						$postmeta_cols[] = $col_nm;

						if( ( $type == 'number' || $type == 'numeric' || $validator == 'customNumericTextEditor' ) && 'sm.image' !== $type ) {
							if( isset( $col['decimalPlaces'] ) ) {
								$numeric_postmeta_cols_decimal_places[ $col_nm ] = $col['decimalPlaces'];
							}
							$numeric_postmeta_cols[] = $col_nm;
						}

						if( 'sm.image' === $type ){
							$image_postmeta_cols[] = $col_nm;
						} else if( 'sm.multipleImage' === $type ){
							$multiple_image_postmeta_cols[] = $col_nm;
						}

					}

					// Code for storing the serialized cols
					if( $type == 'sm.serialized' ) {
						$data_cols_serialized[] = $col_nm;
						if( $editor == 'text' ) {
							$data_cols_serialized_text_editor[ $col_nm ] = ( !empty( $col['separator'] ) ? $col['separator'] : ',' );
						}

					} if( $type == 'sm.longstring' ) {
						$data_cols_longstring[] = $col_nm;
					} else if( $type == 'sm.multilist' ) {
						$data_cols_multilist[] = $col_nm;
					} else if( $editor == 'select2' && !empty( $col['select2Options']['multiple'] ) ) {
						$data_cols_multi_select2[ $col['data'] ] = ( !empty( $col['separator'] ) ? $col['separator'] : '' );
					} else if( $type == 'dropdown' ) {
						$data_cols_dropdown[] = $col_nm;
					} else if( $type == 'checkbox' ) {
						$data_cols_checkbox[] = $col_nm;
						if( !empty( $col['uncheckedTemplate'] ) ) {
							$data_cols_unchecked_template[$col_nm] = $col['uncheckedTemplate'];
						}
					} else if( $type == 'sm.datetime' && !empty( $col['date_type'] ) && $col['date_type'] == 'timestamp' ) {
						$data_cols_timestamp[] = $col_nm;
					} else if( $type == 'sm.date' && !empty( $col['date_type'] ) && $col['date_type'] == 'timestamp' ) {
						$data_date_cols_timestamp[] = $col_nm;
					} else if( $type == 'sm.time' && !empty( $col['date_type'] ) && $col['date_type'] == 'timestamp' ) {
						$data_time_cols_timestamp[] = $col_nm;
					} else if( $type == 'sm.datetime' && empty( $col['date_type'] ) ) {
						$data_cols_datetime[] = $col_nm;
					}

					// Code for saving the taxonomy names
					if ($col_exploded[0] == 'terms' && $col_nm != 'object_id' ) {
						$terms_visible_cols[$col_nm] = ( ! empty( $col['values'] ) ) ? $col['values'] : array();
					}
					if( ! empty( $col['is_display_date_in_site_timezone'] ) ){
						$data_cols_display_date_in_site_timezone[] = $col_nm;
					}
				}
			}

			// Code for setting limit & offset
			$start = (!empty($this->req_params['start'])) ? $this->req_params['start'] : 0;
			$limit = ( ! empty( $this->req_params['cmd'] ) && 'get_export_csv' === $this->req_params['cmd'] && ( ! empty( $this->req_params['storewide_option'] ) ) ) ? -1 : ( ( ! empty( $this->req_params['sm_limit'] ) ) ? $this->req_params['sm_limit'] : 50 );
			$current_page = (!empty($this->req_params['sm_page'])) ? $this->req_params['sm_page'] : '1';
			$start_offset = ($current_page > 1) ? (($current_page - 1) * $limit) : $start;

			$data_col_params = array( 	
										'limit'										=> $limit,
										'offset'									=> $start_offset,
										'current_page'								=> $current_page,
										'data_cols' 								=> $data_cols,
										'required_cols' 							=> $required_cols,
										'data_cols_serialized' 						=> $data_cols_serialized,
									 	'data_cols_longstring' 						=> $data_cols_longstring,
									 	'data_cols_multilist' 						=> $data_cols_multilist,
									 	'data_cols_dropdown' 						=> $data_cols_dropdown,
									 	'data_cols_checkbox'						=> $data_cols_checkbox,
									 	'data_cols_timestamp' 						=> $data_cols_timestamp,
									 	'data_date_cols_timestamp' 					=> $data_date_cols_timestamp,
									 	'data_time_cols_timestamp' 					=> $data_time_cols_timestamp,
									 	'data_cols_display_date_in_site_timezone' 	=> $data_cols_display_date_in_site_timezone,
									 	'data_cols_datetime' 						=> $data_cols_datetime,
									 	'data_cols_multi_select2' 					=> $data_cols_multi_select2,
									 	'data_cols_numeric_decimal_places' 			=> $numeric_postmeta_cols_decimal_places,
										'col_model'									=> $col_model,
										'search_cols_type'							=> $search_cols_type,
										'visible_cols'								=> $visible_cols,
										'terms_visible_cols'                        => $terms_visible_cols,
										'advance_search_operators'					=> $this->advance_search_operators
									);

			if( $load_default_data_model ) { //condition to skip the default data model
				$this->req_params['table_model'] = ( empty( $this->req_params['table_model'] ) && ! empty( $store_model_transient['tables'] ) ) ? $store_model_transient['tables'] : $this->req_params['table_model'];

				$post_cond = (!empty($this->req_params['table_model']['posts']['where'])) ? $this->req_params['table_model']['posts']['where'] : array('post_type' => $this->dashboard_key, 'post_status' => 'any' );
				$meta_query = (!empty($this->req_params['table_model']['postmeta']['where'])) ? $this->req_params['table_model']['postmeta']['where'] : '';
				$tax_query = (!empty($this->req_params['table_model']['terms']['where'])) ? $this->req_params['table_model']['terms']['where'] : '';

				// Condition to handle display of 'trash' records
				if( ! empty( $this->is_show_trash_records() ) && ! empty( $post_cond['post_status'] ) ){
					$post_cond['post_status'] = ( ! is_array( $post_cond['post_status'] ) ) ? array( $post_cond['post_status'] ) : $post_cond['post_status'];
					$post_cond['post_status'] = array_merge( $post_cond['post_status'], array( 'trash' ) );
				}

				//Code for advanced search
				$search = "";
				$search_condn = "";

		        //Code to clear the advanced search temp table
		        if( empty($this->req_params['advanced_search_query'])) {
		            $wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp");
		            delete_option('sm_advanced_search_query');
		        }

				// TODO: revise this code as per new advanced search
		        // if( !empty($this->req_params['date_filter_query']) && ( defined('SMPRO') && true === SMPRO ) ) {

		        // 	if( empty($this->req_params['search_query']) ) {
		        // 		$this->req_params['search_query'] = array( $this->req_params['date_filter_query'] );
		        // 	} else {

		        // 		$date_filter_array = json_decode(stripslashes($this->req_params['date_filter_query']),true);

		        // 		foreach( $this->req_params['search_query'] as $key => $search_string_array ) {
		        // 			$search_string_array = json_decode(stripslashes($search_string_array),true);

		        // 			foreach( $date_filter_array as $date_filter ) {
				// 				$search_string_array[] = $date_filter;		
		        // 			}

		        // 			$this->req_params['search_query'][$key] = addslashes(json_encode($search_string_array));
		        // 		}
		        // 	}
		        // }

		        $sm_advanced_search_results_persistent = 0; // flag to handle persistent search results		
				if ( ! empty( $this->req_params['table_model']['posts']['where']['post_type'] ) ) {
					$post_type = ( is_array( $this->req_params['table_model']['posts']['where']['post_type'] ) ) ? $this->req_params['table_model']['posts']['where']['post_type'] : array( $this->req_params['table_model']['posts']['where']['post_type'] );
				}
		        //Code fo handling advanced search functionality
		        if( !empty( $this->req_params['advanced_search_query'] ) && $this->req_params['advanced_search_query'] != '[]' ) {

					$this->req_params['advanced_search_query'] = json_decode(stripslashes($this->req_params['advanced_search_query']), true);

		            if (!empty($this->req_params['advanced_search_query'])) {

						$this->process_search_cond( array( 'post_type' => $post_type,
														'search_query' => (!empty($this->req_params['advanced_search_query'])) ? $this->req_params['advanced_search_query'] : array(),
		            									'SM_IS_WOO30' => (!empty($this->req_params['SM_IS_WOO30'])) ? $this->req_params['SM_IS_WOO30'] : '',
														'search_cols_type' => $search_cols_type,
														'data_col_params' => $data_col_params ) );

		            }

		        }

				// Code for handling sorting of the postmeta
		        $sort_params = $this->build_query_sort_params( 
					array( 
						'sort_params' => $this->req_params['sort_params'],
						'numeric_meta_cols' => $numeric_postmeta_cols,
						'data_cols' => $data_cols
					) 
				);

				//WP_Query to get all the relevant post_ids
				$args = array(
					            'posts_per_page' => $limit,
					            'offset' => $start_offset,
					            'meta_query' => array( $meta_query ),
					            'tax_query' => array( $tax_query ),
					            'orderby' => ( !empty( $sort_params['column_nm'] ) ? $sort_params['column_nm'] : '' ),
					            'order' => ( !empty( $sort_params['sortOrder'] ) ? $sort_params['sortOrder'] : '' ),
								'sm_sort_params' => ( !empty( $sort_params ) ? $sort_params : array() )
							);

				$args = array_merge($args, $post_cond);
				$can_optimize_dashboard_speed = apply_filters( 'sm_can_optimize_dashboard_speed', false );
				if ( ! empty( $can_optimize_dashboard_speed ) ) {
					$exclude_statuses = in_array( 'trash', (array) $post_cond['post_status'], true ) ? array( 'auto-draft' ) : array( 'trash', 'auto-draft' );
					$join = apply_filters( 'sm_posts_join_paged', '', $sort_params );
					$fields = apply_filters( 'sm_posts_fields', "{$wpdb->prefix}posts.*", $sort_params );
					$where = "{$wpdb->prefix}posts.post_type IN (" . implode( ', ', array_fill( 0, count( $post_type ), '%s') ) . ") AND {$wpdb->prefix}posts.post_status NOT IN (" . implode( ', ', array_fill( 0, count( $exclude_statuses ), '%s') ) . ")";
					$where_cond = apply_filters( 'sm_posts_where', $where );
					$where = ( ! empty( $where_cond[ 'sql' ] ) ) ? " AND " . $where_cond[ 'sql' ] : "";
					$params = array_merge( $post_type, $exclude_statuses );
					$params = ( ! empty( $where_cond[ 'value' ] ) ) ? array_merge( $params, $where_cond[ 'value' ] ) : $params;
					$group_by = apply_filters( 'sm_posts_groupby',  "{$wpdb->prefix}posts.ID" );
					$group_by = ( ! empty( $group_by ) ) ? " GROUP BY " . $group_by : "";
					$not_allowed_orderby_cols = array( 'meta_key' );
					$order_by = ( ! empty( $sort_params['column_nm'] ) ) ? ( in_array( $sort_params['column_nm'], $not_allowed_orderby_cols ) ? "{$wpdb->prefix}posts.post_date " . $sort_params['sortOrder'] : '' ) : '';
					$order_by = apply_filters( 'sm_posts_orderby', $order_by, array(
						'sort_params' => $sort_params,
						'start_offset' => $start_offset,
						'limit' => $limit
					) );
					$order_by = ( ! empty( $order_by ) ) ? " ORDER BY " . $order_by : "";
					$limits = "";
					$placeholder_params = $params;
					if ( ( ! empty( $limit ) ) && ( -1 !== $limit ) ) {
						$limits = " LIMIT %d, %d";
						$placeholder_params = array_merge( $params, array( $start_offset, $limit ) );
					}
					$query = $wpdb->prepare( "SELECT $fields
						FROM {$wpdb->prefix}posts AS {$wpdb->prefix}posts
						$join
						WHERE 1=1 $where
						$group_by
						$order_by
						$limits
					", $placeholder_params );
					$posts_data = $wpdb->get_results(
						$query, ARRAY_A
					);
					do_action( 'sm_found_posts', $query );
					// To get total count.
					$total_count = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(DISTINCT {$wpdb->prefix}posts.id)
							FROM {$wpdb->prefix}posts AS {$wpdb->prefix}posts
							$join
							WHERE 1=1 $where",
							$params
						)
					);
				} else {
					$result_posts = new WP_Query( $args );
					$posts_data = $result_posts->posts;
					$total_count = $result_posts->found_posts;
				}
				// Code for saving the post_ids in case of simple search/advanced search.
				if ( ( defined('SMPRO') && true === SMPRO ) && ! empty( $this->req_params['search_text'] ) || ( ! empty($this->req_params['advanced_search_query'] ) && $this->req_params['advanced_search_query'] != '[]' ) ) {
					if ( ! empty( $can_optimize_dashboard_speed ) ) {
						$ids = $wpdb->get_col( $wpdb->prepare( "SELECT {$wpdb->prefix}posts.ID
							FROM {$wpdb->prefix}posts AS {$wpdb->prefix}posts
							$join WHERE 1=1 $where
							$group_by
							ORDER BY {$wpdb->prefix}posts.post_date DESC
						", $params ) );
						$ids = ( ( ! empty( $ids ) ) && is_array( $ids ) ) ? implode( ",", $ids ) : '';	
					} else {
						$search_query_args = array_merge( $args, array( 'posts_per_page' => -1,
						'fields' => 'ids' ) );
						unset( $search_query_args['offset'] );
						$search_results = new WP_Query( $search_query_args );
						$ids = implode( ",", $search_results->posts );
					}
					set_transient( 'sa_sm_search_post_ids', $ids , WEEK_IN_SECONDS );
				}
				$items = array();
				$post_ids = array();
				$index_ids = array();
	        	$index = 0;
	        	$total_pages = 1;
	        	if ($total_count > $limit) {
	        		$total_pages = ceil($total_count/$limit);
	        	}
	        	if ( !empty( $posts_data ) ) {
	        		foreach ($posts_data as $key => $value) {

	        			$post = ( ! empty( $can_optimize_dashboard_speed ) ) ? $value : ( array ) $value;
						$id_val = ( ! empty( $can_optimize_dashboard_speed ) ) ? $value['ID'] : $value->ID;
	        			foreach ($post as $post_key => $post_value) {

	        				if ( is_array( $data_cols ) && !empty( $data_cols ) ) {
	        					if ( array_search( $post_key, $data_cols ) === false ) {
	        						continue; //cond for checking col in col model	
	        					}
	        				}

	        				if ( is_array( $data_cols_checkbox ) && !empty( $data_cols_checkbox ) ) {
	        					if( array_search( $post_key, $data_cols_checkbox ) !== false && $post_value == '' ) { //added for bad_value checkbox
	        						$post_value = $data_cols_unchecked_template[$post_key];
	        					}
	        				}

							if( is_array( $data_cols_serialized ) && !empty( $data_cols_serialized ) ) {
        						if( in_array( $post_key, $data_cols_serialized ) ) {
									$post_value = maybe_unserialize( $post_value );
									if( !empty( $post_value ) ) {
										$post_value = ( !empty( $data_cols_serialized_text_editor[$post_key] ) ) ? implode($data_cols_serialized_text_editor[$post_key], $post_value) : json_encode( $post_value );
									}
		        				}
        					}

	        				$key = 'posts_'.strtolower(str_replace(' ', '_', $post_key));
	        				$items [$index][$key] = $post_value;
	        			}

	        			//Code for generating the view & edit links for the post
	        			if ( is_array( $data_cols ) && !empty( $data_cols ) ) {
	        				foreach( $view_edit_cols as $col ) {
	        					if ( array_search( $col, $data_cols ) ) {
        							$link = ( 'custom_view_link' === $col ) ? get_permalink( $id_val ) : get_edit_post_link( $id_val, '' );
	        						$items [$index]['custom_'. ( ( 'custom_view_link' === $col ) ? 'view' : 'edit' ) .'_link'] = ( !empty( $this->req_params['cmd'] ) && $this->req_params['cmd'] != 'get_export_csv' && $this->req_params['cmd'] != 'get_print_invoice' ) ? '<a href="'.$link.'" target="_blank" style="text-decoration:none !important; color:#5850ecc2 !important;"><span class="dashicons dashicons-external"></span></a>' : $link;
	        					}
	        				}
        				}

						$post_ids[] = $id_val;
						$index_ids[ $id_val ] = $index;
						$index++;
	        		}
	        	}

	        	//Code for getting the postmeta data
	        	if( !empty( $post_ids ) && !empty( $postmeta_cols ) ) {

	        		if( !empty( $items ) ) { //Code to create and initialize all the meta columns
	        			foreach ( $items as $key => $item ) {
	        				foreach ( $postmeta_cols as $col ) {
	        					$meta_key = 'postmeta_meta_key_'.$col.'_meta_value_'.$col;
	        					$meta_value = '';

	        					//Code for handling checkbox data
	        					if( is_array( $data_cols_checkbox ) && !empty( $data_cols_checkbox ) && is_array( $data_cols_unchecked_template ) && !empty( $data_cols_unchecked_template ) ) {
	        						if( in_array( $col, $data_cols_checkbox ) && !empty( $data_cols_unchecked_template[ $col ] ) ) { //added for bad_value checkbox
			        					$meta_value = $data_cols_unchecked_template[ $col ];
			        				}
	        					}

	        					$items [$key][$meta_key] = $meta_value;	
	        				}
	        			}
	        		}

					$postmeta_data = array();
					//TODO: Check Not working on client site
					if( count( $post_ids ) > 100 ) {



						$post_id_chunks = array_chunk( $post_ids, 100 );

						foreach( $post_id_chunks as $id_chunk ){
							$results = $wpdb->get_results( $wpdb->prepare( "SELECT post_id as post_id,
											                              meta_key AS meta_key,
											                              meta_value AS meta_value
											                    FROM {$wpdb->prefix}postmeta as prod_othermeta 
											                    WHERE post_id IN (". implode(",",$id_chunk) .")
											                    	AND meta_key IN ('". implode("','", $postmeta_cols) ."')
											                    	AND 1=%d
																GROUP BY post_id, meta_key", 1 ), 'ARRAY_A' );
							
							if( ! empty( $results ) ) {
								$postmeta_data = array_merge( $postmeta_data, $results );
							}
						}
				
					} else {
						$postmeta_data = $wpdb->get_results( $wpdb->prepare( "SELECT post_id as post_id,
											                              meta_key AS meta_key,
											                              meta_value AS meta_value
											                    FROM {$wpdb->prefix}postmeta as prod_othermeta 
											                    WHERE post_id IN (". implode(",",$post_ids) .")
											                    	AND meta_key IN ('". implode("','", $postmeta_cols) ."')
											                    	AND 1=%d
																GROUP BY post_id, meta_key", 1 ), 'ARRAY_A' );
					}

	        		if( !empty( $postmeta_data ) ) {

	        			foreach( $postmeta_data as $data ) {
	        				$index = ( isset( $index_ids[$data['post_id']] ) ) ? $index_ids[$data['post_id']] : '';

	        				if( '' === $index ) {
	        					continue;
	        				}

	        				$items [$index]['postmeta_post_id'] = $data['post_id'];

	        				$meta_key = ( isset( $data['meta_key'] ) ) ? $data['meta_key'] : '';

	        				if( empty( $meta_key ) ) {
	        					continue;
	        				}
	        				
	        				if( !in_array( $data['meta_key'], $data_cols_checkbox ) ) {
								$meta_value = ( isset( $data['meta_value'] ) ) ? $data['meta_value'] : '';
	        				} else {
	        					$meta_value = ( isset( $data['meta_value'] ) && $data['meta_value'] != '' ) ? $data['meta_value'] : $items[$key]['postmeta_meta_key_'.$meta_key.'_meta_value_'.$meta_key];
	        				}

	        				//Code for handling serialized data
        					if( is_array( $data_cols_serialized ) && !empty( $data_cols_serialized ) ) {
        						if( in_array( $meta_key, $data_cols_serialized ) ) {
									$meta_value = maybe_unserialize( $meta_value );
									if( !empty( $meta_value ) ) {
										$meta_value = ( !empty( $data_cols_serialized_text_editor[$meta_key] ) && ! empty( $meta_value ) && is_array( $meta_value ) ) ? implode($data_cols_serialized_text_editor[$meta_key], $meta_value) : json_encode( $meta_value );
									}
		        				}
        					}

        					//Code for handling timestamp data
        					if( ( is_array( $data_cols_timestamp ) && !empty( $data_cols_timestamp ) ) || ( is_array( $data_date_cols_timestamp ) && !empty( $data_date_cols_timestamp ) ) || ( is_array( $data_time_cols_timestamp ) && !empty( $data_time_cols_timestamp ) )  ) {
        						if( ( in_array( $meta_key, $data_cols_timestamp ) || in_array( $meta_key, $data_date_cols_timestamp ) || in_array( $meta_key, $data_time_cols_timestamp ) ) && !empty( $meta_value ) && is_numeric( $meta_value ) ) {

        							$format = 'Y-m-d H:i:s';
        							if( in_array( $meta_key, $data_date_cols_timestamp ) ) {
        								$format = 'Y-m-d';
        							} else if ( in_array( $meta_key, $data_time_cols_timestamp ) ) {
        								$format = 'H:i';
        							}

									$meta_value = ( in_array( $meta_key, $data_cols_display_date_in_site_timezone ) ) ? sa_sm_get_site_timestamp_from_utc_date( $meta_value ) : $meta_value;

									$date = new DateTime();
									$date->setTimestamp($meta_value);

									$meta_value = $date->format($format);
        						}
        					}

        					//Code for handling blank date values
        					if( is_array( $data_cols_datetime ) && !empty( $data_cols_datetime ) ) {
        						if( in_array( $meta_key, $data_cols_datetime ) && empty( $meta_value ) && is_numeric( $meta_value ) ) {
        							$meta_value = '-';
        						}
							}	

							// //Code for handling blank numeric fields
        					// if( in_array( $meta_key, $numeric_postmeta_cols ) && ! isset( $numeric_postmeta_cols_decimal_places[$meta_key] ) ) {
        					// 	$meta_value = ( ! empty( $meta_value ) ) ? $meta_value : 0;
        					// }
							
							//Code for handling image fields
        					if( in_array( $meta_key, $image_postmeta_cols ) ) {
								if( ! empty( $meta_value ) ){
									$attachment = wp_get_attachment_image_url( $meta_value, 'full' );
									$meta_value = ( ! empty( $attachment ) ) ? $attachment : '';
								}
							}

							//Code for handling multiple image fields
        					if( in_array( $meta_key, $multiple_image_postmeta_cols ) ) {
								if( ! empty( $meta_value ) ){
									$image_ids = ( ! is_array( $meta_value ) ) ? explode( ",", $meta_value ) : array();
									if( !empty( $image_ids ) ) {
										$meta_value = array();
										$img_url = '';
										foreach( $image_ids as $image_id ) {
											$img_url = wp_get_attachment_image_url( $image_id, 'full' );
											$meta_value[] = ( !empty( $this->req_params['cmd'] ) && $this->req_params['cmd'] == 'get_export_csv' ) ? $img_url : array( 'id' => $image_id, 'val' => $img_url );
										}
									}
								}
							}
							
        					//Code for rounding of integer fields
        					if( isset( $numeric_postmeta_cols_decimal_places[$meta_key] ) && !empty( $meta_value ) ) {
        						$meta_value = round( intval( $meta_value ), intval( $numeric_postmeta_cols_decimal_places[$meta_key] ) );
        					}

        					$meta_key = sanitize_title($meta_key);

	        				$meta_key = 'postmeta_meta_key_'.$meta_key.'_meta_value_'.$meta_key;
	        				$items [$index][$meta_key] = $meta_value;
	        			}
	    			}
	        	}
	        	// Handling multilist data.
	        	$items = $this->format_terms_data( 
	        		array(
	        			'items'               => $items,
	        			'terms_visible_cols'  => $terms_visible_cols,
	        			'data_cols_multilist' => $data_cols_multilist,
	        			'data_cols_dropdown'  => $data_cols_dropdown,
	        			'ids'                 => $post_ids,
	        			'id_name'             => 'posts_id',
	        			'postmeta_cols'       => $postmeta_cols
	        		)
	        	);
	        	foreach( $items as $key => $item ) {
	        		//Code for handling multi-select2 columns
	        		foreach( $data_cols_multi_select2 as $col => $separator ) {
						if( isset( $item[ $col ] ) ) {
							if( !empty( $separator ) ) {
								$val = explode( $separator, $item[ $col ] );
							} else { //for serialized strings
								$val = maybe_unserialize( $item[ $col ] );
							}

							if( is_array( $val ) ) {
								$items[$key][ $col ] = implode(',', $val);
							} else {
								$items[$key][ $col ] = '';
							}
						}
					}
	        	}
	        	$data_model ['items'] = (!empty($items)) ? $items : '';
	        	$data_model ['start'] = $start+$limit;
	        	$data_model ['page'] = $current_page;
	        	$data_model ['total_pages'] = $total_pages;
	        	$data_model ['total_count'] = $total_count;
			}

			$data_model ['meta'] = array( 'is_view_contain_search_params' => $is_view_contain_search_params ); //added to pass any meta to FE
        	//Filter to modify the data model
			$data_model = apply_filters( 'sm_data_model', $data_model, $data_col_params );
			if( !empty( $this->req_params['cmd'] ) && ( $this->req_params['cmd'] == 'get_export_csv' || $this->req_params['cmd'] == 'get_print_invoice' ) ) {
				return $data_model;
			} else {
				wp_send_json( $data_model );
			}

		}

		public function get_batch_update_copy_from_record_ids( $args = array() ) {

			global $wpdb;
			$data = array();

			$dashboard_key = ( !empty( $args['dashboard_key'] ) ) ? $args['dashboard_key'] : $this->dashboard_key;
			$is_ajax = ( isset( $args['is_ajax'] )  ) ? $args['is_ajax'] : true;

			if( !empty( $dashboard_key ) || !empty( $this->req_params['table_model']['posts']['where']['post_type'] ) ) {
				$dashboards = ( !empty( $this->req_params['table_model']['posts']['where']['post_type'] ) && empty( $args['dashboard_key'] ) ) ? $this->req_params['table_model']['posts']['where']['post_type'] : $dashboard_key;
				$dashboards = ( is_array( $dashboards ) ) ? $dashboards : array( $dashboards );
				$search_term = ( ! empty( $this->req_params['search_term'] ) ) ? $this->req_params['search_term'] : ( ( ! empty( $args['search_term'] ) ) ? $args['search_term'] : '' );


				$select = apply_filters( 'sm_batch_update_copy_from_ids_select', "SELECT ID AS id, post_title AS title", $args );

				$search_cond = ( ! empty( $search_term ) ) ? " AND ( id LIKE '%".$search_term."%' OR post_title LIKE '%".$search_term."%' OR post_excerpt LIKE '%".$search_term."%' ) " : '';

				$search_cond_ids = ( !empty( $args['search_ids'] ) ) ? " AND id IN ( ". implode(",", $args['search_ids']) ." ) " : '';

				$results = $wpdb->get_results( $select . " FROM {$wpdb->prefix}posts WHERE post_status != 'trash' ". $search_cond ." ". $search_cond_ids ." AND post_type IN ('". implode("','", $dashboards) ."') ", 'ARRAY_A' );

				if( count( $results ) > 0 ) {
					foreach( $results as $result ) {
						$data[ $result['id'] ] = trim($result['title']);
					}
				}

				$data = apply_filters( 'sm_batch_update_copy_from_ids', $data );
			}

			if( $is_ajax ){
				wp_send_json( $data );
			} else {
				return $data;
			}
		}


		public function save_state() {
			$slug = ( ! empty( $this->req_params['active_module'] ) ) ? $this->req_params['active_module'] : '';
			$is_view = ( isset( $this->req_params['is_view'] ) ) ? $this->req_params['is_view'] : '';
			if( ! empty( $slug ) && ! empty( $this->req_params['dashboard_states'] ) ) {		
				// Code to update the dashboards column state
				foreach ($this->req_params['dashboard_states'] as $dashboard => $value) {
					$value = json_decode( stripslashes( $value ), true );			
					$column_model_transient = sa_sm_generate_column_state( $value );
					if ( ( 1 !== intval( $is_view ) ) && ( ! empty( $this->store_col_model_transient_option_nm ) ) ) {
						update_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, $column_model_transient );
					}
				}

				// State saving for view should not be done.
				if( 1 === intval( $is_view ) ) {
					global $wpdb;	
					$result = $wpdb->query( // phpcs:ignore
											$wpdb->prepare( // phpcs:ignore
												"UPDATE {$wpdb->prefix}sm_views
																	SET params = %s
																	WHERE slug = %s",
												wp_json_encode( $column_model_transient ),
												$slug
											)
										);
				}
			}

			if( ( defined('SMPRO') && true === SMPRO ) && isset( $this->req_params['edited_column_titles'] ) && is_callable( 'Smart_Manager_Pro_Base', 'update_column_titles' ) ){
				$this->req_params['state_option_name'] = $this->store_col_model_transient_option_nm;
				Smart_Manager_Pro_Base::update_column_titles( $this->req_params );
			}
			
			wp_send_json( array( 'ACK'=> 'Success' ) );
		}

		// Function to reset the column state to default
		public function reset_state() {
			global $wpdb;

			$current_user_id = get_current_user_id();
			$slug = ( ! empty( $this->req_params['active_module'] ) ) ? $this->req_params['active_module'] : '';
			$is_view = ( isset( $this->req_params['is_view'] ) ) ? ( 1 === intval( $this->req_params['is_view'] ) ) : false;

			if( ! $is_view && ! empty( $slug ) ) {
			 	delete_user_meta( $current_user_id, 'sa_sm_'.$slug );
			} else {
				$column_model_transient = array();
				if( ! empty( $this->req_params['dashboard_key'] ) ) {
					$column_model_transient = get_user_meta( $current_user_id, 'sa_sm_'.$this->req_params['dashboard_key'], true);
					if( empty( $column_model_transient ) ) {
						$store_model_transient = get_transient( 'sa_sm_'.$this->req_params['dashboard_key'] );
						if( ! empty( $store_model_transient ) && !is_array( $store_model_transient ) ) {
							$column_model_transient = sa_sm_generate_column_state( json_decode( $store_model_transient, true ) );
						}	
					}
				}
			
			 	if( ! empty( $column_model_transient ) ) {
					$result = $wpdb->query( // phpcs:ignore
											$wpdb->prepare( // phpcs:ignore
												"UPDATE {$wpdb->prefix}sm_views
																	SET params = %s
																	WHERE slug = %s",
												wp_json_encode( $column_model_transient ),
												$slug
											)
										);
				}	
				
			}		
			wp_send_json( array( 'ACK'=> 'Success' ) );
		}

		public function inline_update() {
			global $wpdb, $current_user;
			$is_advanced_search  = ( ( ! empty( $this->req_params['is_advanced_search'] ) ) && ( sanitize_text_field( $this->req_params['is_advanced_search'] ) === 'true' ) ) ? true : false;
			$edited_data = ( ! empty( $this->req_params['edited_data'] ) ) ? json_decode( stripslashes( $this->req_params['edited_data'] ), true ) : array();
			$updated_edited_data = apply_filters( 'sm_filter_updated_edited_data', ( ! empty( $this->req_params['updatedEditedData'] ) ) ? json_decode( stripslashes( $this->req_params['updatedEditedData'] ), true ) : $edited_data );
			$store_model_transient = $store_model_transient = get_transient( 'sa_sm_' . $this->dashboard_key );
			$store_model_transient = ( ! empty( $store_model_transient ) && ! is_array( $store_model_transient ) ) ? json_decode( $store_model_transient, true ) : $this->get_dashboard_model( true );
			$table_model = ( ! empty( $store_model_transient['tables'] ) ) ? $store_model_transient['tables'] : array();
			$col_model = ( ! empty( $store_model_transient['columns'] ) ) ? $store_model_transient['columns'] : array();
			if ( empty( $edited_data ) || empty( $table_model ) || empty( $col_model ) ) return;
			if ( is_callable( array( 'Smart_Manager_Task', 'task_update' ) ) && ( is_array( $updated_edited_data ) && ( ! empty( $updated_edited_data ) ) ) && ( ! empty( $this->req_params['title'] ) ) && ( ! empty( $this->dashboard_key ) ) ) {
				$this->task_id = Smart_Manager_Task::task_update( 
					array(
						'title' => $this->req_params['title'],
						'created_date' => date( 'Y-m-d H:i:s' ),
						'completed_date' => '0000-00-00 00:00:00',
						'post_type' => $this->dashboard_key,
						'type' => 'inline',
						'status' => 'in-progress',
						'actions' => array_values( $updated_edited_data ),
						'record_count' => count( $updated_edited_data )
					) 
				);
			}
			$edited_data['task_id'] = intval( $this->task_id );
			$edited_data = apply_filters( 'sm_inline_update_pre', $edited_data );
			if ( true === array_key_exists( 'task_id', $edited_data ) ) {
				unset( $edited_data['task_id'] );
			}
			$data_cols_serialized = array();
			$data_cols_multiselect = array();
			$data_cols_multiselect_val = array();
			$data_cols_list = array();
			$data_cols_list_val = array();
			$data_cols_timestamp = array();
			$data_date_cols_timestamp = array();
			$data_time_cols_timestamp = array();
			$date_cols_site_timezone = array();
			$date_cols_utc_timezone = array();
			$data_cols_checkbox = array();
			$data_cols_numeric = array();
			$numeric_cols_decimal_places = array();
			$taxonomies		           = array();
			$taxonomy_data_to_update   = array();
			//Code for storing the serialized cols
			foreach ($col_model as $col) {
				$col_exploded = (!empty($col['src'])) ? explode("/", $col['src']) : array();

				if (empty($col_exploded)) continue;
				
				if ( sizeof($col_exploded) > 2) {
					$col_meta = explode("=",$col_exploded[1]);
					$col_nm = $col_meta[1];
				} else {
					$col_nm = $col_exploded[1];
				}

				if ( !empty( $col['type'] ) ) {
					if( $col['type'] == 'sm.serialized' ) {
						$data_cols_serialized[] = $col_nm;
					} elseif( $col['type'] == 'sm.multilist' ) {
						$data_cols_multiselect[] = $col_nm;
						$data_cols_multiselect_val[$col_nm] = (!empty($col['values'])) ? $col['values'] : array();

						if (empty($data_cols_multiselect_val[$col_nm])) continue;

						$final_multiselect_val = array();

						foreach ($data_cols_multiselect_val[$col_nm] as $key => $value) {
							if( ! empty( $value['term'] ) ) {
								$final_multiselect_val[$key] = $value['term'];
							}
						}

						$data_cols_multiselect_val[$col_nm] = $final_multiselect_val;
					} elseif ( $col['type'] == 'dropdown' ) {
						$data_cols_list[] = $col_nm;
						$data_cols_list_val[$col_nm] = (!empty($col['values'])) ? $col['values'] : array();
					} else if( $col['type'] == 'sm.datetime' && !empty( $col['date_type'] ) && $col['date_type'] == 'timestamp' ) {
						$data_cols_timestamp[] = $col_nm;
					} else if( $col['type'] == 'sm.date' && !empty( $col['date_type'] ) && $col['date_type'] == 'timestamp' ) {
						$data_date_cols_timestamp[] = $col_nm;
					} else if( $col['type'] == 'sm.time' && !empty( $col['date_type'] ) && $col['date_type'] == 'timestamp' ) {
						$data_time_cols_timestamp[] = $col_nm;
					} else if( 'checkbox' === $col['type'] ) {
						$data_cols_checkbox[] = $col_nm;
					} else if( sizeof($col_exploded) <= 2 && ( 'number' === $col['type'] || 'numeric' === $col['type'] || ( ! empty( $col['validator'] ) && 'customNumericTextEditor' === $col['validator'] ) ) ){
						if( isset( $col['decimalPlaces'] ) ) {
							$numeric_cols_decimal_places[ $col_nm ] = $col['decimalPlaces'];
						}
						$data_cols_numeric[] = $col_nm;
					}

					if( ($col['type'] == 'sm.datetime' || $col['type'] == 'sm.date') ) {
						if( ! empty( $col['is_utc'] ) ){
							$date_cols_utc_timezone[] = $col_nm;
						} else if( ( isset($col['is_utc']) && false === $col['is_utc'] ) ){
							$date_cols_site_timezone[] = $col_nm;
						}
					}
				}
			}

			$sm_default_inline_update = true;

			$data_col_params = array( 'data_cols_multiselect' => $data_cols_multiselect,
									 'data_cols_multiselect_val' => $data_cols_multiselect_val,
									 'data_cols_list' => $data_cols_list,
									 'data_cols_list_val' => $data_cols_list_val,
									 'data_cols_timestamp' => $data_cols_timestamp,
									 'data_date_cols_timestamp' => $data_date_cols_timestamp,
									 'data_time_cols_timestamp' => $data_time_cols_timestamp,
									 'col_model' => $col_model,
									 'data_cols_serialized' => $data_cols_serialized,
									 'data_cols_checkbox' => $data_cols_checkbox,
									 'data_cols_numeric' => $data_cols_numeric,
									 'numeric_cols_decimal_places' => $numeric_cols_decimal_places,
									 'task_id' => $this->task_id
									);

			$sm_default_inline_update = apply_filters('sm_default_inline_update', $sm_default_inline_update);

			set_transient('sm_beta_skip_delete_dashboard_transients', 1, DAY_IN_SECONDS); // for preventing delete dashboard transients

			if( !empty($sm_default_inline_update) ) {
				$update_params_meta = array(); // for all tables with meta_key = meta_value like structure for updating the values
				$insert_params_meta = array(); // for all tables with meta_key = meta_value like structure for inserting the values
				$meta_data_edited = array();
				$meta_index = 0;
				$old_post_id = '';
				$meta_case_cond = 'CASE post_id ';
				$meta_keys_edited = array(); // array for storing the edited meta_keys
				$data_col_params['posts_fields'] = array(); // array for keeping track of all 'posts' table fields

				foreach ($edited_data as $id => $edited_row) {

					
					$update_params_custom = array(); // for custom tables
					$where_cond = array();
					$insert_post = 0;

					$temp_id = $id;
					$id = ( strpos($id, 'sm_temp_') !== false ) ? 0 : $id; //for newly added records

					//Code for inserting the post
					if ( empty($id) ) {
						$insert_params_posts = array();
						foreach ($edited_row as $key => $value) {
							$edited_value_exploded = explode("/", $key);
							
							if (empty($edited_value_exploded)) continue;

							$update_table = $edited_value_exploded[0];
							$update_column = $edited_value_exploded[1];

							if ($update_table == 'posts') {
								$insert_params_posts [$update_column] = $value;
							}
						}

						if( empty( $insert_params_posts['post_type'] ) ) {
							$insert_params_posts['post_type'] = $this->dashboard_key;
						}

						if ( !empty($insert_params_posts) ) {
							$inserted_id = wp_insert_post($insert_params_posts);
							if ( !is_wp_error( $inserted_id ) && !empty($inserted_id) ) {
								if( ! empty( $edited_data[$temp_id] ) ){
									unset( $edited_data[$temp_id] );
								} 
								$id = $inserted_id;
								$insert_post = 1; //Flag for determining whether post has been inserted	
								$edited_data[$id] =  $edited_row;
							} else {
								continue;
							}

						} else {
							continue;
						}
					}

					// if (empty($edited_row['posts/ID'])) continue;

					// $id = $edited_row['posts/ID'];
					foreach ($edited_row as $key => $value) {
						$prev_val = $meta_key_name = '';
						$edited_value_exploded = explode("/", $key);

						if (empty($edited_value_exploded)) continue;

						$update_cond = array(); // for handling the where condition
						$update_params_meta_flag = false; // flag for handling the query for meta_key = meta_value like structure

						$update_table = $edited_value_exploded[0];
						$update_column = $edited_value_exploded[1];

						if (empty($where_cond[$update_table])) {
							$where_cond[$update_table] = (!empty($table_model[$update_table]['pkey']) && $update_column == $table_model[$update_table]['pkey']) ? 'WHERE '. $table_model[$update_table]['pkey'] . ' = ' . $value : '';
						}
						if ( sizeof( $edited_value_exploded ) > 2 ) {
							$cond = explode( '=', $edited_value_exploded[1] );
							if ( 2 === sizeof( $cond ) ) {
								$update_cond[ $cond[0] ] = $meta_key_name = $cond[1];
							}
							$update_column_exploded = explode( '=', $edited_value_exploded[2] );
							$update_column = $update_column_exploded[0];
							$update_params_meta_flag = true;
						}
						$update_column_name = ( 'postmeta' === $update_table ) ? $meta_key_name : $update_column;
						$this->field_names[ $id ][ $update_column_name ] = $key;
						// For fetching previous value	
						if ( ( ! empty( $id ) ) && ( ! empty( $update_table ) ) && ( ! empty( $update_column_name ) ) && ( is_callable( array( 'Smart_Manager_Task', 'get_previous_data' ) ) ) ){
							$prev_val = Smart_Manager_Task::get_previous_data( $id, $update_table, $update_column_name );
							$prev_val = ( ! empty( $data_col_params ) ) ? sa_sm_format_prev_val( array(
								'prev_val' => $prev_val,
								'update_column' => $update_column_name,
								'col_data_type' => $data_col_params,
								'updated_val' => $value
								)
							) : $prev_val;
						}
						// handling the update array for posts table
						if ( $update_table == 'posts' && $insert_post != 1 ) {

							if ( ! empty( $id ) && empty($data_col_params['posts_fields'][$id][$table_model[$update_table]['pkey']]) ) {
								$data_col_params['posts_fields'][$id][$table_model[$update_table]['pkey']] = $id;
							}

							$data_col_params['posts_fields'][$id][$update_column] = $value;
							if ( ( ! empty( $update_column ) ) && ( 'post_date' === $update_column ) ) {
								$data_col_params['posts_fields'][ $id ]['post_date_gmt'] = get_gmt_from_date( $value );
								$data_col_params['posts_fields'][ $id ]['edit_date'] = true;
							}
							$this->prev_post_values[ $id ][ $update_column ] = $prev_val;
						} else if ( $update_params_meta_flag === true ) {

							if (empty($id) || empty($update_cond['meta_key'])) continue;

							$meta_key = $update_cond['meta_key'];
							$updated_val = $value;
							$this->prev_postmeta_values[ $id ][ $meta_key ] = $prev_val;
							//Code for handling serialized data
	    					if( in_array($meta_key, $data_cols_serialized) ) {
								if (!empty($value)) {
									$updated_val = json_decode($value,true);

									if( empty( $updated_val ) ) { // for comma separated string values
										$updated_val = explode(",", $value);

										if( empty($updated_val) ) {
											$updated_val = $value;
										}
									}
								}
	        				}

	        				//Code for handling timestamp data
	    					if( in_array($meta_key, $data_cols_timestamp) || in_array($meta_key, $data_date_cols_timestamp) || in_array($meta_key, $data_time_cols_timestamp) ) {

	    						if( in_array($meta_key, $data_time_cols_timestamp) ) {
	    							$value = '1970-01-01'.$value;
								}

								//Code for converting date & datetime values to localize timezone
								$value = !empty ( $value ) ? strtotime( $value ) : '';
								if( in_array( $meta_key, $date_cols_site_timezone ) ){
									$offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
									$updated_val = ( ! empty( $value ) ) ? $value + $offset : '';
								} else if( in_array( $meta_key, $date_cols_utc_timezone ) ){
									$updated_val = ( ! empty( $value ) ) ? sa_sm_get_utc_timestamp_from_site_date( $value ) : '';
								} else {
									$updated_val = $value;
								}
	    					}

							//Code for forming the edited data array
							if ( empty( $meta_data_edited[$update_table] ) ) {
								$meta_data_edited[$update_table] = array();
							}

							if ( empty( $meta_data_edited[$update_table][$id] ) ) {
								$meta_data_edited[$update_table][$id] = array();
							}

							$meta_data_edited[$update_table][$id][$update_cond['meta_key']] = $updated_val;
							$meta_keys_edited [$update_cond['meta_key']] = '';

						} else if($update_table == 'terms') {
							if ( ( defined('SMPRO') ) && ( true === SMPRO ) ) {
								$args = apply_filters( 'sm_process_inline_terms_update', array(
									'update_column' => $update_column,
									'term_ids' => array_map( 'absint', explode( ',', $value ) ),
									'id' => $id,
									'prev_val' => $prev_val,
									'value' => $value,
									'taxonomies' => $taxonomies,
									'prev_postmeta_values' => $this->prev_postmeta_values,
									'meta_data_edited' => $meta_data_edited,
									'meta_keys_edited' => $meta_keys_edited,
									'taxonomy_data_to_update' => $taxonomy_data_to_update,
								) );
								if ( ( empty( $args ) ) ) {
									continue;
								}
								$taxonomy_data_to_update = ( ! empty( $args['taxonomy_data_to_update'] ) ) ? $args['taxonomy_data_to_update'] : $taxonomy_data_to_update;
								$this->prev_postmeta_values = ( ! empty( $args['prev_postmeta_values'] ) ) ? $args['prev_postmeta_values'] : $this->prev_postmeta_values;
								$meta_data_edited = ( ! empty( $args['meta_data_edited'] ) ) ? $args['meta_data_edited'] : $meta_data_edited;
								$meta_keys_edited = ( ! empty( $args['meta_keys_edited'] ) ) ? $args['meta_keys_edited'] : $meta_keys_edited;
								$taxonomies = ( ! empty( $args['taxonomies'] ) ) ? $args['taxonomies'] : $taxonomies;
							} else {
								$this->update_terms_table_data( array(
									'update_column' => $update_column,//taxonomy.
									'data_cols_multiselect' => $data_cols_multiselect,
									'data_cols_multiselect_val' => $data_cols_multiselect_val,
									'data_cols_list' => $data_cols_list,
									'data_cols_list_val' => $data_cols_list_val,
									'value' => $value, //terms.
									'id' => $id //post id.
									)
								);
							}
						}
					}
				}
				//Code for updating the terms data.
				do_action( 'sm_inline_update_post_terms', array(
					'taxonomy_data_to_update'=> $taxonomy_data_to_update,
					'taxonomies' => $taxonomies,
					'task_id' => ( ! empty( $this->task_id ) ) ? $this->task_id : 0,
				) );

				//Code for updating the meta data.
				do_action( 'sm_inline_update_post_meta', 
					array(
						'meta_data_edited' => ( ! empty( $meta_data_edited ) ) ? $meta_data_edited : array(),
						'meta_keys_edited' => ( ! empty( $meta_keys_edited ) ) ? array_keys( $meta_keys_edited ) : array(),
						'task_id' => ( ! empty( $this->task_id ) ) ? $this->task_id : 0, 
						'prev_postmeta_values'=> ( ! empty( $this->prev_postmeta_values ) ) ? $this->prev_postmeta_values : array(), 
					)
				);
				if ( ( ! empty( $meta_data_edited ) ) && ( ! empty( $meta_keys_edited ) ) && ( defined('SMPRO') && empty( SMPRO ) ) ) {
					foreach ( $meta_data_edited as $update_params ) {
						if( empty( $update_params ) || !is_array( $update_params ) ) continue;
						foreach ($update_params as $id => $updated_data) {
							if( empty( $id ) || empty( $updated_data ) || !is_array( $updated_data ) ) continue;
							foreach ($updated_data as $key => $value) {
								if( empty( $key ) ) continue;
								$key      = wp_unslash( $key );
								$value    = esc_sql( wp_unslash( $value ) );
								$prev_val = ( ( ! empty( $this->prev_postmeta_values[$id] ) ) && ( ! empty($this->prev_postmeta_values[$id][$key]) ) ) ? $this->prev_postmeta_values[$id][$key] : '';
								update_post_meta( $id, $key, $value, $prev_val );
							}
						}
					}
				}

				// Code for updating the posts data.
				do_action( 'sm_inline_update_post_data',
					array( 
						'posts_data' => $data_col_params['posts_fields'], 
						'task_id' => ( ! empty( $this->task_id ) ) ? $this->task_id : 0, 
					) 
				);
				if ( ( ! empty( $data_col_params['posts_fields'] ) ) && ( defined('SMPRO') && empty( SMPRO ) ) ) {
					foreach ( $data_col_params['posts_fields'] as $id => $post_params ) {
						if ( empty( $id ) || empty( $post_params ) ) {
							continue;	
						}
						wp_update_post( $post_params );
					}
				}
			}
			do_action( 'sm_inline_update_post', $edited_data, $data_col_params );
			// For updating task details table.
			if ( ( ! empty( self::$update_task_details_params ) ) && is_callable( array( 'Smart_Manager_Task', 'task_details_update' ) ) ) {
				Smart_Manager_Task::task_details_update();
			}
			delete_transient('sm_beta_skip_delete_dashboard_transients', 1, DAY_IN_SECONDS); // for preventing delete dashboard transients
			$msg_str = '';

			if ( sizeof($edited_data) > 1 ) {
				$msg_str = 's';
			}
			//Update man-hrs data in DB
			Smart_Manager::sm_update_man_hours_data( $is_advanced_search ? 'advanced_search_inline' : 'inline', sizeof( $edited_data ) );
			if( isset( $this->req_params['pro'] ) && empty( $this->req_params['pro'] ) ) {
				$sm_inline_update_count = get_option( 'sm_inline_update_count', 0 );
				$sm_inline_update_count += sizeof($edited_data);
				update_option( 'sm_inline_update_count', $sm_inline_update_count, 'no' );
				//Get time saved
				$modal_message = '';
				$time_saved_details = Smart_Manager::sm_get_time_saved_with_additional_savings( $is_advanced_search ? 'advanced_search_inline' : 'inline', sizeof( $edited_data ), 'mins' );
				if( ! empty( $time_saved_details ) && is_array( $time_saved_details )  ){
					$modal_message = sprintf(
								/* translators: %1$s: time saved in minutes/hours, %2$s: Pro upgrade link, %3$s: additional savings possible */
								__(
									'You saved <strong>%1$s</strong>! Upgrade to %2$s to save more <strong>%3$s</strong> and unlock undo.',
									'smart-manager-for-wp-e-commerce'
								),
								__(
									( ( ! empty( $time_saved_details['time_saved'] ) ) ? $time_saved_details['time_saved'] : '' ) . 
									' ' . 
									( ( ! empty( $time_saved_details['unit'] ) ) ? $time_saved_details['unit'] : '' )
								), // Time saved in mins/hrs.
								sprintf(
									'<a href="%s" target="_blank">%s</a>',
									esc_url( SM_APP_ADMIN_URL . "-pricing" ),
									esc_html__( 'Pro', 'smart-manager-for-wp-e-commerce' )
								),
								__(
									( ( ! empty( $time_saved_details['additional_savings'] ) ) ? $time_saved_details['additional_savings'] : '' ) . 
									' ' . 
									( ( ! empty( $time_saved_details['unit'] ) ) ? $time_saved_details['unit'] : '' )
								) // Additional savings in mins/hrs.
							);
				}
				$resp = array( 
							'sm_inline_update_count' => $sm_inline_update_count,
							'modal_message' => $modal_message,
							'msg' => sprintf(
									/* translators: %1$d: number of updated record %2$s: record update message */ 
									esc_html__( '%1$d record%2$s updated successfully!', 'smart-manager-for-wp-e-commerce'), sizeof( $edited_data ), $msg_str 
								),
						);
								
				$msg = json_encode($resp);
			} else {
				$msg = sprintf(
					/* translators: %1$d: number of updated record %2$s: record update message */
					esc_html__( '%1$d record%2$s updated successfully! %3$s', 'smart-manager-for-wp-e-commerce' ), sizeof( $edited_data ), $msg_str, '<a href="#" id="undo_action" data-task-id="'.$this->task_id.'">Undo last update.</a>');
			}

			echo $msg;
			exit;
		}
				
		// Function to handle the delete data functionality
		public function delete() {

			global $wpdb;

			$delete_ids = (!empty($this->req_params['ids'])) ? json_decode(stripslashes($this->req_params['ids']), true) : array();

			if( empty( $delete_ids ) ) return;

			$default_process = apply_filters( 'sm_default_process_delete_records', true );

			if( ! empty( $default_process ) ){
				$deleter = apply_filters( 'sm_deleter', null, array( 'source' => $this ) );

				$is_callable = false;
				if ( ! empty( $deleter ) ) {
					if ( ! empty( $deleter['callable'] ) ) {
						if ( is_array( $deleter['callable'] ) ) {
							if ( is_callable( $deleter['callable'] ) ) {
								$is_callable = true;
							}
						} elseif ( is_string( $deleter['callable'] ) ) {
							if ( function_exists( $deleter['callable'] ) ) {
								$is_callable = true;
							}
						}
					}
					if ( ! empty( $deleter['delete_ids'] ) ) {
						$delete_ids = $deleter['delete_ids'];
					}
				}

				foreach ( $delete_ids as $delete_id ) {
					( $is_callable ) ? call_user_func_array( $deleter['callback'], array( intval( $delete_id ) ) ) : wp_trash_post( intval( $delete_id ) );
				}
			}
			
			$delete_ids_count = apply_filters( 'sm_default_process_delete_records_result', sizeof( $delete_ids ), array( 'ids' => $delete_ids, 'this' => $this ) );
			echo sprintf( 
				/* translators: %1$d: number of updated record %2$s: record update message */
				esc_html__( '%1$d record%2$s deleted successfully!', 'smart-manager-for-wp-e-commerce' ), $delete_ids_count, ( ( $delete_ids_count > 1 ) ? 's' : '' ) );
			exit;
		}

		/**
		 * Function to check if 'trash' records are to be shown or not
		 *
		 * @return boolean flag for whether 'trash' records are to be shown or not
		 */
		public function is_show_trash_records(){
			return apply_filters( 'sm_view_trash_records', ( 'yes' === get_option( 'sm_view_'.$this->dashboard_key.'_trash_records', 'no' ) || 'yes' === Smart_Manager_Settings::get( 'view_trash_records' ) ) );
		}

		// *****************************************************************************************
		// Functions for handling advanced search functionality
		// *****************************************************************************************

		/**
		 * Function to generate meta_query for advanced search for any flat tables like 'posts', 'term_taxonomy', etc.
		 *
		 * @param array $params The search condition params.
		 * @return array updated search query.
		 */
		public function create_flat_table_search_query( $params = array() ){
			$table_nm = ( ! empty( $params['table_nm'] ) ) ? $params['table_nm'] : '';
			$search_params = ( ! empty( $params['search_params'] ) ) ? $params['search_params'] : '';

			if( empty( $table_nm ) || empty( $search_params ) ){
				return array();
			}
			
			$search_col = apply_filters('sm_search_format_query_'. $table_nm .'_col_name', $search_params['search_col'], $search_params);
			$search_value = apply_filters('sm_search_format_query_'. $table_nm .'_col_value', $search_params['search_value'], $search_params);
			if ( empty( $params['search_query']['cond_'.$table_nm.'_col_vals'] ) ) {
				$params['search_query']['cond_'.$table_nm.'_col_vals'] = array();
			}
			if ( empty( $params['search_query']['cond_'.$table_nm.'_selected_search_operators'] ) ) {
				$params['search_query']['cond_'.$table_nm.'_selected_search_operators'] = array();
			}
			if( in_array( $search_params['search_data_type'], array( "number", "numeric" ) ) ) {
				$val = ( empty( $search_value ) && '0' != $search_value ) ? "''" : $search_value;
				$cond = "( ".$params['rule']['table_name'].".".$search_col . " ". $search_params['search_operator'] .( empty( $params['skip_placeholders'] ) ? " %f" : " '".$search_params['search_value']."'" )." )";
			} else if ( $search_params['search_data_type'] == "date" || $search_params['search_data_type'] == "sm.datetime" ) {
				$cond = "( ".$params['rule']['table_name'].".".$search_col . " ". $search_params['search_operator'] ." %s AND ". $params['rule']['table_name'] .".". $search_col ." NOT IN ('0', '1970-01-01 00:00:00', '1970-01-01', '', 0) )";
			} else {
				if ($search_params['search_operator'] == 'is') {
					$cond = "( ".$params['rule']['table_name'].".".$search_col . " LIKE ".( empty( $params['skip_placeholders'] ) ? "%s" : "'".$search_params['search_value']."'" )." )";
				} else if ($search_params['search_operator'] == 'is not') {
					$cond = "( ".$params['rule']['table_name'].".".$search_col . " NOT LIKE ". ( empty( $params['skip_placeholders'] ) ? "%s" : "'".$search_params['search_value']."'" ) ." )";
				} else {
					$cond = "( ".$params['rule']['table_name'].".".$search_col . " ". $search_params['search_operator'] .( empty( $params['skip_placeholders'] ) ? " %s" : " '".$search_params['search_value']."'" ). " )";
				}
			}

			$cond = apply_filters( 'sm_search_'.$table_nm.'_cond', $cond, array_merge( $search_params, array( 'table_nm' => $params['rule']['table_name'] ) ) );

			$params['search_query']['cond_'.$table_nm] .= $cond ." && ";
			$params['search_query']['cond_'.$table_nm.'_col_vals'][] = $search_value;
			$params['search_query']['cond_'.$table_nm.'_selected_search_operators'][] = $search_params['selected_search_operator'];
			return $params['search_query'];
		}

		/**
		 * Function to generate meta_query for advanced search for any meta tables like 'postmeta', 'termmeta', etc.
		 *
		 * @param array $params The search condition params.
		 * @return array updated search query.
		 */
		public function create_meta_table_search_query( $params = array() ){

			global $wpdb;

			$meta_table = ( ! empty( $params['table_nm'] ) ) ? $params['table_nm'] : '';
			$search_params = ( ! empty( $params['search_params'] ) ) ? $params['search_params'] : '';

			if( empty( $meta_table ) || empty( $search_params ) ){
				return array();
			}

			$params['search_query']['cond_'.$meta_table.'_col_name'] .= $search_params['search_col'];
			$params['search_query']['cond_'.$meta_table.'_col_value'] .= $search_params['search_value'];

			$search_params['search_col'] = apply_filters('sm_search_format_query_'.$meta_table.'_col_name', $search_params['search_col'], $search_params);
			$search_params['search_value'] = apply_filters('sm_search_format_query_'.$meta_table.'_col_value', $search_params['search_value'], $search_params);
			if ( empty( $params['search_query']['cond_'.$meta_table.'_col_vals'] ) ) {
				$params['search_query']['cond_'.$meta_table.'_col_vals'] = array();
			}
			if ( empty( $params['search_query']['cond_'.$meta_table.'_selected_search_operators'] ) ) {
				$params['search_query']['cond_'.$meta_table.'_selected_search_operators'] = array();
			}
			if( in_array( $search_params['search_data_type'], array( "number", "numeric" ) ) ) {
				$val = ( empty( $search_params['search_value'] ) && '0' != $search_params['search_value'] ) ? "''" : $search_params['search_value'];
				
				//Condition for exact matching of '0' numeric values
				if( '0' == $search_params['search_value'] && ( '=' === $search_params['search_operator'] || '!=' === $search_params['search_operator'] ) ) {
					$val = "'". $val . "'";
				}
				
				$meta_cond = "( ". $params['rule']['table_name'].".meta_key LIKE '". $search_params['search_col'] . "' AND ". $params['rule']['table_name'] .".meta_value ". $search_params['search_operator']. " " . ( empty( $params['skip_placeholders'] ) ? "%f" : $search_params['search_value'] )." )";
				
				$params['search_query']['cond_'.$meta_table.'_operator'] .= $search_params['search_operator'];
			} else if( $search_params['search_data_type'] == "date" || $search_params['search_data_type'] == "sm.datetime" ) {
				$meta_cond = "( ". $params['rule']['table_name'].".meta_key LIKE '". $search_params['search_col'] . "' AND ". $params['rule']['table_name'] .".meta_value ". $search_params['search_operator'] ." %s AND ". $params['rule']['table_name'] .".meta_value NOT IN ('0', '1970-01-01 00:00:00', '1970-01-01', '', 0) )";
				$params['search_query']['cond_'.$meta_table.'_operator'] .= $search_params['search_operator'];
			} else {
				if ($search_params['search_operator'] == 'is') {
					$params['search_query']['cond_'.$meta_table.'_operator'] .= 'LIKE';
					$meta_cond = "( ". $params['rule']['table_name'].".meta_key LIKE '". $search_params['search_col'] . "' AND ". $params['rule']['table_name'] .".meta_value LIKE ".( empty( $params['skip_placeholders'] ) ? "%s" : "'".$search_params['search_value']."'" )." )";
				} else if ($search_params['search_operator'] == 'is not') {

					$params['search_query']['cond_'.$meta_table.'_operator'] .= 'NOT LIKE';
					$meta_cond = "( ". $params['rule']['table_name'].".meta_key LIKE '". $search_params['search_col'] . "' AND ". $params['rule']['table_name'] .".meta_value NOT LIKE ".( empty( $params['skip_placeholders'] ) ? "%s" : "'".$search_params['search_value']."'" )." )";
				} else {
					$params['search_query']['cond_'.$meta_table.'_operator'] .= $search_params['search_operator'];
					$meta_cond = "( ". $params['rule']['table_name'].".meta_key LIKE '". $search_params['search_col'] . "' AND ". $params['rule']['table_name'] .".meta_value ". $search_params['search_operator']. ( empty( $params['skip_placeholders'] ) ? " %s" : " '".$search_params['search_value']."'" )." )";
				}	
			}

			$meta_cond = apply_filters( 'sm_search_'.$meta_table.'_cond', $meta_cond, array_merge( $search_params, array( 'table_nm' => $params['rule']['table_name'], 'rule_val' => $params['rule']['value'] ) ) );
			$pkey = ( ! empty( $search_params['pkey'] ) ) ? $search_params['pkey'] : 'post_id';
			$join_table = ( ! empty( $search_params['join_table'] ) ) ? $search_params['join_table'] : 'posts';
			$type = ( ! empty( $search_params['type'] ) ) ? $search_params['type'] : 'post_type';
			$post_type_condn = '';
			if ( ! empty( $search_params['post_type'] ) ) {
				$post_type_condn = ( is_array( $search_params['post_type'] ) && count( $search_params['post_type'] ) > 0 ) ? $type . " IN ('" . implode( "','", $search_params['post_type'] ) ."')" : $type . " = '". $search_params['post_type']."'";
			}

			if( ( ( empty( $params['rule']['value'] ) && '0' !== $params['rule']['value'] ) || $params['rule']['value'] == "''") && ! empty( $search_params['post_type'] ) && ! empty( $search_params['search_col'] ) && ! empty( $post_type_condn ) ) {
				$empty_search_value = ( $search_params['search_operator'] == 'is' || $search_params['search_operator'] == '=' ) ? 'IS NULL' : 'IS NOT NULL';
				$meta_cond = "( ". $meta_cond ." OR ( ". $params['rule']['table_name'] .".meta_key LIKE '". $search_params['search_col'] . "' AND ". $params['rule']['table_name'] .".meta_value " . $empty_search_value ." )
								OR ( ". $params['rule']['table_name'] .".".$pkey." ". ( ( $search_params['search_operator'] == 'is' || $search_params['search_operator'] == '=' ) ? 'NOT IN' : 'IN' ) ." ( SELECT DISTINCT ft.id 
																																										FROM {$wpdb->prefix}".$meta_table." as mt
																																											JOIN {$wpdb->prefix}".$join_table." as ft
																																												ON(ft.id = mt.".$pkey."
																																													AND ft.".$post_type_condn." )
																																										WHERE mt.meta_key = '". $search_params['search_col'] . "' ) ) )";
			}

			$params['search_query']['cond_'.$meta_table.''] .= $meta_cond ." && ";
			$params['search_query']['cond_'.$meta_table.'_col_name'] .= " && ";
			$params['search_query']['cond_'.$meta_table.'_col_value'] .= " && ";
			$params['search_query']['cond_'.$meta_table.'_operator'] .= " && ";
			$params['search_query']['cond_'.$meta_table.'_col_vals'][] = $search_params['search_value'];
			$params['search_query']['cond_'.$meta_table.'_selected_search_operators'][] = $search_params['selected_search_operator'];
			return $params['search_query'];
		}

		/**
		 * Function to generate terms query for advanced search.
		 *
		 * @param array $params The search condition params.
		 * @return array updated search query.
		 */
		public function create_terms_table_search_query( $params = array() ){

			global $wpdb;

			$search_params = ( ! empty( $params['search_params'] ) ) ? $params['search_params'] : '';

			if( empty( $search_params ) ){
				return array();
			}

			$params['search_query']['cond_terms_col_name'] .= $search_params['search_col'];
			$params['search_query']['cond_terms_col_value'] .= $search_params['search_value'];

			$search_params['search_col'] = apply_filters('sm_search_format_query_terms_col_name', $search_params['search_col'], $search_params);
			$search_params['search_value'] = apply_filters('sm_search_format_query_terms_col_value', $search_params['search_value'], $search_params);
			if ( empty( $params['search_query']['cond_terms_col_vals'] ) ) {
				$params['search_query']['cond_terms_col_vals'] = array();
			}
			if ( empty( $params['search_query']['cond_terms_selected_search_operators'] ) ) {
				$params['search_query']['cond_terms_selected_search_operators'] = array();
			}
			if ( 'is' === $search_params['search_operator'] ) {
				if( $params['rule']['value'] == "''" ) { //for handling empty search strings
					$terms_cond = "( ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE 'product_type' )";
					$params['search_query']['cond_terms_operator'] .= 'NOT LIKE';
				} else {                                        
					$terms_cond = "( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug LIKE %s" . " )";
					$params['search_query']['cond_terms_operator'] .= 'LIKE';
						
				}
			} else if( 'is not' === $search_params['search_operator'] ) {
				if( $params['rule']['value'] == "''" ) { //for handling empty search strings
					$terms_cond = "( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' )";
					$params['search_query']['cond_terms_operator'] .= 'LIKE';
				} else {
					$terms_cond = "( ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug NOT LIKE %s" . " )";
					$params['search_query']['cond_terms_operator'] .= 'NOT LIKE';
				}
			} else {
				$terms_cond = "( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug ". $search_params['search_operator'] ." %s )";
				$params['search_query']['cond_terms_operator'] .= $search_params['search_operator'];
			}

			$terms_cond = apply_filters( 'sm_search_terms_cond', $terms_cond, array_merge( $search_params, array( 'table_nm' => $wpdb->prefix .'term_taxonomy.taxonomy' ) ) );

			$params['search_query']['cond_terms'] .= $terms_cond ." && ";
			$params['search_query']['cond_terms_col_name'] .= " && ";
			$params['search_query']['cond_terms_col_value'] .= " && ";
			$params['search_query']['cond_terms_operator'] .= " && ";
			$params['search_query']['cond_terms_col_vals'][] = $search_params['search_value'];
			$params['search_query']['cond_terms_selected_search_operators'][] = $search_params['selected_search_operator'];
			return $params['search_query'];
		}

		/**
		 * Function to process terms query for advanced search.
		 *
		 * @param array $params The search condition params.
		 * @return void.
		 */
		public function process_terms_table_search_query( $params = array() ){

			global $wpdb, $wp_version;

			$search_query = ( ! empty( $params['search_query'] ) ) ? $params['search_query'] : array();
			if( empty( $params ) || empty( $search_query ) ){
				return;
			}

			$cond_terms_array = explode(" && ",$search_query['cond_terms']);

			$cond_terms_col_name = (!empty($search_query['cond_terms_col_name'])) ? explode(" && ",$search_query['cond_terms_col_name']) : '';
			$cond_terms_col_value = (!empty($search_query['cond_terms_col_value'])) ?  explode(" && ",$search_query['cond_terms_col_value']) : '';
			$cond_terms_operator = (!empty($search_query['cond_terms_operator'])) ?  explode(" && ",$search_query['cond_terms_operator']) : '';

			$index = 0;

			$terms_advanced_search_from = '';
			$terms_advanced_search_where = '';
			$result_terms_search = '';
			foreach ($cond_terms_array as $cond_terms) {
				$exp_search_val = ( is_array( $params['search_vals'] ) && ( ! empty( $params['search_vals'][ $index ] ) ) ) ? $params['search_vals'][ $index ] : '';
				$exp_search_val = $this->format_advanced_search_value( array(
					'search_val' => $exp_search_val,
					'selected_search_operator' => $params['selected_search_operators'][ $index ],
				) );
				$search_params = array('cond_terms_col_name' => $cond_terms_col_name[$index],
										'cond_terms_col_value' => $cond_terms_col_value[$index],
										'cond_terms_operator' => $cond_terms_operator[$index],
										'SM_IS_WOO30' => (!empty($params['SM_IS_WOO30'])) ? $params['SM_IS_WOO30'] : '',
										'post_type' => (!empty($params['post_type'])) ? $params['post_type'] : array());

				$cond_terms = apply_filters('sm_search_terms_condition_start', $cond_terms, $search_params);
				$query_advanced_search_taxonomy_id = $wpdb->prepare( "SELECT {$wpdb->prefix}term_taxonomy.term_taxonomy_id
														FROM {$wpdb->prefix}term_taxonomy
														JOIN {$wpdb->prefix}terms
															ON ( {$wpdb->prefix}terms.term_id = {$wpdb->prefix}term_taxonomy.term_id)
														WHERE ".$cond_terms, $exp_search_val );
				$result_advanced_search_taxonomy_id = $wpdb->get_col ( $query_advanced_search_taxonomy_id );

				//Query to get the child taxonomy ids 
				$query_advanced_search_parent_id = $wpdb->prepare( "SELECT {$wpdb->prefix}term_taxonomy.term_taxonomy_id
													FROM {$wpdb->prefix}term_taxonomy
														JOIN {$wpdb->prefix}terms 
														ON ( {$wpdb->prefix}term_taxonomy.parent = {$wpdb->prefix}terms.term_id )    
													WHERE {$wpdb->prefix}terms.slug  = %s", $exp_search_val ); 

				$result_advanced_search_parent_id = $wpdb->get_col( $query_advanced_search_parent_id);

				if (!empty($result_advanced_search_taxonomy_id))  {

					$terms_search_result_flag = ( $index == (sizeof($cond_terms_array) - 1) ) ? ', '.$params['search_query_index'] : ', 0';
					$terms_advanced_search_select = "SELECT DISTINCT ".$wpdb->prefix."posts.id, ". $params['search_query_index'];

					$search_params['terms_search_result_flag'] = $terms_search_result_flag;


					$result_taxonomy_ids = implode(",",$result_advanced_search_taxonomy_id);
					$result_taxonomy_ids .= (!empty($result_advanced_search_parent_id)) ? ','.implode(',',$result_advanced_search_parent_id) : ''; //condition added for displaying child taxonomies when searching for parent taxonomies
					$search_params['search_query_index'] = $params['search_query_index'];
					$search_params['result_taxonomy_ids'] = $result_taxonomy_ids;

					$terms_advanced_search_from = "FROM {$wpdb->prefix}posts
													JOIN {$wpdb->prefix}term_relationships
														ON ({$wpdb->prefix}term_relationships.object_id = {$wpdb->prefix}posts.id
															AND {$wpdb->prefix}posts.post_type IN ('". implode( "','", $params['post_type'] ) ."') )";

					$terms_advanced_search_where = "WHERE {$wpdb->prefix}term_relationships.term_taxonomy_id IN (". $result_taxonomy_ids .")";

					//Code for handling blank taxonomy search conditions
					if( !empty($search_params['cond_terms_operator']) && $search_params['cond_terms_operator'] == 'NOT LIKE' ) {

						$tt_ids_to_exclude = array();
						$taxonomy = apply_filters('sm_search_format_query_terms_col_name', $search_params['cond_terms_col_name'], $search_params);
						if( ( $search_params['cond_terms_col_value'] == "''" || empty( $search_params['cond_terms_col_value'] ) ) ) {

							if (version_compare ( $wp_version, '4.5', '>=' )) {
								$tt_ids_to_exclude = get_terms( array(
															'taxonomy' => $taxonomy,
															'fields' => 'tt_ids',
													));	
							} else {
								$tt_ids_to_exclude = get_terms( $taxonomy, array(
															'fields' => 'tt_ids',
													));	
							}
							
						} else {
							$term_meta = get_term_by( 'slug', $search_params['cond_terms_col_value'], $taxonomy );
							if ( ! is_wp_error( $term_meta ) && ! empty( $term_meta->term_taxonomy_id ) ) {
								$tt_ids_to_exclude[] = $term_meta->term_taxonomy_id;
							}
						}

						if( ! empty( $tt_ids_to_exclude ) ) {
							$search_params['tt_ids_to_exclude'] = $tt_ids_to_exclude;
							$terms_advanced_search_where .= " AND {$wpdb->prefix}posts.ID NOT IN ( SELECT object_id 
																			FROM {$wpdb->prefix}term_relationships
																			WHERE term_taxonomy_id IN (". implode(",", $tt_ids_to_exclude) .") )";
						}
					}

					$terms_advanced_search_select_old = $terms_advanced_search_select;
					$terms_advanced_search_select = apply_filters('sm_search_query_terms_select', $terms_advanced_search_select, $search_params);
					$terms_advanced_search_from	= apply_filters('sm_search_query_terms_from', $terms_advanced_search_from, $search_params);
					$terms_advanced_search_where	= apply_filters('sm_search_query_terms_where', $terms_advanced_search_where, $search_params);

					if( $terms_advanced_search_select_old == $terms_advanced_search_select ) {
						$terms_advanced_search_select .= " ,1  ";
					}

					//Query to find if there are any previous conditions
					$count_temp_previous_cond = $wpdb->query("UPDATE {$wpdb->base_prefix}sm_advanced_search_temp 
																SET flag = 0
																WHERE flag = ". $params['search_query_index']);
					if ( ! empty( $this->previous_cond_has_results ) ) {	
					//Code to handle condition if the ids of previous cond are present in temp table
						if ( ( 0 === $index && $count_temp_previous_cond > 0 ) || ( ! empty( $result_terms_search ) ) || $index > 0 ) {
							$terms_advanced_search_from .= " JOIN ".$wpdb->base_prefix."sm_advanced_search_temp ON (".$wpdb->base_prefix."sm_advanced_search_temp.product_id = ".$wpdb->prefix."posts.id)";

							$terms_advanced_search_where .= "AND ".$wpdb->base_prefix."sm_advanced_search_temp.flag = 0";
						}

						$result_terms_search = array();

						if (!empty($terms_advanced_search_select ) && !empty($terms_advanced_search_from ) && !empty($terms_advanced_search_where )) {
							$terms_advanced_search_select = esc_sql( $terms_advanced_search_select );
							$query_terms_search = "REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp ( {$terms_advanced_search_select} {$terms_advanced_search_from} {$terms_advanced_search_where} )";
							$result_terms_search = $wpdb->query ( $query_terms_search );
						}
					}
					do_action('sm_search_terms_condition_complete',$result_terms_search,$search_params);
				}

				//Code to delete the unwanted post_ids
				$wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp WHERE flag = 0");

				$index++;
			}
			$this->previous_cond_has_results = ( ! empty( $result_terms_search ) ) ? true : false;
			do_action('sm_search_terms_conditions_array_complete',$search_params);

			//Query to reset the cat_flag
			$wpdb->query("UPDATE {$wpdb->base_prefix}sm_advanced_search_temp SET cat_flag = 0");
		}

		/**
		 * Function to process meta_query for advanced search for any meta tables like 'postmeta', 'termmeta', etc.
		 *
		 * @param array $params The search condition params.
		 * @return void.
		 */
		public function process_meta_table_search_query( $params = array() ){

			global $wpdb;

			$search_query = ( ! empty( $params['search_query'] ) ) ? $params['search_query'] : array();
			$meta_table = ( ! empty( $params['table_nm'] ) ) ? $params['table_nm'] : '';
			if( empty( $params ) || empty( $search_query ) ){
				return;
			}

			$meta_conditions = explode( " && ",$search_query['cond_'.$meta_table] );

			$col_names = (!empty($search_query['cond_'. $meta_table .'_col_name'])) ? explode(" && ",$search_query['cond_'. $meta_table .'_col_name']) : array();
			$col_values = (!empty($search_query['cond_'. $meta_table .'_col_value'])) ? explode(" && ",$search_query['cond_'. $meta_table .'_col_value']) : array();
			$col_ops = (!empty($search_query['cond_'. $meta_table .'_operator'])) ? explode(" && ",$search_query['cond_'. $meta_table .'_operator']) : array();

			$index = 0;
			$results = array();
			foreach( $meta_conditions as $cond ) {

				$flag = ', '.$params['search_query_index'];

				$search_params = array_merge( array( 'cond_'. $meta_table .'_col_name' => (!empty($col_names[$index])) ? trim($col_names[$index]) : '',
										'cond_'. $meta_table .'_col_value' => (!empty($col_values[$index])) ? trim($col_values[$index]) : '',
										'cond_'. $meta_table .'_operator' => (!empty($col_ops[$index])) ? trim($col_ops[$index]) : '',
										'flag' => $flag
									), $params );

				$cond = apply_filters('sm_search_'. $meta_table .'_condition_start', $cond, $search_params);

				$search_params['cond_'.$meta_table] = $cond;

				$select = 'SELECT DISTINCT '.$wpdb->prefix.''.$meta_table.'.'. $params['meta_key_col'] .' '. $flag .' ,0 ';
				$from = "FROM ".$wpdb->prefix."".$meta_table;
				$where = 'WHERE '.$cond;

				$select = apply_filters( 'sm_search_query_'. $meta_table .'_select', $select, $search_params) ;
				$from	= apply_filters( 'sm_search_query_'. $meta_table .'_from', $from, $search_params );
				$where	= apply_filters( 'sm_search_query_'. $meta_table .'_where', $where, $search_params );

				//Query to find if there are any previous conditions
				$count_temp_previous_cond = $wpdb->query("UPDATE {$wpdb->base_prefix}sm_advanced_search_temp 
															SET flag = 0
															WHERE flag = ". $params['search_query_index']);

				//Code to handle condition if the ids of previous cond are present in temp table
				if ( ! empty( $this->previous_cond_has_results ) ) {
					if ( ( 0 === $index && $count_temp_previous_cond > 0 ) || ( ! empty( $results ) ) || $index > 0 ) {
						$from .= apply_filters( 'sm_search_query_'. $meta_table .'_join', " JOIN ".$wpdb->base_prefix."sm_advanced_search_temp ON (".$wpdb->base_prefix."sm_advanced_search_temp.product_id = {$wpdb->prefix}". $meta_table .".". $params['meta_key_col'] .")", $search_params );
						$where .= " AND ".$wpdb->base_prefix."sm_advanced_search_temp.flag = 0";
					}

					$results = array();

					if ( ( ! empty( $select ) ) && ( ! empty( $from ) ) && ( ! empty( $where ) ) ) {
						$select = esc_sql( $select );
						$exp_search_val = ( is_array( $params['search_vals'] ) && ( ! empty( $params['search_vals'][ $index ] ) ) ) ? $params['search_vals'][ $index ] : '';
						$exp_search_val = $this->format_advanced_search_value( array(
							'search_val' => $exp_search_val,
							'selected_search_operator' => $params['selected_search_operators'][ $index ],
						) );
						$query_postmeta_search = $wpdb->prepare(
							"REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp ( {$select} {$from} {$where} )", 
							$exp_search_val
						); // Prepare a SQL query using $wpdb->prepare for safe execution.
						$results = $wpdb->query ( $query_postmeta_search );
					}
				}

				do_action('sm_search_'. $meta_table .'_condition_complete',$results,$search_params, array(
					'select' => $select,
					'from' => $from,
					'where' => $where
				));

				//Query to delete the unwanted post_ids
				$wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp WHERE flag = 0");

				$index++;
			}
			$this->previous_cond_has_results = ( ! empty( $results ) ) ? true : false;
			do_action('sm_search_'. $meta_table .'_conditions_array_complete',$search_params);
		}

		/**
		 * Function to process meta_query for advanced search for any flat tables like 'posts', 'term_taxonomy', etc.
		 *
		 * @param array $params The search condition params.
		 * @return void.
		 */
		public function process_flat_table_search_query( $params = array() ){

			global $wpdb;

			$search_query = ( ! empty( $params['search_query'] ) ) ? $params['search_query'] : array();
			$table_nm = ( ! empty( $params['table_nm'] ) ) ? $params['table_nm'] : '';
			if( empty( $params ) || empty( $search_query ) ){
				return;
			}

			$conditions = explode(" && ",$search_query['cond_'. $table_nm]);

			$index = 0;
			$results = array();
			foreach ( $conditions as $cond ) {

				$flag = ', '.$params['search_query_index'];
				$cat_flag = ( $index == (sizeof($conditions) - 1) ) ? ", 999" : ', 0';

				$cond = apply_filters( 'sm_search_'. $table_nm .'_condition_start', $cond, $params );

				$search_params = array('cond' => $cond,
										'SM_IS_WOO30' => (!empty($params['SM_IS_WOO30'])) ? $params['SM_IS_WOO30'] : '',
										'post_type' => (!empty($params['post_type'])) ? $params['post_type'] : '',
										'search_query' => $search_query,
										'search_query_index' => $params['search_query_index'],
										'flag' => $flag,
										'cat_flag' => $cat_flag
									);

				$select = "SELECT DISTINCT ".$wpdb->prefix."". $table_nm .".". $params['key_col'] ." ". $flag ." ". $cat_flag ." ";
				$from = " FROM ".$wpdb->prefix."". $table_nm ." ";
				$where = " WHERE ". $cond ." ";

				$select = apply_filters('sm_search_query_'. $table_nm .'_select', $select, $search_params);
				$from	= apply_filters('sm_search_query_'. $table_nm .'_from', $from, $search_params);
				$where	= apply_filters('sm_search_query_'. $table_nm .'_where', $where, $search_params);

				//Query to find if there are any previous conditions
				$count_temp_previous_cond = $wpdb->query("UPDATE {$wpdb->base_prefix}sm_advanced_search_temp 
															SET flag = 0
															WHERE flag = ". $params['search_query_index']);

				if ( ! empty( $this->previous_cond_has_results ) ) {
					//Code to handle condition if the ids of previous cond are present in temp table
					if ( ( 0 === $index && $count_temp_previous_cond > 0 ) || ( ! empty( $results ) ) || $index > 0 ) {
						$from .= " JOIN ".$wpdb->base_prefix."sm_advanced_search_temp ON (".$wpdb->base_prefix."sm_advanced_search_temp.product_id = {$wpdb->prefix}". $table_nm .".". $params['key_col'] .") ";
						$where .= " AND ".$wpdb->base_prefix."sm_advanced_search_temp.flag = 0 ";
					}

					$results = array();

					if ( ( ! empty( $select ) ) && ( ! empty( $from ) ) && ( ! empty( $where ) ) ) {
						$select = esc_sql( $select ); 
						$from = esc_sql( $from );
						$exp_search_val = ( is_array( $params['search_vals'] ) && ( ! empty( $params['search_vals'][ $index ] ) ) ) ? $params['search_vals'][ $index ] : '';
						$exp_search_val = $this->format_advanced_search_value( array(
							'search_val' => $exp_search_val,
							'selected_search_operator' => $params['selected_search_operators'][ $index ],
						) );
						$query_posts_search = $wpdb->prepare(
							"REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp ( {$select} {$from} {$where} )", 
							$exp_search_val
						); // Prepare a SQL query using $wpdb->prepare for safe execution.
						$results = $wpdb->query ( $query_posts_search );
					}
				}
				//Query to delete the unwanted post_ids
				$wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp WHERE flag = 0");

				do_action('sm_search_'. $table_nm .'_condition_complete',$results,$search_params);

				$index++;
			}
			$this->previous_cond_has_results = ( ! empty( $results ) ) ? true : false;
			do_action('sm_search_'. $table_nm .'_conditions_array_complete',$search_params);
		}

		/**
		 * Function for modifying advanced search condition for flat tables.
		 *
		 * @param string $cond The search query condition.
		 * @param array $params The search condition params.
		 * @return string updated query condition.
		 */
		public function modify_posts_advanced_search_condition( $cond = '', $params = array() ){
			if ( ( empty( $params ) ) || ( ! is_array( $params ) ) || empty( $params['post_type'] ) ) {
				return '';
			}
			global $wpdb;
			return $cond . ( 
				! empty( $params['post_type'] ) 
				? " AND " . $wpdb->prefix . "posts.post_type IN ('" . 
					( is_array( $params['post_type'] ) ? implode( "','", $params['post_type'] ) : $params['post_type'] ) . 
				  "')" 
				: '' 
			);
			
		}

		/**
		 * Function for modifying advanced search from clause for meta tables.
		 *
		 * @param string $from The search query from clause.
		 * @param array $params The search condition params.
		 * @return string updated from clause.
		 */
		public function modify_postmeta_advanced_search_from( $from = '', $params = array() ){
			global $wpdb;
			$join = " JOIN ".$wpdb->prefix."posts ON( ".$wpdb->prefix."posts.id = ".$wpdb->prefix."". $params['table_nm'] .".". $params['meta_key_col'] ." AND ".$wpdb->prefix."posts.post_type IN ('". implode( "','", $params['post_type'] ) ."') )";
			return $from . ( ( ! empty( $params['post_type'] ) && strpos( $from, $join ) === false ) ? $join : '' );
		}

		/**
		 * Function for handling ANDing with att and other fields for advanced search.
		 *
		 * @param array $params The search condition params.
		 * @return void.
		 */
		public function get_matching_children_advanced_search( $params = array() ){
			
			global $wpdb;

			$child_where_cond = '';

			if ( !empty( $advanced_search_query_string['cond_terms'] ) || !empty( $advanced_search_query_string['cond_postmeta'] ) ) {
				$child_where_cond = " WHERE ".$wpdb->prefix."posts.id IN (SELECT product_id FROM {$wpdb->base_prefix}sm_advanced_search_temp ) ";
			}

			//Query to get the variations of the parent product in result set
			$wpdb->query ( "REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp
										(SELECT DISTINCT {$wpdb->prefix}posts.id ,". $params['search_query_index'] .", 0
										FROM {$wpdb->prefix}posts 
											JOIN {$wpdb->base_prefix}sm_advanced_search_temp 
												ON ({$wpdb->base_prefix}sm_advanced_search_temp.product_id = {$wpdb->prefix}posts.post_parent
													AND {$wpdb->base_prefix}sm_advanced_search_temp.cat_flag = 999
													AND {$wpdb->base_prefix}sm_advanced_search_temp.flag = ". $params['search_query_index'] .")
										". $child_where_cond .")" );
		}

		// Function to build query sort params from supplied arguments
		public function build_query_sort_params( $args = array() ){
			$query_sort_params = array();
			$sort_params = ( ! empty( $args['sort_params'] ) ) ? esc_sql( $args['sort_params'] ) : array();

			if( empty( $sort_params ) ){
				return $query_sort_params;
			}

			$numeric_meta_cols = ( ! empty( $args['numeric_meta_cols'] ) ) ? $args['numeric_meta_cols'] : array();
			$data_cols = ( ! empty( $args['data_cols'] ) ) ? $args['data_cols'] : array();

			$tables = array( 'terms' );
			foreach( $this->advanced_search_table_types as $table_types ){
				if( ! is_array( $table_types ) ) {
					continue;
				}
				$tables = array_merge( $tables, array_keys( $table_types ) );
			}

			if( !empty( $sort_params['column'] ) && !empty( $sort_params['sortOrder'] ) ) {

				$col_exploded = explode( "/", $sort_params['column'] );

				$query_sort_params['table'] = ( ! empty( $col_exploded[0] ) && in_array( $col_exploded[0], $tables ) ) ? $col_exploded[0] : '';

				if ( sizeof( $col_exploded ) > 2) {
					$col_meta = explode( "=", $col_exploded[1] );
					$query_sort_params['column_nm'] = ( ! empty( $col_meta[0] ) && ( 'meta_key' === $col_meta[0] || in_array( $col_meta[0], $data_cols ) ) ) ? $col_meta[0] : '';

					if( 'meta_key' === $query_sort_params['column_nm'] && ( ! empty( $col_meta[1] ) && in_array( $col_meta[1], $data_cols ) ) ) {
						$query_sort_params['sort_by_meta_key'] = $col_meta[1];
						$query_sort_params['column_nm'] = ( ! empty( $numeric_meta_cols ) && in_array( $col_meta[1], $numeric_meta_cols ) ) ? 'meta_value_num' : 'meta_value';
					}
				} else {
					$query_sort_params['column_nm'] = ( ! empty( $col_exploded[1] ) && in_array( $col_exploded[1], $data_cols ) ) ? $col_exploded[1] : '';
				}

				$query_sort_params['sortOrder'] = ( ! empty( $sort_params['sortOrder'] ) && in_array( strtoupper( $sort_params['sortOrder'] ), array('ASC', 'DESC') ) ) ? strtoupper( $sort_params['sortOrder'] ) : '';
			}
			return $query_sort_params;
		}

		// Function to return saved col_model from transients
		public function get_col_model( $dashboard_key = '' ){

			if( empty( $dashboard_key ) ){
				return array();
			}

			$store_model_transient = get_transient( 'sa_sm_'. $dashboard_key );

			if( ! empty( $store_model_transient ) && !is_array( $store_model_transient ) ) {
				$store_model_transient = json_decode( $store_model_transient, true );
			}
			
			return ( ! empty( $store_model_transient['columns'] ) ) ? $store_model_transient['columns'] : array();
		}

		
		public function create_search_condition( $search_params = array() ){

			global $wpdb;

			if( empty( $search_params ) ){
				return array();
			}

			$db_table_name = ( ! empty( $search_params['search_string']['table_name'] ) ) ? $search_params['search_string']['table_name'] : '';
			$table_name = substr( $db_table_name, strlen( $wpdb->prefix ) );
			$is_meta_table = ( false !== strpos( $db_table_name, 'meta' ) ) ? true : false;
			$is_taxonomy_cond = ( ! empty( $search_params['is_taxonomy'] ) ) ? $search_params['is_taxonomy'] : false;
			$db_operator_map = array(
										'is'		=> 'LIKE',
										'is not'	=> 'NOT LIKE'
			);

			$search_col = apply_filters('sm_search_format_query_'. $table_name .'_col_name', $search_params['search_col'], $search_params);
			$search_value = apply_filters('sm_search_format_query_'. $table_name .'_col_value', $search_params['search_value'], $search_params);
			$additional_cond = ( ! empty( $is_meta_table ) ) ? " AND ". $db_table_name .".meta_key LIKE '". $search_col . "' " : "";


			if( in_array( $search_params['search_data_type'], array( "number", "numeric" ) ) ) {
				$search_value = ( empty( $search_value ) && '0' != $search_value ) ? "''" : $search_value;
				if( $is_meta_table && '0' == $search_value && ( '=' === $search_params['search_operator'] || '!=' === $search_params['search_operator'] ) ) {
					$search_value = "'". $search_value . "'";
				}	 
			} else if ( $search_params['search_data_type'] == "date" || $search_params['search_data_type'] == "sm.datetime" || ! empty( $db_operator_map[$search_params['search_operator']] ) ) {
				$search_value = " '" . $search_value ."' ";

				if( ! empty( $db_operator_map[$search_params['search_operator']] ) ){
					$search_params['search_operator'] = $db_operator_map[$search_params['search_operator']];
				} else {
					$additional_cond .= " AND ". $db_table_name .".". $search_col ." NOT IN ('0', '1970-01-01 00:00:00', '1970-01-01', '', 0)";
				}
				
			} else {
				$search_value = " '%" . $search_value ."%' ";
			}
			
			$cond = "( ". $db_table_name .".". ( ( ! empty( $is_meta_table ) ) ? 'meta_value' : $search_col ) ." ". $search_params['search_operator'] ." ". $search_value ." ". $additional_cond .")";

			return apply_filters('sm_search_'. $table_name .'_cond', $cond, $search_params) ." && ";
		}

		/**
			* Get columns from flat tables.
			* @param  array $args actual flat table name, flat table name, post type/ type and id column to get columns from flat tables.
			* @return array $col_model Updated column model.
		*/
		public function get_flat_table_columns( $args = array() ) {
			
			if ( empty( $args ) || ( ! empty( $args ) && empty( $args['table_nm'] ) ) ){
				return array();
			}
			
			global $wpdb;
			$results = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}". $args['table_nm'], 'ARRAY_A' );
			$num_rows = $wpdb->num_rows;
			if ( empty( $num_rows ) || empty( $results )  ) {
				return array();
			}

			$col_model = array();
			$ignored_cols = apply_filters( 'sm_ignored_cols', ( ( ! empty( $args['ignored_cols'] ) && is_array( $args['ignored_cols'] ) ) ? $args['ignored_cols'] : array() ) );
			$uneditable_cols = apply_filters( 'sm_uneditable_cols', ( ( ! empty( $args['uneditable_cols'] ) && is_array( $args['uneditable_cols'] ) ) ? $args['uneditable_cols'] : array() ) );
			$col_titles = apply_filters( 'sm_flat_table_col_titles', array() );

			foreach ( $results as $result ) {
				$field_nm = ( ! empty( $result['Field'] ) ) ? $result['Field'] : '';
				if( empty( $field_nm ) || ( is_array( $ignored_cols ) && isset( $ignored_cols[ $args['table_nm'] ] ) && is_array( $ignored_cols[ $args['table_nm'] ] ) && in_array( $field_nm, $ignored_cols[ $args['table_nm'] ] ) ) ) {
					continue;
				}
				$col_args = apply_filters( 'sm_get_custom_cols', array(
					'table_nm' => $args['table_nm'],
					'col'      => $field_nm,
					'db_type'  => ( ! empty( $result['Type'] ) ) ? $result['Type'] : '',
					'editable' => ( in_array( $field_nm, $uneditable_cols ) ) ? false : true,
					'visible_cols' => ( ! empty( $args['visible_columns'] ) ) ? $args['visible_columns'] : array(),
					'name'		=> ( ! empty( $col_titles[ $args['table_nm'] ] ) && is_array( $col_titles[ $args['table_nm'] ] ) && ! empty( $col_titles[ $args['table_nm'] ][$field_nm] ) ) ? $col_titles[ $args['table_nm'] ][$field_nm] : ''
				), $field_nm );
				if ( ! empty( $col_args ) ) {
					if( is_array( $col_args ) && ! empty( $col_args[0] ) && is_array( $col_args[0] ) && empty( $col_args['table_nm'] ) ){
						foreach ( $col_args as $arg ) {
							$col_model[] = $this->get_default_column_model( $arg );
						}
						continue;
					}
					$col_model[] = $this->get_default_column_model( $col_args );
				} 
			}
			return $col_model;
		}

		/**
			* Get columns from meta tables.
			* @param  array $args actual meta table name, meta table name, post type/ type and id column to get columns from meta tables.
			* @return array $col_model Updated column model.
		*/
		public function get_meta_table_columns( $args = array() ) {
			if ( empty( $args ) || empty( $args['table_nm'] ) || empty( $args['post_type'] ) || empty( $args['meta_table_nm'] ) || empty( $args['child_id'] ) || empty( $args['parent_id'] ) ) {
				return array();
			}

			global $wpdb;
			$table_nm = $wpdb->prefix.''.$args['table_nm'];
			$meta_table_nm = $wpdb->prefix.''.$args['meta_table_nm'];
			$post_type_cond = ( is_array( $this->post_type ) ) ? " AND " . $table_nm . "." . $args['post_type'] . "IN ('" . implode( "','", $this->post_type ) . "')" : " AND {$wpdb->prefix}" . $args['table_nm'] . "." . $args['post_type'] . " = '" . $this->post_type . "'";
			$results = $wpdb->get_results( "SELECT DISTINCT " . $meta_table_nm . ".meta_key,
												" . $meta_table_nm . ".meta_value
											FROM " . $meta_table_nm . "
												JOIN " . $table_nm . " ON (" . $table_nm . ".{$args['parent_id']} = " . $meta_table_nm . "." . $args['child_id'] . ") WHERE " . $meta_table_nm . ".meta_key != '' 
												AND " . $meta_table_nm . ".meta_key NOT LIKE 'free-%'
												AND " . $meta_table_nm . ".meta_key NOT LIKE '_oembed%'" . $post_type_cond . "
											GROUP BY ". $meta_table_nm . ".meta_key", 'ARRAY_A');
			$num_rows = $wpdb->num_rows;
			if( ! empty( $results ) && $num_rows > 0 ) {
				$meta_keys = array();
				foreach ( $results as $key => $col ) {
					if ( empty( $col['meta_value'] ) || '1' == $col['meta_value']  || '0.00' == $col['meta_value'] ) {
						$meta_keys [] = $col['meta_key']; //TODO: if possible store in db instead of using an array
					}
					unset( $results[ $key ] );
					$results[ $col['meta_key'] ] = $col;
				}
				//not in 0 added for handling empty date columns
				if ( ! empty( $meta_keys ) ) { 
					$results_meta_value = $wpdb->get_results ( "SELECT " . $meta_table_nm . ".meta_key,
																	" . $meta_table_nm . ".meta_value
																FROM " . $meta_table_nm."
																	JOIN " . $table_nm . " ON (" . $table_nm . ".{$args['parent_id']} = " . $meta_table_nm . ".{$args['child_id']})
																WHERE " . $table_nm . "." . $args['post_type'] . " = '" . $this->dashboard_key . "'
																	AND " . $meta_table_nm . ".meta_value NOT IN ('','0','0.00','1')
																	AND " . $meta_table_nm . ".meta_key IN ('" . implode( "','",$meta_keys ) . "')
																GROUP BY " . $meta_table_nm . ".meta_key", 'ARRAY_A' );
					$num_rows_meta_value = $wpdb->num_rows;
					if( ! empty( $results_meta_value ) && $num_rows_meta_value > 0 ) {
						foreach ( $results_meta_value as $result_meta_value ) {
							if ( isset( $results [ $result_meta_value['meta_key'] ] ) ) {
								$results [ $result_meta_value['meta_key'] ]['meta_value'] = $result_meta_value['meta_value'];
							}
						}
					}
				}
				//Filter to add custom meta columns for custom plugins
				$results = apply_filters( 'sm_default_dashboard_model_meta_cols', $results );
				$meta_count = 0;
				//Code for pkey column for meta table
				$col_model[] = $this->get_default_column_model( array(
					'table_nm' 			=> $args['meta_table_nm'],
					'col'				=> $args['child_id'],
					'type'				=> 'numeric',
					'hidden'			=> true,
					'allow_showhide'	=> false,
					'visible_cols' 		=> ( ! empty( $args['visible_columns'] ) ) ? $args['visible_columns'] : array(),
					'editor'			=> false,
				) );
				foreach ( $results as $col ) {
					$meta_key = ( ! empty( $col['meta_key'] ) ) ? $col['meta_key'] : '';
					$meta_value = ( ! empty( $col['meta_value'] ) || 0 === $col['meta_value'] ) ? $col['meta_value'] : '';
					$col_model[] = $this->get_default_column_model( apply_filters( 'sm_meta_col_model_args', array(
						'table_nm' 	=> $args['meta_table_nm'],
						'col'		=> $meta_key,
						'is_meta'	=> true,
						'col_value'	=> $meta_value,
						'visible_cols' => ( ! empty( $args['visible_columns'] ) ) ? $args['visible_columns'] : array(),
						'hidden'	=> ( $meta_count > 5 ) ? true : false
					) ) );
					$meta_count++;
				}
				return $col_model;
			}
		}

		/**
		 * Function for formatting terms data.
		 * @param  array $args terms related data.
		 * @return array $args['items'] updated items array.
		*/
		public function format_terms_data( $args = array() ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['terms_visible_cols'] ) || empty( $args['ids'] ) || empty( $args['items'] ) || empty( $args['id_name'] ) ) {
				return $args['items'];
			}

	        $valid_term_visible_cols = array_filter( array_keys( $args['terms_visible_cols'] ), function( $taxonomy ) {
	        		return taxonomy_exists( $taxonomy );
	        } );
	        if ( empty( $valid_term_visible_cols ) ) {
	        	return $args['items'];
	        }
	        $terms_objects = wp_get_object_terms( $args['ids'], $valid_term_visible_cols, 'orderby=none&fields=all_with_object_id' );

			// Code to create and initialize all the terms columns.
        	foreach ( $args['items'] as $key => $item ) {
        		foreach ( array_keys( $args['terms_visible_cols'] ) as $col ) {
        			$terms_key = 'terms_' . strtolower( str_replace( ' ', '_', $col ) );
        			$args['items'][ $key ][ $terms_key ] = '';	
        		}
        	}

	        if ( empty( $terms_objects ) || ( ! is_array( $terms_objects ) ) ) {
	        	return $args['items'];
	        }
	        $terms_data = array();	
	        //Code for creating the terms data array
			foreach ( $terms_objects as $term_obj ) {
				if ( empty( $term_obj->object_id ) || empty( $term_obj->taxonomy ) ) {
					continue;
				}
				if ( empty( $terms_data[ $term_obj->object_id ] ) ) {
					$terms_data[ $term_obj->object_id ] = array();
				}

				$taxonomy_nm = $term_obj->taxonomy;

				// Code for export CSV functionality
				if ( ! empty( $this->req_params[ 'cmd' ] ) && ( 'get_export_csv' === $this->req_params[ 'cmd' ] ) ) {
					if ( ! isset( $terms_data[ $term_obj->object_id ][ $taxonomy_nm ] ) ){
						$terms_data[ $term_obj->object_id ][ $taxonomy_nm ] = array();
					}
					$terms_data[ $term_obj->object_id ][ $taxonomy_nm ][] = $term_obj->term_id;
					continue;
				}

				//Code for handling multilist data
	        	if ( is_array( $args['data_cols_multilist'] ) && ( false !== array_search( $taxonomy_nm, $args['data_cols_multilist'] ) ) && ( ( isset( $args['postmeta_cols'] ) ) && ( is_array( $args['postmeta_cols'] ) && ( false === array_search( $taxonomy_nm, $args['postmeta_cols'] ) ) ) ) ) { //added postmeta check condition for multilist columns
	        		if ( empty( $term_obj->name ) || empty( $term_obj->term_id ) ) {
	        			continue;
	        		}
					$multilist_value = $term_obj->name;
					$multilist_separator = ', ';

					if ( ! empty( $args['terms_visible_cols'][ $taxonomy_nm ] ) ) {
						if ( ! empty( $args['terms_visible_cols'][ $taxonomy_nm ][ $term_obj->term_id ] ) ) {
							$multilist_value = ( ! empty( $args['terms_visible_cols'][ $taxonomy_nm ][ $term_obj->term_id ]['title'] ) ) ? $args['terms_visible_cols'][ $taxonomy_nm ][ $term_obj->term_id ]['title'] : $multilist_value;
						}
					}
					if ( empty( $terms_data[ $term_obj->object_id ][ $taxonomy_nm ] ) ) {
		        		$terms_data[ $term_obj->object_id ][ $taxonomy_nm ] = $multilist_value;
		        	} else {
		        		$terms_data[ $term_obj->object_id ][ $taxonomy_nm ] .= $multilist_separator . "" . $multilist_value;
		        	}
	        	} elseif ( is_array( $args['data_cols_dropdown'] ) && ( false !== array_search( $taxonomy_nm, $args['data_cols_dropdown'] ) ) ) {
	        			$terms_data[ $term_obj->object_id ][ $taxonomy_nm ] = $term_obj->term_id;
	        	} else {
	        		$terms_data[ $term_obj->object_id ][ $taxonomy_nm ] = $term_obj->name;
	        	}
			}
	        foreach ( $args['items'] as $key => $item ) {
	        	$id = ( ! empty( $item[ $args['id_name'] ] ) ) ? $item[ $args['id_name'] ] : '';
				if ( empty( $id ) ) continue;

				foreach ( array_keys( $args['terms_visible_cols'] ) as $visible_taxonomy ) {

					$terms_key = 'terms_' . strtolower( str_replace( ' ', '_', $visible_taxonomy ) );
					// Code for export CSV functionality
					if ( ! empty( $this->req_params[ 'cmd' ] ) && ( 'get_export_csv' === $this->req_params[ 'cmd' ] ) ) {
						if ( empty( $terms_data[ $id ][ $visible_taxonomy ] ) || ( ! is_array( $terms_data[ $id ][ $visible_taxonomy ] ) ) ) {
							$args['items'][ $key ][ $terms_key ] = '';
							continue;
						}
						rsort( $terms_data[ $id ][ $visible_taxonomy ] );
						$args['items'][ $key ][ $terms_key ] = sa_sm_format_term_ids( $terms_data[ $id ][ $visible_taxonomy ], $visible_taxonomy );
						continue;
					}

					$args['items'][ $key ][ $terms_key ] = ( ! empty( $terms_data[ $id ][ $visible_taxonomy ] ) ) ? $terms_data[ $id ][ $visible_taxonomy ] : '';

				}	        			
	        }
	        return $args['items'];
		}

	    /**
		 * Function to Update terms table data.
		 * @param  array $args terms related data.
		 * @return result of function call.
		 */
		public function update_terms_table_data( $args = array() ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['id'] ) || empty( $args['update_column'] ) ) {
				return;
			}

	    	if ( ( ! empty( $args['data_cols_multiselect'] ) ) && ( false !== array_search( $args['update_column'], $args['data_cols_multiselect'] ) ) ) {

	    		$actual_val = ( ! empty( $args['data_cols_multiselect_val'][ $args['update_column'] ] ) ) ? $args['data_cols_multiselect_val'][ $args['update_column'] ] : array();
	    		if ( empty( $actual_val ) ) {
	    			return;
	    		}
				$term_ids = array_map( 'intval', explode( ",", $args['value'] ) );
				if ( empty( $term_ids ) ) {
					return;
				}
				$result = wp_set_object_terms( $args['id'], $term_ids, $args['update_column'] );
	    	} elseif ( ( ! empty( $args['data_cols_list'] ) ) && ( false !== array_search( $args['update_column'], $args['data_cols_list'] ) ) ) {
	    		$actual_val = ( ! empty( $args['data_cols_list_val'][ $args['update_column'] ] ) ) ? $args['data_cols_list_val'][ $args['update_column'] ] : array();
	    		if ( empty( $actual_val ) ) {
	    			return;
	    		}
				$edited_values = explode( ", ", $args['value'] );
				if ( empty( $edited_values ) || ( ! is_array( $edited_values ) ) ) {
					return;
				}
				foreach ( $edited_values as $edited_value ) {
					$term_id = array_search( $edited_value, $actual_val );
					if ( false === $term_id ) {
						if ( ! isset( $actual_val[ $edited_value ] ) ) {
							continue;
						}
						$term_id = intval( $edited_value );
					}
					if ( ! empty( $term_id ) ) {
						$term_ids[] = $term_id;
					}
				}	
				if ( empty( $term_ids ) ) {
					return;
				}
				$result = wp_set_object_terms( $args['id'], $term_ids, $args['update_column'] );
	    	}
			return $result;
	    }

	    /**
	     * Function to get query for sort terms table column.
		 * @param  array $args sort params.
		 * @return string $join updated join query.
		 */
		public function terms_table_column_sort_query( $args = array() ) {
			global $wpdb;
			if ( empty( $args ) || ( ! is_array( $args ) ) || ( empty( $args['col_name'] ) ) ) {
				return $args['join'];
			}
			$join_condition = "AND " . $wpdb->prefix . "term_taxonomy.taxonomy = '" . $args['col_name'] . "'";
			$join_condition = ( ! empty( $args['sort_params'] ) ) ? apply_filters( 'sm_terms_sort_join_condition', $join_condition, $args['sort_params'] ) : $join_condition;
			// Query to get the ordered term_taxonomy_ids of the taxonomy being sorted.
			$taxonomy_ids = $wpdb->get_col( $wpdb->prepare( "SELECT {$wpdb->prefix}term_taxonomy.term_taxonomy_id
																		FROM {$wpdb->prefix}term_taxonomy
																			JOIN {$wpdb->prefix}terms
																			ON ( {$wpdb->prefix}terms.term_id = {$wpdb->prefix}term_taxonomy.term_id 
																				 ". $join_condition ." )
																		WHERE 1=%d", 1 ) );
			$wpdb->num_rows;
			if ( empty( $taxonomy_ids ) || ( ! is_array( $taxonomy_ids ) ) || count( $taxonomy_ids ) < 0 || ( false !== strpos( $args['join'], 'taxonomy_sort' ) ) || ( empty( $args['id'] ) ) ) {
				return $args['join'];
			}
			//added 'term_relationships' check as the event gets fired more than once in some cases causing the query to break.
			$args['join'] .= " LEFT JOIN ( SELECT {$wpdb->prefix}term_relationships.object_id as object_id,
														{$wpdb->prefix}terms.name as term_name
													FROM {$wpdb->prefix}term_relationships
														JOIN {$wpdb->prefix}term_taxonomy
															ON( {$wpdb->prefix}term_taxonomy.term_taxonomy_id = {$wpdb->prefix}term_relationships.term_taxonomy_id
																AND {$wpdb->prefix}term_relationships.term_taxonomy_id IN (" .implode(",",$taxonomy_ids). ") ) 
														JOIN {$wpdb->prefix}terms
															ON ( {$wpdb->prefix}terms.term_id = {$wpdb->prefix}term_taxonomy.term_id ) 
															". $join_condition ." ) as taxonomy_sort 
										ON (taxonomy_sort.object_id = " . $args['id'] . ")";
							
			$this->terms_sort_join = true;
			return $args['join'];
		}

	    /**
	     * Function to generate and export the CSV data.
		 * @return void
		 */
		public function get_export_csv() {

			global $current_user;

			ini_set('memory_limit','-1');
			set_time_limit(0);

			$this->req_params['sort_params'] = json_decode( stripslashes( $this->req_params['sort_params'] ), true );
			$this->req_params['table_model'] = json_decode( stripslashes( $this->req_params['table_model'] ), true );

			$current_store_model = get_transient( 'sa_sm_'.$this->dashboard_key );
			if( ! empty( $current_store_model ) && !is_array( $current_store_model ) ) {
				$current_store_model = json_decode( $current_store_model, true );
			}
			$column_model_transient = get_user_meta(get_current_user_id(), 'sa_sm_'.$this->dashboard_key, true);

			// Code for handling views
			if( ( defined('SMPRO') && true === SMPRO ) && ! empty( $this->req_params['is_view'] ) && ! empty( $this->req_params['active_view'] ) ) {
				if( class_exists( 'Smart_Manager_Pro_Views' ) ) {
					$view_obj = Smart_Manager_Pro_Views::get_instance();
					if( is_callable( array( $view_obj, 'get' ) ) ){
						$view_slug = $this->req_params['active_view'];
						$view_data = $view_obj->get($view_slug);
						if( ! empty( $view_data ) ) {
							$this->dashboard_key = $view_data['post_type'];
							$column_model_transient = json_decode( $view_data['params'], true );
							if( !empty( $column_model_transient['search_params'] ) ) {
								if( ! empty( $column_model_transient['search_params']['isAdvanceSearch'] ) ) { // For advanced search
									if( ! empty( $column_model_transient['search_params']['params'] ) && is_array( $column_model_transient['search_params']['params'] ) ) {
										array_walk(
											$column_model_transient['search_params']['params'],
											function ( &$value ) {
												$value = ( ! empty( $value ) ) ? ( json_encode( $value ) ) : '';
											}
										);
									}
								}
								$search_params = $column_model_transient['search_params'];
							}
						}
					}
				}
			}

			if( !empty( $column_model_transient ) && !empty( $current_store_model ) ) {
				$current_store_model = $this->map_column_to_store_model( $current_store_model, $column_model_transient );
			}

			$col_model = (!empty($current_store_model['columns'])) ? $current_store_model['columns'] : array();
			$col_model = apply_filters( 'sm_col_model_for_export', $col_model, $this->req_params );

			$data = $this->get_data_model( $col_model );
			if ( empty( $data ) && is_callable( array( 'Smart_Manager', 'log' ) ) ) {
				Smart_Manager::log( 'error', _x( 'Export CSV: no data found ', 'export data model', 'smart-manager-for-wp-e-commerce' ) );
			}
			$columns_header = $select_cols = $numeric_cols = array();

			$getfield = '';

			foreach( $col_model as $col ) {
				if( empty( $col['exportable'] ) || !empty( $col['hidden'] ) ) {
					continue;
				}

				$columns_header[ $col['data'] ] = $col['key'];

				$getfield .= $col['key'] . ',';

				if( ! empty( $col['values'] ) ) {
					$select_cols[ $col['data'] ] = $col['values'];
				}

				if( ( ( ! empty( $col['type'] ) && 'numeric' === $col['type'] ) ) || ( ( ! empty( $col['validator'] ) && 'customNumericTextEditor' === $col['validator'] ) ) ){
					$numeric_cols[] = $col['data'];
				}
			}

			$fields = substr_replace($getfield, '', -1);
			$each_field = array_keys( $columns_header );

			$view_name = ( ! empty( $this->req_params['active_view'] ) ) ? $this->req_params['active_view'] . '-view_' : '';
			$csv_file_name = sanitize_title(get_bloginfo( 'name' )) . '_' . $this->dashboard_key . '_' . $view_name . gmdate('d-M-Y_H:i:s');
			$csv_file_name = ( ! empty( $this->req_params[ 'storewide_option' ] ) ) ? ( ( 'entire_store' === $this->req_params[ 'storewide_option' ] ? $csv_file_name : $csv_file_name . '_all_products_stock_columns') ) . ".csv" : $csv_file_name . ( ( ! empty( $this->req_params[ 'columnsToBeExported' ] ) && 'visible' === $this->req_params[ 'columnsToBeExported' ] ) ? '_selected_records' : '_selected_products_' . $this->req_params[ 'columnsToBeExported' ] . '_columns' ) . ".csv";
			$escaped_html_columns = apply_filters( 'sa_sm_escaped_html_columns', array( 'posts_post_excerpt', 'posts_post_content' ) );
			foreach( (array) $data['items'] as $row ){

				for($i = 0; $i < count ( $columns_header ); $i++){

					if( $i == 0 ){
						$fields .= "\n";	
					}

					if( !empty( $select_cols[ $each_field[$i] ] ) && !empty( $row[$each_field[$i]] ) ) {
						$row_each_field = !empty( $select_cols[ $each_field[$i] ][ $row[$each_field[$i]] ] ) ? $select_cols[ $each_field[$i] ][ $row[$each_field[$i]] ] : $row[$each_field[$i]];
					} else {
						$row_each_field = !empty($row[$each_field[$i]]) ? $row[$each_field[$i]] : '';
					}
					$array_temp = str_replace(array("\n", "\n\r", "\r\n", "\r"), "\t", $row_each_field);
					$array = str_replace("<br>", "\n", $array_temp);
					$array = str_replace('"', '""', $array);
					if( ! empty( $numeric_cols ) && in_array( $each_field[$i], $numeric_cols ) ){
						$str = $array;
					} else{
						$array = ( ! is_array( $array ) ) ? str_getcsv ( $array , ",", "\"" , "\\") : $array;
						$str = ( $array && is_array( $array ) ) ? implode( ', ', $array ) : '';
					}
					$fields .= ( ! empty( $each_field[ $i ] ) && ! empty( $escaped_html_columns ) && is_array( $escaped_html_columns ) && in_array( $each_field[ $i ], $escaped_html_columns ) ) ? '"'. esc_html( $str ) . '",' : '"'. $str . '",';
				}	
				$fields = substr_replace($fields, '', -1); 
			}

			$upload_dir = wp_upload_dir();
			$file_data = array();
			$file_data['wp_upload_dir'] = $upload_dir['path'] . '/';
			$file_data['file_name'] = $csv_file_name;
			$file_data['file_content'] = $fields;
			if ( empty( $file_data ) && is_callable( array( 'Smart_Manager', 'log' ) ) ) {
				Smart_Manager::log( 'error', _x( 'Export CSV: no file data found ', 'export file data', 'smart-manager-for-wp-e-commerce' ) );
			}

			header("Content-type: text/x-csv; charset=UTF-8"); 
			header("Content-Transfer-Encoding: binary");
			header("Content-Disposition: attachment; filename=".$file_data['file_name']); 
			header("Pragma: no-cache");
			header("Expires: 0");

			while(ob_get_contents()) {
				ob_clean();
			}

			echo $file_data['file_content'];
			
			exit;
		}

		/**
	     * Function to get parent term values for taxonomies.
		 * 
		 * @param array $args array of taxonomy related data.
		 * @return array terms data.
		 */
		public function get_parent_term_values( $args = array() ) {
			if ( empty( $args['include_taxonomy'] ) || ! is_array( $args['taxonomy_obj'] ) ) {
				return;
			}
			$terms_val = array();
			$terms_val_search = array();
			// Code for storing the parent taxonomies titles
			$taxonomy_parents = array();
			foreach ( $args['taxonomy_obj'] as $term_obj ) {
				if ( 'product_cat' === $args['include_taxonomy'] && ( 'product_cat' !== $term_obj->taxonomy ) ) {
					continue;
				}
				if ( empty( $term_obj->parent ) ) {
					$taxonomy_parents[ $term_obj->term_id ] = $term_obj->name;
				}
			}
			foreach ( $args['taxonomy_obj'] as $term_obj ) {
				if ( empty( $terms_val[ $term_obj->taxonomy ] ) ) {
					$terms_val[ $term_obj->taxonomy ] = array();
				}
				$title = ucwords( ( ! empty( $taxonomy_parents[ $term_obj->parent ] ) ) ? ( $taxonomy_parents[ $term_obj->parent ] . ' — ' . $term_obj->name ) : $term_obj->name );
				$terms_val[ $term_obj->taxonomy ][ $term_obj->term_id ] = $title;
				$terms_val_search[ $term_obj->taxonomy ][ $term_obj->slug ] = $title; //for advanced search
				$this->terms_val_parent[ $term_obj->taxonomy ][ $term_obj->term_id ] = array(
					'term' => $term_obj->name,
					'parent' => $term_obj->parent,
					'title' => $title
				);
			}
			return array(
				'terms_val'        => $terms_val,
				'terms_val_search' => $terms_val_search );
		}

		/**
	     * Function to format advanced search value.
		 * 
		 * @param array $params array contains search value, selected search operator.
		 * @return string $params['search_val'] formatted search value.
		 */
		public function format_advanced_search_value( $params = array() ) {
			if ( empty( $params ) || ( ! is_array( $params ) ) || empty( $params['selected_search_operator'] ) ) {
				return;
			}
			global $wpdb;
			$params['search_val'] = trim( $params['search_val'] );
			$params['search_val'] = ( ! empty( $params['selected_search_operator'] ) && ( in_array( $params['selected_search_operator'], array( 'like', 'not like') ) ) ) ? '%'.$wpdb->esc_like( $params['search_val'] ) . '%' : $params['search_val']; // To handle operators like startsWith, endsWith, notStartsWith, notEndswith.
			if ( in_array( $params['selected_search_operator'], array( 'anyOf', 'notAnyOf' ) ) ) { // to handle search values for anyOf, notAnyOf operators.
				$params['search_val'] = array_map( function( $value ) use ( $wpdb ) {
					return '%' . $wpdb->esc_like( trim( $value ) ) . '%';
				}, explode( "|", $params['search_val'] ) );
			}
			return $params['search_val'];
		}

		/**
		 * Generates the WHERE clause for search queries.
		 *
		 * @param array $params The parameters for constructing the WHERE clause.
		 * @return array An array containing the WHERE clause, additional conditions, and the search text.
		 */
		public function get_where_clause_for_search( $params = array() ) {
			if ( empty( $params ) || ( ! is_array( $params ) ) || empty( $params['where'] ) ) {
				return array();
			}
			global $wpdb;
			$where_cond = array();
			$search_text = '';
			// Code for handling advanced search.
			if ( ! empty( $this->req_params ) && ( ! empty( $this->req_params['advanced_search_query'] ) ) && ( '[]' !== $this->req_params['advanced_search_query'] ) && ( false === strpos( $params['where'], 'sm_advanced_search_temp.flag > 0' ) ) ) {
				$params['where'] .= " AND {$wpdb->base_prefix}sm_advanced_search_temp.flag > 0
				AND " . time() . " = " . time(); // Added time() as a unique identification condition so that when using advanced search the query doesn't get cached.
			}
			// Code for handling simple search.
			if ( ! empty( $this->req_params['search_text'] ) ) {
				$store_model_transient = get_transient( 'sa_sm_' . $this->dashboard_key );
				if ( ! empty($store_model_transient) && ( ! is_array( $store_model_transient ) ) ) {
					$store_model_transient = json_decode($store_model_transient, true);
				}
				$col_model = ( ! empty( $store_model_transient['columns'] ) ) ? $store_model_transient['columns'] : array();
				$search_text = $wpdb->_real_escape( $this->req_params['search_text'] );
				$search_val = ( ! empty( $params['optimize_dashboard_speed'] ) ) ? '%s': '%'. $search_text .'%';
				$ignored_cols = array( 'comment_count', 'post_mime_type', 'post_type', 'menu_order' );
				$simple_search_ignored_cols = apply_filters( 'sm_simple_search_ignored_posts_columns', $ignored_cols, $col_model );
				$matchedResults = array();
				// Code for getting users table condition
				if ( ( ! empty( $col_model ) ) && is_array( $col_model ) ) {
					foreach ( $col_model as $col ) {
						if ( empty( $col['src'] ) ) {
							continue;
						}
						$src_exploded = explode( "/", $col['src'] );
						if ( ! empty( $src_exploded[0] ) && ( 'posts' === $src_exploded[0] ) && ( ! in_array( $src_exploded[1], $simple_search_ignored_cols ) ) ) {
							if ( ! empty( $col['selectOptions'] ) ) {
								$matchedResults = preg_grep( '/' . ucfirst( $search_text ) . '.*/', $col['selectOptions'] );
							}
							if ( is_array( $matchedResults ) && ( ! empty( $matchedResults ) ) ) {
								foreach ( array_keys( $matchedResults ) as $search ) {
									$search = ( ! empty( $params['optimize_dashboard_speed'] ) ) ? '%s': '%'. $search .'%';
									if ( false === strpos( $params['where'], "{$wpdb->prefix}posts." . $src_exploded[1] . " LIKE '{$search}'") ) {
										$where_cond[] = "( {$wpdb->prefix}posts." . $src_exploded[1] . " LIKE '{$search}' )";
									}
								}
							} else {
								if ( false === strpos( $params['where'], "{$wpdb->prefix}posts." . $src_exploded[1] . " LIKE '{$search_val}'") ) {
									$where_cond[] = "( {$wpdb->prefix}posts." . $src_exploded[1] . " LIKE '{$search_val}' )";
								}
							}
						}
					}
				}
				$params['where'] .= ( ( false === strpos( $params['where'], 'meta_value LIKE' ) ) || ( ! empty( $where_cond ) ) ) ? ' AND ( ' : '';
				$params['where'] .= ( false === strpos( $params['where'], "meta_value LIKE '{$search_val}'") ) ? " ({$wpdb->prefix}postmeta.meta_value LIKE '{$search_val}') " : '';
				$params['where'] .= ( ! empty( $where_cond ) ) ? ' OR ' . implode( " OR ", $where_cond ) : '';
				$params['where'] .= ( ( true === strpos( $params['where'], 'meta_value LIKE') ) || ( ! empty( $where_cond ) ) ) ? ' ) ' : '';
			}
			if ( ! empty( $this->req_params['selected_ids'] ) && ( '[]' !== $this->req_params['selected_ids'] ) && empty( $this->req_params['storewide_option'] ) && ( ! empty( $this->req_params['cmd'] ) ) && ( 'get_export_csv' === $this->req_params['cmd'] ) ) {
				$selected_ids = json_decode( stripslashes( $this->req_params['selected_ids'] ) );
				$params['where'] .= ( ! empty( $selected_ids ) ) ? " AND {$wpdb->prefix}posts.ID IN (" . implode( ",", $selected_ids ) . ")" : $params['where'];
			}
			return array( 'where' => $params['where'], 'where_cond' => $where_cond, 'search_text' => $search_text );
		}

		/**
		 * Adds custom JOIN clauses to the SQL query for search.
		 *
		 * @param array $params The parameters for constructing the JOIN clause.
		 * @return string The modified JOIN clause.
		 */
		public function get_join_clause_for_search( $params = array() ) {
			if ( empty( $params ) || ( ! is_array( $params ) ) ) {
				return;
			}
			global $wpdb;
			$sort_params = array();
			if ( ! empty( $params['wp_query_obj'] ) ) {
				$sort_params = ( ! empty( $params['wp_query_obj']->query_vars['sm_sort_params'] ) ) ? $params['wp_query_obj']->query_vars['sm_sort_params'] : array();		
			} elseif ( ! empty( $params['sort_params'] ) ) {
				$sort_params = $params['sort_params']; 
			}
			// Code for sorting of the terms columns
			if ( ! empty( $sort_params ) ) {
				if ( ! empty( $sort_params['column_nm'] ) && ! empty( $sort_params['sortOrder'] ) ) {
					if ( ! empty( $sort_params['table'] ) && ( 'terms' === $sort_params['table'] ) ) {
						$params['join'] = $this->terms_table_column_sort_query( 
							array(
								'col_name'     => $sort_params['column_nm'],
								'id'           => $wpdb->prefix . 'posts.ID',
								'sort_order'   => $sort_params['sortOrder'],
								'join'         => $params['join'],
								'wp_query_obj' => ( ! empty( $params['wp_query_obj'] ) ) ? $params['wp_query_obj'] : '',
								'sort_params'  => $sort_params
							)
						);
					}
				}
			}
			// Code for handling advanced search.
			if ( ! empty( $this->req_params ) && ! empty( $this->req_params['advanced_search_query'] ) && $this->req_params['advanced_search_query'] != '[]' && ( false === strpos( $params['join'], 'sm_advanced_search_temp' ) ) ) {
				$params['join'] .= " JOIN {$wpdb->base_prefix}sm_advanced_search_temp ON ({$wpdb->base_prefix}sm_advanced_search_temp.product_id = {$wpdb->prefix}posts.id)";
			}
			// Code for handling simple search.
			if ( ! empty( $this->req_params['search_text'] ) && ( false === strpos( $params['join'], 'postmeta' ) ) ) {
				$params['join'] .= " JOIN {$wpdb->prefix}postmeta ON ({$wpdb->prefix}postmeta.post_id = {$wpdb->prefix}posts.id)";
			}
			return $params['join'];
		}

		/**
		 * Generates the GROUP BY clause for search queries.
		 *
		 * @param array $params The parameters for constructing the GROUP BY clause.
		 * @return string The modified GROUP BY clause.
		 */
		public function get_group_by_clause_for_search( $params = array() ) {
			if ( empty( $params ) || ( ! is_array( $params ) ) ) {
				return;
			}
			global $wpdb;
			return ( false === strpos( $params['group_by'], 'posts.id' ) ) ? $wpdb->prefix.'posts.id' : $params['group_by'];
		}

		/**
		 * Generates the ORDER BY clause for sorting based on the provided parameters.
		 *
		 * @param array $params. An array of parameters to determine the sorting order.
		 * @return string The ORDER BY clause for the SQL query.
		 */
		public function get_order_by_clause_for_sort( $params = array() ) {
			if ( empty( $params ) || ( ! is_array( $params ) ) ) {
				return;
			}
			global $wpdb;
			$sort_params = array();
			if (  ! empty( $params['wp_query_obj'] ) ) {
				$sort_params = ( ! empty( $params['wp_query_obj']->query_vars['sm_sort_params'] ) ) ? $params['wp_query_obj']->query_vars['sm_sort_params'] : array();		
			} elseif( ! empty( $params['sort_params'] ) ) {
				$sort_params = $params['sort_params']; 
			}
			if ( ! empty( $sort_params ) && ( ! empty( $sort_params['column_nm'] ) ) ) {
				$sort_order = ( ! empty( $sort_params['sortOrder'] ) ) ? $sort_params['sortOrder'] : 'ASC';
				if ( ! empty( $sort_params['table'] ) ) {
					if ( 'posts' === $sort_params['table'] ) {
						$params['order_by'] = $sort_params['column_nm'] .' '. $sort_order;
					} else if ( ( 'terms' === $sort_params['table'] ) && ( true === $this->terms_sort_join ) ) {
						$params['order_by'] = ' taxonomy_sort.term_name '.$sort_order ;
					}	
				}
			}
			// Condition for sorting of postmeta_cols.
			if ( ! empty( $sort_params ) && ( ! empty( $sort_params['sort_by_meta_key'] ) ) ) {
				$sort_order = ( ! empty( $sort_params['sortOrder'] ) ) ? $sort_params['sortOrder'] : 'ASC';
				$post_type = ( ! empty( $this->post_type ) ) ? $this->post_type : $this->req_params['active_module'];
				$post_type = ( ! is_array( $post_type ) ) ? array( $post_type ) : $post_type;
				$meta_value = ( ! empty( $sort_params['column_nm'] ) && ( 'meta_value_num' === $sort_params['column_nm'] ) ) ? 'pm.meta_value+0' : 'pm.meta_value';
				$post_ids = $wpdb->get_col( "SELECT DISTINCT p.ID 
											FROM {$wpdb->prefix}posts AS p
												LEFT JOIN {$wpdb->prefix}postmeta AS pm
													ON (p.ID = pm.post_id
														AND pm.meta_key = '". $sort_params['sort_by_meta_key'] ."')
											WHERE p.post_type IN ('". implode("','", $post_type) ."')
											ORDER BY ". $meta_value ." ". $sort_order );

				$option_name = 'sm_data_model_sorted_ids';
				update_option( $option_name, implode( ',', $post_ids ), 'no' );
				$limit = ( isset( $params['wp_query_obj']->query['posts_per_page'] ) && isset( $params['wp_query_obj']->query['offset'] ) && $params['wp_query_obj']->query['posts_per_page'] > 0 ) ? ( " LIMIT ". $params['wp_query_obj']->query['offset'] .", ". $params['wp_query_obj']->query['posts_per_page'] ) : '';
				$params['order_by'] = " FIND_IN_SET( ".$wpdb->prefix."posts.ID, ( SELECT option_value FROM ".$wpdb->prefix."options WHERE option_name = '".$option_name."' ) ) ";
			}

			return $params['order_by'];
		}
	}
}
