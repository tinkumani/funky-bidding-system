<?php

class Funky_Bidding_Items {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_items_submenu'));
        add_action('admin_post_add_item', array($this, 'handle_add_item'));
             add_action('admin_menu', array($this, 'add_items_submenu'));
        add_action('admin_post_add_item', array($this, 'handle_add_item'));
        add_action('admin_menu', array($this, 'add_view_items_submenu'));
        add_action('admin_menu', array($this, 'add_current_items_submenu'));
        add_action('admin_post_delete_items', array($this, 'handle_delete_items'));
    }

    public function add_items_submenu() {
        add_submenu_page(
            'funky_bidding_campaigns', 
            'Add Items', 
            'Add Items', 
            'manage_options', 
            'funky_bidding_add_items', 
            array($this, 'render_add_items_page')
        );
    }

    public function render_add_items_page() {
        global $wpdb;
        $campaigns = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}bidding_campaigns");

        echo '<div class="wrap">';
        echo '<h1>Add Item to Campaign</h1>';
        echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="add_item">';

        // Campaign Selection
        echo '<label for="campaign_id">Select Campaign:</label><br>';
        echo '<select name="campaign_id" id="campaign_id" required>';
        echo '<option value="">Select Campaign</option>';
        foreach ($campaigns as $campaign) {
            echo '<option value="' . esc_attr($campaign->id) . '">' . esc_html($campaign->name) . '</option>';
        }
        echo '</select><br><br>';

        // Item Name
        echo '<label for="item_name">Item Name:</label><br>';
        echo '<input type="text" name="item_name" id="item_name" required><br><br>';

        // Item Image Upload
        echo '<label for="item_image">Item Image:</label><br>';
        echo '<input type="file" name="item_image" id="item_image" accept="image/*"><br><br>';

        // Minimum Bid
        echo '<label for="min_bid">Minimum Bid:</label><br>';
        echo '<input type="number" name="min_bid" id="min_bid" step="0.01" required><br><br>';

        // Bid Increment
        echo '<label for="bid_increment">Bid Increment:</label><br>';
        echo '<input type="number" name="bid_increment" id="bid_increment" step="0.01" required><br><br>';

        echo '<input type="submit" value="Add Item" class="button button-primary">';
        echo '</form>';
        echo '</div>';
    }

    public function handle_add_item() {
        if ( isset($_POST['campaign_id'], $_POST['item_name'], $_POST['min_bid'], $_POST['bid_increment']) ) {
            global $wpdb;
            $campaign_id = intval($_POST['campaign_id']);
            $item_name = sanitize_text_field($_POST['item_name']);
            $min_bid = floatval($_POST['min_bid']);
            $bid_increment = floatval($_POST['bid_increment']);
            
            // Handle the image upload if it exists
            $item_image = '';
            if (!empty($_FILES['item_image']['name'])) {
                $uploaded = media_handle_upload('item_image', 0);
                if (!is_wp_error($uploaded)) {
                    $item_image = wp_get_attachment_url($uploaded);
                }
            }

            // Insert item into the database
            $wpdb->insert(
                "{$wpdb->prefix}bidding_items",
                array(
                    'campaign_id'   => $campaign_id,
                    'item_name'     => $item_name,
                    'item_image'    => $item_image,
                    'min_bid'       => $min_bid,
                    'bid_increment' => $bid_increment
                )
            );

            // Redirect after success
            wp_redirect(admin_url('admin.php?page=funky_bidding_add_items&success=1'));
            exit;
        }
    }
    public function display_campaign_items() {
        if (!isset($_GET['campaign_id'])) {
            echo '<p>No campaign selected.</p>';
            return;
        }

        $campaign_id = intval($_GET['campaign_id']);
        global $wpdb;

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, 
                    MAX(b.bid_amount) as current_price, 
                    COUNT(b.id) as bid_count,
                    MAX(b.bid_time) as last_bid_time,
                    b.user_email,
                    u.user_phone
             FROM {$wpdb->prefix}bidding_items i
             LEFT JOIN {$wpdb->prefix}bidding_bids b ON i.id = b.item_id
             LEFT JOIN {$wpdb->prefix}bidding_users u ON b.user_email = u.user_email
             WHERE i.campaign_id = %d
             GROUP BY i.id
             ORDER BY current_price DESC",
            $campaign_id
        ));

        if (empty($items)) {
            echo '<p>No items found for this campaign.</p>';
            return;
        }

        echo '<div class="wrap">';
        echo '<h2>Campaign Items</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Image</th>';
        echo '<th>Item Name</th>';
        echo '<th>Original Bid Price</th>';
        echo '<th>Current Price</th>';
        echo '<th>Number of Bids</th>';
        echo '<th>Highest Bidder Email</th>';
        echo '<th>Highest Bidder Phone</th>';
        echo '<th>Last Bid Time</th>';
        echo '<th>Action</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($items as $item) {
            echo '<tr>';
            echo '<td>' . (
                $item->item_image 
                ? '<img src="' . esc_url($item->item_image) . '" alt="' . esc_attr($item->item_name) . '" style="max-width: 50px; max-height: 50px;">' 
                : 'No image'
            ) . '</td>';
            echo '<td>' . esc_html($item->item_name) . '</td>';
            echo '<td>$' . number_format($item->min_bid, 2) . '</td>';
            echo '<td>$' . number_format($item->current_price ? $item->current_price : $item->min_bid, 2) . '</td>';
            echo '<td>' . intval($item->bid_count) . '</td>';
            echo '<td>' . esc_html($item->user_email ? $item->user_email : 'N/A') . '</td>';
            echo '<td>' . esc_html($item->user_phone ? $item->user_phone : 'N/A') . '</td>';
            echo '<td>' . ($item->last_bid_time ? date('Y-m-d H:i:s', strtotime($item->last_bid_time)) : 'No bids yet') . '</td>';
            echo '<td>';
            if ($item->user_email) {
                echo '<a href="mailto:' . esc_attr($item->user_email) . '?subject=Congratulations on Your Winning Bid&body=Congratulations on winning the bid for ' . esc_attr($item->item_name) . '. Here are the instructions to collect your item: [Insert Instructions Here]" class="button">Send Email</a>';
            } else {
                echo 'No winner yet';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function add_view_items_submenu() {
        add_submenu_page(
            'funky_bidding_campaigns',
            'View Campaign Items',
            'View Items',
            'manage_options',
            'funky_bidding_view_items',
            array($this, 'render_view_items_page')
        );
    }

    public function render_view_items_page() {
        echo '<div class="wrap">';
        echo '<h1>View Campaign Items</h1>';

        // Campaign selection form
        echo '<form method="GET" action="">';
        echo '<input type="hidden" name="page" value="funky_bidding_view_items">';
        echo '<select name="campaign_id">';
        echo '<option value="">Select a Campaign</option>';

        global $wpdb;
        $campaigns = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}bidding_campaigns");
        foreach ($campaigns as $campaign) {
            $selected = (isset($_GET['campaign_id']) && $_GET['campaign_id'] == $campaign->id) ? 'selected' : '';
            echo '<option value="' . esc_attr($campaign->id) . '" ' . $selected . '>' . esc_html($campaign->name) . '</option>';
        }

        echo '</select>';
        echo '<input type="submit" value="View Items" class="button">';
        echo '</form>';

        // Display items if a campaign is selected
        if (isset($_GET['campaign_id'])) {
            $this->display_campaign_items();
        }

        echo '</div>';
    }

    public function display_current_campaign_items() {
        if (!isset($_GET['campaign_id'])) {
            return;
        }

        $campaign_id = intval($_GET['campaign_id']);
        global $wpdb;

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bidding_items WHERE campaign_id = %d",
            $campaign_id
        ));

        if (empty($items)) {
            echo '<p>No items found for this campaign.</p>';
            return;
        }

        echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="delete_items">';
        echo '<input type="hidden" name="campaign_id" value="' . esc_attr($campaign_id) . '">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Select</th><th>Item Name</th><th>Minimum Bid</th><th>Bid Increment</th></tr></thead>';
        echo '<tbody>';

        foreach ($items as $item) {
            echo '<tr>';
            echo '<td><input type="checkbox" name="delete_items[]" value="' . esc_attr($item->id) . '"></td>';
            echo '<td>' . esc_html($item->item_name) . '</td>';
            echo '<td>' . esc_html($item->min_bid) . '</td>';
            echo '<td>' . esc_html($item->bid_increment) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<input type="submit" value="Delete Selected Items" class="button button-primary" onclick="return confirm(\'Are you sure you want to delete the selected items?\');">';
        echo '</form>';
    }

    public function handle_delete_items() {
        if (!isset($_POST['delete_items']) || !isset($_POST['campaign_id'])) {
            wp_redirect(admin_url('admin.php?page=funky_bidding_view_items&campaign_id=' . $_POST['campaign_id']));
            exit;
        }

        global $wpdb;
        $items_to_delete = array_map('intval', $_POST['delete_items']);
        $campaign_id = intval($_POST['campaign_id']);

        foreach ($items_to_delete as $item_id) {
            $wpdb->delete(
                $wpdb->prefix . 'bidding_items',
                array('id' => $item_id, 'campaign_id' => $campaign_id),
                array('%d', '%d')
            );
        }

        wp_redirect(admin_url('admin.php?page=funky_bidding_view_items&campaign_id=' . $campaign_id . '&deleted=1'));
        exit;
    }
    public function add_current_items_submenu() {
        add_submenu_page(
            'funky_bidding_campaigns',
            'Current Campaign Items',
            'Current Items',
            'manage_options',
            'funky_bidding_current_items',
            array($this, 'render_current_items_page')
        );
    }
    
    // Add this new method
    public function render_current_items_page() {
        echo '<div class="wrap">';
        echo '<h1>Current Campaign Items</h1>';
    
        // Campaign selection form
        echo '<form method="GET" action="">';
        echo '<input type="hidden" name="page" value="funky_bidding_current_items">';
        echo '<select name="campaign_id">';
        echo '<option value="">Select a Campaign</option>';
    
        global $wpdb;
        $campaigns = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}bidding_campaigns");
        foreach ($campaigns as $campaign) {
            $selected = (isset($_GET['campaign_id']) && $_GET['campaign_id'] == $campaign->id) ? 'selected' : '';
            echo '<option value="' . esc_attr($campaign->id) . '" ' . $selected . '>' . esc_html($campaign->name) . '</option>';
        }
    
        echo '</select>';
        echo '<input type="submit" value="View Items" class="button">';
        echo '</form>';
    
        // Display items if a campaign is selected
        if (isset($_GET['campaign_id'])) {
            $this->display_current_campaign_items();
        }
    
        echo '</div>';
    }
}
