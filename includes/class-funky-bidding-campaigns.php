<?php

class Funky_Bidding_Campaigns {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_campaigns_menu'));
        add_action('admin_post_add_campaign', array($this, 'handle_add_campaign'));
        add_action('admin_menu', array($this, 'add_active_campaigns_submenu'));
    }

    // Add admin menu for campaigns
    public function add_campaigns_menu() {
        add_menu_page(
            'Bidding Campaigns',
            'Bidding Campaigns',
            'manage_options',
            'funky_bidding_campaigns',
            array($this, 'render_campaigns_page'),
            'dashicons-hammer'
        );

        add_submenu_page(
            'funky_bidding_campaigns',
            'Add Campaign',
            'Add Campaign',
            'manage_options',
            'funky_bidding_add_campaign',
            array($this, 'render_add_campaign_page')
        );
    }

    // Display existing campaigns
    public function render_campaigns_page() {
        global $wpdb;
        $campaigns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bidding_campaigns");

        echo '<div class="wrap">';
        echo '<h1>Bidding Campaigns</h1>';
        echo '<table class="widefat fixed">';
        echo '<thead><tr><th>ID</th><th>Name</th><th>Start Date</th><th>End Date</th></tr></thead><tbody>';
        foreach ($campaigns as $campaign) {
            echo "<tr><td>{$campaign->id}</td><td>{$campaign->name}</td><td>{$campaign->start_date}</td><td>{$campaign->end_date}</td></tr>";
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    // Display add campaign form
    public function render_add_campaign_page() {
        echo '<div class="wrap">';
        echo '<h1>Add New Campaign</h1>';
        echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="add_campaign">';

        // Campaign Name
        echo '<label for="campaign_name">Campaign Name:</label><br>';
        echo '<input type="text" name="campaign_name" id="campaign_name" required><br><br>';

        // Description
        echo '<label for="description">Description:</label><br>';
        echo '<textarea name="description" id="description" required></textarea><br><br>';

        // Start Date
        echo '<label for="start_date">Start Date:</label><br>';
        echo '<input type="datetime-local" name="start_date" id="start_date" required><br><br>';

        // End Date
        echo '<label for="end_date">End Date:</label><br>';
        echo '<input type="datetime-local" name="end_date" id="end_date" required><br><br>';

        // Sponsorship Image Upload
        echo '<label for="sponsorship_image">Sponsorship Image (optional):</label><br>';
        echo '<input type="file" name="sponsorship_image" id="sponsorship_image" accept="image/*"><br><br>';

        echo '<input type="submit" value="Add Campaign" class="button button-primary">';
        echo '</form>';
        echo '</div>';
    }

    // Handle campaign submission
    public function handle_add_campaign() {
        if (isset($_POST['campaign_name'], $_POST['description'], $_POST['start_date'], $_POST['end_date'])) {
            global $wpdb;

            $campaign_name = sanitize_text_field($_POST['campaign_name']);
            $description = sanitize_textarea_field($_POST['description']);
            $start_date = sanitize_text_field($_POST['start_date']);
            $end_date = sanitize_text_field($_POST['end_date']);
            $sponsorship_image = '';

            // Handle image upload
            if (!empty($_FILES['sponsorship_image']['name'])) {
                $uploaded = media_handle_upload('sponsorship_image', 0);
                if (!is_wp_error($uploaded)) {
                    $sponsorship_image = wp_get_attachment_url($uploaded);
                }
            }

            // Insert campaign into the database
            $wpdb->insert(
                "{$wpdb->prefix}bidding_campaigns",
                array(
                    'name' => $campaign_name,
                    'description' => $description,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'sponsorship_image' => $sponsorship_image
                )
            );

            wp_redirect(admin_url('admin.php?page=funky_bidding_campaigns'));
            exit;
        }
    }

    // Display active campaigns with max bid price and number of bids
    public function display_active_campaigns() {
        global $wpdb;
        $current_time = current_time('mysql');

        $campaigns = $wpdb->get_results("
            SELECT c.id, c.name, c.end_date, 
                   MAX(b.bid_amount) as max_bid, 
                   COUNT(DISTINCT b.id) as bid_count
            FROM {$wpdb->prefix}bidding_campaigns c
            LEFT JOIN {$wpdb->prefix}bidding_items i ON c.id = i.campaign_id
            LEFT JOIN {$wpdb->prefix}bidding_bids b ON i.id = b.item_id
            WHERE c.start_date <= '$current_time' AND c.end_date >= '$current_time'
            GROUP BY c.id
            ORDER BY c.end_date ASC
        ");

        echo '<div class="wrap">';
        echo '<h2>Active Campaigns</h2>';
        
        if (empty($campaigns)) {
            echo '<p>No active campaigns at the moment.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Campaign Name</th>';
            echo '<th>End Date</th>';
            echo '<th>Max Bid</th>';
            echo '<th>Number of Bids</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($campaigns as $campaign) {
                echo '<tr>';
                echo '<td>' . esc_html($campaign->name) . '</td>';
                echo '<td>' . esc_html($campaign->end_date) . '</td>';
                echo '<td>' . ($campaign->max_bid ? '$' . number_format($campaign->max_bid, 2) : 'No bids yet') . '</td>';
                echo '<td>' . esc_html($campaign->bid_count) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
    public function add_active_campaigns_submenu() {
        add_submenu_page(
            'funky_bidding_campaigns',
            'Active Campaigns',
            'Active Campaigns',
            'manage_options',
            'funky_bidding_active_campaigns',
            array($this, 'display_active_campaigns')
        );
    }
}
