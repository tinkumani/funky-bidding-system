<?php

class Funky_Bidding_Activation {
    
    public static function activate() {
        self::create_tables();
        self::add_version_option();
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
    
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bidding_campaigns (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            sponsorship_image VARCHAR(255),
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;
    
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bidding_items (
            id INT(11) NOT NULL AUTO_INCREMENT,
            campaign_id INT(11) NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            item_image VARCHAR(255),
            min_bid DECIMAL(10,2) NOT NULL,
            bid_increment DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id)
        ) $charset_collate;
    
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bidding_bids (
            id INT(11) NOT NULL AUTO_INCREMENT,
            item_id INT(11) NOT NULL,
            user_email VARCHAR(100) NOT NULL,
            bid_amount DECIMAL(10,2) NOT NULL,
            bid_time DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY item_id (item_id)
        ) $charset_collate;";
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        self::log_table_creation($result);
        self::verify_tables_exist();
        self::add_foreign_key_constraints();
    }

    private static function log_table_creation($result) {
        error_log('dbDelta result: ' . print_r($result, true));
    }

    private static function verify_tables_exist() {
        global $wpdb;
        $tables = array('bidding_campaigns', 'bidding_items', 'bidding_bids');
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log("Table $table_name was not created successfully.");
            } else {
                error_log("Table $table_name was created successfully.");
            }
        }
    }

    private static function add_foreign_key_constraints() {
        global $wpdb;
        $constraint_queries = array(
            "ALTER TABLE {$wpdb->prefix}bidding_items 
             ADD CONSTRAINT fk_campaign_id 
             FOREIGN KEY (campaign_id) 
             REFERENCES {$wpdb->prefix}bidding_campaigns(id) 
             ON DELETE CASCADE",
            "ALTER TABLE {$wpdb->prefix}bidding_bids 
             ADD CONSTRAINT fk_item_id 
             FOREIGN KEY (item_id) 
             REFERENCES {$wpdb->prefix}bidding_items(id) 
             ON DELETE CASCADE"
        );

        foreach ($constraint_queries as $query) {
            $result = $wpdb->query($query);
            if ($result === false) {
                error_log("Failed to add foreign key constraint. Error: " . $wpdb->last_error);
            } else {
                error_log("Foreign key constraint added successfully.");
            }
        }
    }

    private static function add_version_option() {
        add_option('funky_bidding_version', '1.0.0');
    }

    public static function deactivate() {
        // Perform any cleanup tasks if needed
    }

    public static function uninstall() {
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'bidding_bids',
            $wpdb->prefix . 'bidding_items',
            $wpdb->prefix . 'bidding_campaigns'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('funky_bidding_version');
    }

    public static function force_create_tables() {
        self::create_tables();
        echo '<div class="notice notice-success"><p>Tables creation attempted. Please check the error logs for results.</p></div>';
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'funky_bidding_campaigns',
            'Force Create Tables',
            'Force Create Tables',
            'manage_options',
            'funky_bidding_force_create_tables',
            array(__CLASS__, 'force_create_tables')
        );
    }
}

// Hook for adding admin menus
add_action('admin_menu', array('Funky_Bidding_Activation', 'add_admin_menu'));