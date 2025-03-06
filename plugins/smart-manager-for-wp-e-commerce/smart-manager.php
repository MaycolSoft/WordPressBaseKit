<?php
/*
* Plugin Name: WooCommerce Advanced Bulk Edit Products, Orders, Coupons, Any WordPress Post Type - Smart Manager
* Plugin URI: https://www.storeapps.org/product/smart-manager/
* Description: <strong>Lite Version Installed</strong>. The #1 tool for WooCommerce inventory management, stock management, bulk edit, export, delete, duplicate...from one place using an Excel-like sheet editor.
* Version: 8.57.0
* Author: StoreApps
* Author URI: https://www.storeapps.org/
* Text Domain: smart-manager-for-wp-e-commerce
* Domain Path: /languages/
* Requires at least: 5.0
* Tested up to: 6.7.2
* Requires PHP: 5.6+
* WC requires at least: 3.0.0
* WC tested up to: 9.6.2
* Copyright (c) 2010 - 2025 StoreApps. All rights reserved.
* License: GNU General Public License v2.0
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SM_PLUGIN_FILE' ) ) {
	define( 'SM_PLUGIN_FILE', __FILE__ );
}

if ( ! class_exists( 'Smart_Manager' ) && file_exists( ( dirname( __FILE__ ) ) . '/class-smart-manager.php' ) ) {
	include_once 'class-smart-manager.php';
}
