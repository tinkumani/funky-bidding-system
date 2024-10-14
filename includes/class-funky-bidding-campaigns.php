<?php

class Funky_Bidding_Campaigns {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_campaigns_menu'));
        add_action('admin_post_add_campaign', array($this, 'handle_add_campaign'));
        add_action('admin_menu', array($this, 'add_active_campaigns_submenu'));
        add_action('wp_ajax_get_campaign_items', array($this, 'get_campaign_items'));
        add_action('wp_ajax_nopriv_get_campaign_items', array($this, 'get_campaign_items'));
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
            SELECT c.id, c.name, c.start_date, c.end_date, 
                   COUNT(DISTINCT i.id) as total_items,
                   SUM(CASE 
                       WHEN (b.max_bid_amount >= i.max_bid AND i.max_bid != 0) 
                            OR (c.end_date < '$current_time' AND b.max_bid_amount > 0) 
                       THEN 1 
                       ELSE 0 
                   END) as items_sold,
                   SUM(CASE 
                       WHEN (b.max_bid_amount >= i.max_bid AND i.max_bid != 0) 
                            OR (c.end_date < '$current_time' AND b.max_bid_amount > 0) 
                       THEN b.max_bid_amount 
                       ELSE 0 
                   END) as funds_collected
            FROM {$wpdb->prefix}bidding_campaigns c
            LEFT JOIN {$wpdb->prefix}bidding_items i ON c.id = i.campaign_id
            LEFT JOIN (
                SELECT item_id, MAX(bid_amount) as max_bid_amount
                FROM {$wpdb->prefix}bidding_bids
                GROUP BY item_id
            ) b ON i.id = b.item_id
            GROUP BY c.id, c.name, c.start_date, c.end_date
            ORDER BY c.end_date DESC
        ");

        echo '<div class="wrap">';
        echo '<h2>All Campaigns</h2>';
        
        if (empty($campaigns)) {
            echo '<p>No campaigns found.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Campaign Name</th>';
            echo '<th>Start Date</th>';
            echo '<th>End Date</th>';
            echo '<th>Total Items</th>';
            echo '<th>Items Sold</th>';
            echo '<th>Funds Collected</th>';
            echo '<th>Status</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($campaigns as $campaign) {
                $is_active = ($campaign->start_date <= $current_time && $campaign->end_date >= $current_time);
                $row_class = $is_active ? 'active-row' : 'inactive-row';
                $status = $is_active ? 'Active' : 'Inactive';
                
                echo "<tr class='{$row_class}'>";
                echo '<td>' . esc_html($campaign->name) . '</td>';
                echo '<td>' . esc_html($campaign->start_date) . '</td>';
                echo '<td>' . esc_html($campaign->end_date) . '</td>';
                echo '<td>' . esc_html($campaign->total_items) . '</td>';
                echo '<td><a href="#" onclick="showItemDetails(' . esc_js($campaign->id) . ')">' . esc_html($campaign->items_sold) . '</a></td>';
                echo '<td>$' . number_format($campaign->funds_collected, 2) . '</td>';
                echo '<td>' . esc_html($status) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
        
        // Add JavaScript for item details popup
        ?>
        <script type="text/javascript">
        function showItemDetails(campaignId) {
            // AJAX call to fetch item details
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_campaign_items',
                    campaign_id: campaignId,
                    nonce: '<?php echo wp_create_nonce('funky_bidding_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var items = response.data;
                        
                        // Create the modal content
                        var modalContent = '<div style="max-height: 600px; overflow-y: auto;"><h3>Campaign Items</h3>';
                        modalContent += '<table class="wp-list-table widefat fixed striped">';
                        modalContent += '<thead><tr><th>Item Name</th><th>Starting Price</th><th>Current Price</th><th>Max Price</th><th>Status</th></tr></thead>';
                        modalContent += '<tbody>';
                        
                        items.forEach(function(item) {
                            var isSold = (parseFloat(item.current_price) >= parseFloat(item.max_bid) && parseFloat(item.max_bid) !== 0) || 
                                         (new Date(item.campaign_end_date) < new Date() && parseFloat(item.current_price) > parseFloat(item.starting_price));
                            modalContent += '<tr>';
                            modalContent += '<td>' + item.name + '</td>';
                            modalContent += '<td>$' + parseFloat(item.starting_price).toFixed(2) + '</td>';
                            modalContent += '<td>$' + parseFloat(item.current_price).toFixed(2) + '</td>';
                            modalContent += '<td>$' + (parseFloat(item.max_bid) !== 0 ? parseFloat(item.max_bid).toFixed(2) : 'N/A') + '</td>';
                            modalContent += '<td>' + (isSold ? 'Sold' : 'Unsold') + '</td>';
                            modalContent += '</tr>';
                        });
                        
                        modalContent += '</tbody></table></div>';
                        
                        // Display the modal
                        jQuery('<div id="item-details-modal">')
                            .html(modalContent)
                            .dialog({
                                title: 'Campaign Items',
                                modal: true,
                                width: 600,
                                position: { my: "center", at: "center", of: window },
                                close: function() {
                                    jQuery(this).dialog('destroy').remove();
                                }
                            });
                    } else {
                        console.error('Error fetching campaign items:', response.data);
                        alert('Error fetching campaign items. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    alert('Error fetching campaign items. Please try again.');
                }
            });
        }
        </script>
        <?php
    }

    public function get_campaign_items() {
        // Ensure WordPress is fully loaded
        require_once(ABSPATH . 'wp-load.php');

        check_ajax_referer('funky_bidding_nonce', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error('Invalid campaign ID');
        }

        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT i.id, i.item_name as name, i.min_bid as starting_price, i.max_bid, c.end_date as campaign_end_date,
            COALESCE(MAX(b.bid_amount), i.min_bid) as current_price
            FROM {$wpdb->prefix}bidding_items i
            LEFT JOIN {$wpdb->prefix}bidding_bids b ON i.id = b.item_id
            JOIN {$wpdb->prefix}bidding_campaigns c ON i.campaign_id = c.id
            WHERE i.campaign_id = %d
            GROUP BY i.id",
            $campaign_id
        ));

        if ($items === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        }

        wp_send_json_success($items);
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
