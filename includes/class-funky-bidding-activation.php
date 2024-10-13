<?php

class Funky_Bidding_Activation {
    
    public static function activate() {
        self::create_tables();
    }

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create campaigns table
        $campaigns_table = $wpdb->prefix . 'bidding_campaigns';
        $campaigns_sql = "CREATE TABLE $campaigns_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            sponsorship_image varchar(255),
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Create items table
        $items_table = $wpdb->prefix . 'bidding_items';
        $items_sql = "CREATE TABLE $items_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) NOT NULL,
            item_name varchar(255) NOT NULL,
            item_image varchar(255),
            min_bid decimal(10,2) NOT NULL,
            bid_increment decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            FOREIGN KEY (campaign_id) REFERENCES $campaigns_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Create bids table
        $bids_table = $wpdb->prefix . 'bidding_bids';
        $bids_sql = "CREATE TABLE $bids_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            item_id bigint(20) NOT NULL,
            user_email varchar(255) NOT NULL,
            bid_amount decimal(10,2) NOT NULL,
            bid_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (item_id) REFERENCES $items_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($campaigns_sql);
        dbDelta($items_sql);
        dbDelta($bids_sql);
    }
}
