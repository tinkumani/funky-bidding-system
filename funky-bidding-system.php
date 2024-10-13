<?php
/**
 * Plugin Name: Funky Bidding System
 * Description: A full-featured bidding system with campaigns, items, and dynamic bidding.
 * Version: 1.0.0
 * Author: [Your Name]
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include necessary files
require_once plugin_dir_path( __FILE__ ) . 'includes/class-funky-bidding-activation.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-funky-bidding-campaigns.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-funky-bidding-items.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-funky-bidding-shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-funky-bidding-bids.php';

// Activation hook
register_activation_hook( __FILE__, array( 'Funky_Bidding_Activation', 'activate' ) );

// Initialize main plugin classes
add_action( 'plugins_loaded', function() {
    new Funky_Bidding_Campaigns();
    new Funky_Bidding_Items();
    new Funky_Bidding_Shortcodes();
    new Funky_Bidding_Bids();
});
