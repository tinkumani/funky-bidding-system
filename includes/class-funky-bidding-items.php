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
        add_action('admin_menu', array($this, 'add_edit_campaign_submenu'));
        add_action('admin_post_edit_campaign', array($this, 'handle_edit_campaign'));
    }

    public function add_items_submenu() {
        add_submenu_page(
            'funky_bidding_campaigns', 
            'Add Items', 
            'Add Items', 
            'manage_options', 
            'funky_bidding_add_items', 
            array($this, 'render_add_items_page'),
            'funky_bidding_edit_campaign',
            array($this, 'render_edit_campaign_page')
        );
    }
    public function add_edit_campaign_submenu(): void {
        add_submenu_page(
            'funky_bidding_campaigns',
            'Edit Campaign',
            'Edit Campaign',
            'manage_options',
            'funky_bidding_edit_campaign',
            array($this, 'render_edit_campaign_page')
        );
    }

    public function render_edit_campaign_page() {
        global $wpdb;
        
        echo '<div class="wrap">';
        echo '<h1>Edit Campaign</h1>';
    
        // Campaign selection form
        echo '<form method="GET" action="">';
        echo '<input type="hidden" name="page" value="funky_bidding_edit_campaign">';
        echo '<select name="campaign_id">';
        echo '<option value="">Select a Campaign</option>';
    
        $campaigns = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}bidding_campaigns");
        foreach ($campaigns as $campaign) {
            $selected = (isset($_GET['campaign_id']) && $_GET['campaign_id'] == $campaign->id) ? 'selected' : '';
            echo '<option value="' . esc_attr($campaign->id) . '" ' . $selected . '>' . esc_html($campaign->name) . '</option>';
        }
    
        echo '</select>';
        echo '<input type="submit" value="Edit Campaign" class="button">';
        echo '</form>';
    
        // If a campaign is selected, show the edit form
        if (isset($_GET['campaign_id'])) {
            $campaign_id = intval($_GET['campaign_id']);
            $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bidding_campaigns WHERE id = %d", $campaign_id));
    
            if (!$campaign) {
                echo '<p>Campaign not found.</p>';
                return;
            }
    
            echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
            echo '<input type="hidden" name="action" value="edit_campaign">';
            echo '<input type="hidden" name="campaign_id" value="' . esc_attr($campaign_id) . '">';
    
            // Campaign Name
            echo '<label for="campaign_name">Campaign Name:</label><br>';
            echo '<input type="text" name="campaign_name" id="campaign_name" value="' . esc_attr($campaign->name) . '" required><br><br>';
    
            // Campaign Description
            echo '<label for="campaign_description">Campaign Description:</label><br>';
            echo '<textarea name="campaign_description" id="campaign_description" rows="4" cols="50">' . esc_textarea($campaign->description) . '</textarea><br><br>';
    
            // Start Date
            echo '<label for="start_date">Start Date:</label><br>';
            echo '<input type="datetime-local" name="start_date" id="start_date" value="' . esc_attr(date('Y-m-d\TH:i', strtotime($campaign->start_date))) . '" required><br><br>';
    
            // End Date
            echo '<label for="end_date">End Date:</label><br>';
            echo '<input type="datetime-local" name="end_date" id="end_date" value="' . esc_attr(date('Y-m-d\TH:i', strtotime($campaign->end_date))) . '" required><br><br>';
            // Item Watchers Email Template
            echo '<label for="item_watchers_email_template">Item Watchers Email Template:</label><br>';
            echo '<textarea name="item_watchers_email_template" id="item_watchers_email_template" rows="4" cols="50">' . esc_textarea($campaign->item_watchers_email_template) . '</textarea><br><br>';
            // Success Email Template
            echo '<label for="success_email_template">Success Email Template:</label><br>';
            echo '<textarea name="success_email_template" id="success_email_template" rows="4" cols="50">' . esc_textarea($campaign->success_email_template) . '</textarea><br><br>';
            // Sponsorship Image
            echo '<label for="sponsorship_image">Sponsorship Image:</label><br>';
            if ($campaign->sponsorship_image) {
                echo '<img src="' . esc_url($campaign->sponsorship_image) . '" alt="Current Sponsorship Image" style="max-width: 200px;"><br>';
            }
            echo '<input type="file" name="sponsorship_image" id="sponsorship_image" accept="image/*"><br><br>';
    
            echo '<input type="submit" value="Update Campaign" class="button button-primary">';
            echo '</form>';
        }
    
        echo '</div>';
    }

    public function handle_edit_campaign() {
        if (!isset($_POST['campaign_id']) || !current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $campaign_id = intval($_POST['campaign_id']);
        $campaign_name = sanitize_text_field($_POST['campaign_name']);
        $campaign_description = sanitize_textarea_field($_POST['campaign_description']);
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $item_watchers_email_template = sanitize_textarea_field($_POST['item_watchers_email_template']);
        $success_email_template = sanitize_textarea_field($_POST['success_email_template']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'bidding_campaigns';

        $data = array(
            'name' => $campaign_name,
            'description' => $campaign_description,
            'item_watchers_email_template' => $item_watchers_email_template,
            'success_email_template' => $success_email_template,
            'start_date' => $start_date,
            'end_date' => $end_date,
        );

        $format = array('%s', '%s', '%s', '%s');

        if (!empty($_FILES['sponsorship_image']['tmp_name'])) {
            $upload = wp_handle_upload($_FILES['sponsorship_image'], array('test_form' => false));
            if (isset($upload['url'])) {
                $data['sponsorship_image'] = $upload['url'];
                $format[] = '%s';
            }
        }

        $wpdb->update($table_name, $data, array('id' => $campaign_id), $format, array('%d'));

        wp_redirect(admin_url('admin.php?page=funky_bidding_campaigns&updated=true'));
        exit;
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
        // Item Id
        echo '<label for="item_id">Item Id (optional):</label><br>';
        echo '<input type="text" name="item_id" id="item_id"><br><br>';

        // Item Name
        echo '<label for="item_name">Item Name:</label><br>';
        echo '<input type="text" name="item_name" id="item_name" required><br><br>';

        // Item Description
        echo '<label for="item_description">Item Description:</label><br>';
        echo '<textarea name="item_description" id="item_description" rows="4" cols="50" required></textarea><br><br>';

        // Item Image Upload
        echo '<label for="item_image">Item Image:</label><br>';
        echo '<input type="file" name="item_image" id="item_image" accept="image/*"><br><br>';

        // Minimum Bid
        echo '<label for="min_bid">Minimum Bid:</label><br>';
        echo '<input type="number" name="min_bid" id="min_bid" step="0.01" required><br><br>';
        //Max Bid
        echo '<label for="max_bid">Maximum Bid (optional):</label><br>';
        echo '<input type="number" name="max_bid" id="max_bid" step="0.01"><br><br>';
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
            $item_id = intval($_POST['item_id']);
            $item_description = wp_kses_post($_POST['item_description']); // Allow HTML including links
            $item_name = sanitize_text_field($_POST['item_name']);
            $min_bid = floatval($_POST['min_bid']);
            $max_bid = floatval($_POST['max_bid']);
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
                    'item_id'       => $item_id,
                    'item_name'     => $item_name,
                    'item_description' => $item_description,
                    'item_image'    => $item_image,
                    'min_bid'       => $min_bid,
                    'max_bid'       => $max_bid,
                    'bid_increment' => $bid_increment
                )
            );

            $new_item_id = $wpdb->insert_id;

            // Log item activity
            $browser = $_SERVER['HTTP_USER_AGENT'];
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $time = current_time('mysql');
            
            // Get location (you may want to use a geolocation service here)
            $location = 'Unknown';

            $wpdb->insert(
                "{$wpdb->prefix}admin_item_activity",
                array(
                    'item_id' => $new_item_id,
                    'campaign_id' => $campaign_id,
                    'item_name' => $item_name,
                    'item_description' => $item_description,
                    'item_image' => $item_image,
                    'min_bid' => $min_bid,
                    'max_bid' => $max_bid,
                    'bid_increment' => $bid_increment,
                    'browser' => $browser,
                    'ip_address' => $ip_address,
                    'location' => $location,
                    'time' => $time
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
                    MAX(b.bid_time) as last_bid_time,
                    b.user_email,
                    b.user_phone,
                    b.user_name,
                    CASE 
                        WHEN i.max_bid > 0 AND MAX(b.bid_amount) >= i.max_bid THEN 1 
                        ELSE 0 
                    END as is_winner
             FROM {$wpdb->prefix}bidding_items i
             INNER JOIN {$wpdb->prefix}bidding_bids b ON i.id = b.item_id AND b.bid_amount = (SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = i.id)
             WHERE i.campaign_id = %d
             GROUP BY i.id
             HAVING MAX(b.bid_amount) IS NOT NULL
             ORDER BY i.item_id ASC",
            $campaign_id
        ));

        if (empty($items)) {
            echo '<p>No items found for this campaign.</p>';
            return;
        }

        echo '<div class="wrap">';
        echo '<h2>Campaign Items</h2>';
        echo '<table class="wp-list-table widefat fixed striped" id="campaign-items-table">';
        echo '<thead><tr>';
        echo '<th onclick="sortTable(0)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;"># <span class="sort-arrow" onclick="toggleSort(0)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(1)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Image <span class="sort-arrow" onclick="toggleSort(1)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(2)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Item Name <span class="sort-arrow" onclick="toggleSort(2)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(3, true)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Item Id <span class="sort-arrow" onclick="toggleSort(3)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(4)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Original Bid Price <span class="sort-arrow" onclick="toggleSort(4)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(5)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Maximum Bid Price <span class="sort-arrow" onclick="toggleSort(5)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(6)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Current Price <span class="sort-arrow" onclick="toggleSort(6)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(7)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Number of Bids <span class="sort-arrow" onclick="toggleSort(7)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(8)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Highest Bidder Email <span class="sort-arrow" onclick="toggleSort(8)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(9)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Highest Bidder Phone <span class="sort-arrow" onclick="toggleSort(9)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(10)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Username <span class="sort-arrow" onclick="toggleSort(10)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(11)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Won <span class="sort-arrow" onclick="toggleSort(11)">&#9650;&#9660;</span></th>';
        echo '<th onclick="sortTable(12)" style="cursor: pointer; background-color: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccc;">Last Bid Time <span class="sort-arrow" onclick="toggleSort(12)">&#9650;&#9660;</span></th>';
        echo '<th>Action</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        echo '<script>
            var sortDirections = Array(13).fill("asc");
            function toggleSort(columnIndex) {
                sortDirections[columnIndex] = sortDirections[columnIndex] === "asc" ? "desc" : "asc";
                sortTable(columnIndex, false);
            }
            function sortTable(columnIndex, isNumeric = false) {
                var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
                table = document.getElementById("campaign-items-table");
                switching = true;
                dir = sortDirections[columnIndex]; 
                while (switching) {
                    switching = false;
                    rows = table.rows;
                    for (i = 1; i < (rows.length - 1); i++) {
                        shouldSwitch = false;
                        x = rows[i].getElementsByTagName("TD")[columnIndex];
                        y = rows[i + 1].getElementsByTagName("TD")[columnIndex];
                        var xValue = isNumeric ? parseFloat(x.innerHTML) : x.innerHTML.toLowerCase();
                        var yValue = isNumeric ? parseFloat(y.innerHTML) : y.innerHTML.toLowerCase();
                        if (dir == "asc") {
                            if (xValue > yValue) {
                                shouldSwitch = true;
                                break;
                            }
                        } else if (dir == "desc") {
                            if (xValue < yValue) {
                                shouldSwitch = true;
                                break;
                            }
                        }
                    }
                    if (shouldSwitch) {
                        rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                        switching = true;
                        switchcount++; 
                    } else {
                        if (switchcount == 0 && dir == "asc") {
                            dir = "desc";
                            switching = true;
                        }
                    }
                }
            }
        </script>';

        $sequence_number = 1;
        foreach ($items as $item) {
            echo '<tr>';
            echo '<td>' . $sequence_number++ . '</td>'; 
            echo '<td>' . (
                $item->item_image 
                ? '<img src="' . esc_url($item->item_image) . '" alt="' . esc_attr($item->item_name) . '" style="max-width: 50px; max-height: 50px;">' 
                : 'No image'
            ) . '</td>';
            echo '<td>' . esc_html($item->item_name) . '</td>';
            echo '<td>' . esc_html($item->item_id) . '</td>';
            echo '<td>$' . number_format($item->min_bid, 2) . '</td>';
            echo '<td>$' . number_format($item->max_bid, 2) . '</td>';
            echo '<td>$' . number_format($item->current_price ? $item->current_price : $item->min_bid, 2) . '</td>';
            echo '<td>' . intval($item->bid_count) . '</td>';
            echo '<td>' . esc_html($item->user_email ? $item->user_email : 'N/A') . '</td>';
            echo '<td>' . esc_html($item->user_phone ? $item->user_phone : 'N/A') . '</td>';
            echo '<td>' . esc_html($item->user_name ? $item->user_name : 'N/A') . '</td>';
            echo '<td>' . esc_html($item->is_winner ? $item->is_winner : 'N/A') . '</td>';
            echo '<td>' . ($item->last_bid_time ? date('Y-m-d H:i:s', strtotime($item->last_bid_time)) : 'No bids yet') . '</td>';
            echo '<td>';
            if ($item->user_name) {
                echo '<a href="#" class="winner-info" data-name="' . esc_attr($item->user_name) . '" data-phone="' . esc_attr($item->user_phone) . '" data-email="' . esc_attr($item->user_email) . '">' . esc_html($item->user_name) . '</a>';
                echo '<script>
                    jQuery(document).ready(function($) {
                        $(".winner-info").on("click", function(e) {
                            e.preventDefault();
                            var name = $(this).data("name");
                            var phone = $(this).data("phone");
                            var email = $(this).data("email");
                            $("<div>")
                                .html("Winner: " + name + "<br>Phone: " + phone + "<br>Email: " + email)
                                .dialog({
                                    title: "Winner Information",
                                    modal: true,
                                    buttons: {
                                        "Close": function() {
                                            $(this).dialog("close");
                                        }
                                    }
                                });
                        });
                    });
                </script>';
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
