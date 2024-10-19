<?php

class Funky_Bidding_Activation {
    
    public static function activate() {
        self::create_tables();
        self::add_version_option();
    }

    private static function add_foreign_key_constraints() {
        /*global $wpdb;
        
        $constraints = [
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
        ];

        foreach ($constraints as $constraint) {
            try {
                $wpdb->query($constraint);
            } catch (Exception $e) {
                error_log("Failed to add foreign key constraint. Error: " . $e->getMessage());
            }
        }*/
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
    
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bidding_campaigns (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            sponsorship_image VARCHAR(255),
            success_email_template TEXT,
            item_watchers_email_template TEXT,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;
    
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bidding_items (
            id INT(11) NOT NULL AUTO_INCREMENT,
            campaign_id INT(11) NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            item_id INT(11)  NULL,
            item_description TEXT,
            item_image VARCHAR(255),
            min_bid DECIMAL(10,2) NOT NULL,
            max_bid DECIMAL(10,2),
            bid_increment DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id)
        ) $charset_collate;
    
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bidding_bids (
            id INT(11) NOT NULL AUTO_INCREMENT,
            item_id INT(11) NOT NULL,
            user_email VARCHAR(100) NOT NULL,
            user_name VARCHAR(100) NOT NULL,
            user_phone VARCHAR(100) NOT NULL,
            bid_amount DECIMAL(10,2) NOT NULL,
            bid_time DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY item_id (item_id)
        ) $charset_collate;
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bidding_activity (
            id INT(11) NOT NULL AUTO_INCREMENT,
            item_id INT(11) NOT NULL,
            user_name VARCHAR(100) NOT NULL,
            user_phone VARCHAR(20) NOT NULL,
            bid_amount DECIMAL(10,2) NOT NULL,
            browser VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            time DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY item_id (item_id)
        ) $charset_collate;
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}admin_campaign_activity (
            id INT(11) NOT NULL AUTO_INCREMENT,
            campaign_id INT(11) NOT NULL,
            campaign_name VARCHAR(255) NOT NULL,
            description TEXT,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            sponsorship_image VARCHAR(255), 
            browser VARCHAR(255),
            ip_address VARCHAR(100),
            location VARCHAR(255),
            time DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id)
        ) $charset_collate;

        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}admin_item_activity (
            id INT(11) NOT NULL AUTO_INCREMENT,
            item_id INT(11) NOT NULL,
            campaign_id INT(11) NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            item_description TEXT,
            item_image VARCHAR(255),
            min_bid DECIMAL(10,2) NOT NULL, 
            max_bid DECIMAL(10,2),
            bid_increment DECIMAL(10,2) NOT NULL,
            browser VARCHAR(255),
            ip_address VARCHAR(100),
            location VARCHAR(255),
            time DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY item_id (item_id)
        ) $charset_collate;

        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bidding_watchers (
            id INT(11) NOT NULL AUTO_INCREMENT,
            item_id INT(11) NOT NULL,
            user_email VARCHAR(100) NOT NULL,
            time DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY item_id (item_id)
        ) $charset_collate;
        create table if not exists {$wpdb->prefix}bidding_users (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_email VARCHAR(100) NOT NULL,
            time DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_email (user_email)
        ) $charset_collate;
        create table if not exists {$wpdb->prefix}bidding_user_activity (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_email VARCHAR(100) NOT NULL,
            browser VARCHAR(255),
            ip_address VARCHAR(100),
            location VARCHAR(255),
            time DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_email (user_email)
        ) $charset_collate;
        ";
        
    
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
            $wpdb->prefix . 'bidding_campaigns',
            $wpdb->prefix . 'bidding_watchers',
            $wpdb->prefix . 'bidding_users',
            $wpdb->prefix . 'bidding_user_activity',
            $wpdb->prefix . 'bidding_activity',
            $wpdb->prefix . 'admin_campaign_activity'
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
