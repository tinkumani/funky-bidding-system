<?php
date_default_timezone_set('America/Chicago');
class Funky_Bidding_Shortcodes {
    public function __construct() {
        add_shortcode('funky_bidding_campaigns', array($this, 'display_campaigns'));
        add_shortcode('funky_bidding_items', array($this, 'display_items'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('init', array($this, 'create_bidding_users_table'));
        add_action('init', array($this, 'create_bidding_watchers_table'));
        add_action('wp_ajax_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('wp_ajax_nopriv_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('wp_ajax_place_bid', array($this, 'handle_place_bid'));
        add_action('wp_ajax_nopriv_place_bid', array($this, 'handle_place_bid'));
        add_action('wp_ajax_watch_item', array($this, 'handle_watch_item'));
        add_action('wp_ajax_nopriv_watch_item', array($this, 'handle_watch_item'));
        add_action('wp_ajax_load_more_items', array($this, 'load_more_items'));
        add_action('wp_ajax_nopriv_load_more_items', array($this, 'load_more_items'));
        add_action('wp_ajax_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('wp_ajax_nopriv_refresh_campaign_data', array($this, 'refresh_campaign_data')); 
        add_action('wp_ajax_check_new_activity', array($this, 'check_new_activity'));
        add_action('wp_ajax_nopriv_check_new_activity', array($this, 'check_new_activity'));
        add_action('wp_footer', array($this, 'add_activity_check_script'));
    }

    public function add_activity_check_script() {
        wp_enqueue_script('funky-bidding-activity-check', plugin_dir_url(__FILE__) . '../assets/js/funky-bidding-activity-check.js', array('jquery'), '1.0', true);
        wp_localize_script('funky-bidding-activity-check', 'funkyBiddingActivityCheck', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('funkyBiddingLiveFeed')
        ));


        ?>
        <script>
        jQuery(document).ready(function($) {
            function getCSTTimeMinusFiveMinutes() {
                var now = new Date();
                var cstOffset = -6; // CST is UTC-6
                var utc = now.getTime() + (now.getTimezoneOffset() * 60000);
                var cstTime = new Date(utc + (3600000 * cstOffset));
                cstTime.setMinutes(cstTime.getMinutes() - 5);
                
                var year = cstTime.getFullYear();
                var month = ('0' + (cstTime.getMonth() + 1)).slice(-2);
                var day = ('0' + cstTime.getDate()).slice(-2);
                var hours = ('0' + cstTime.getHours()).slice(-2);
                var minutes = ('0' + cstTime.getMinutes()).slice(-2);
                var seconds = ('0' + cstTime.getSeconds()).slice(-2);
                
                return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
            }
            var lastCheckTime = getCSTTimeMinusFiveMinutes();
            function checkNewActivity() {
                $.ajax({
                    url: funkyBiddingActivityCheck.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'check_new_activity',
                        last_check_time: lastCheckTime,
                        nonce: funkyBiddingActivityCheck.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            lastCheckTime = data.current_time;

                            // Handle new bids
                            if (data.new_bids.length > 0) {
                                data.new_bids.forEach(function(bid) {
                                    showNotification('Someone just bid: $' + bid.bid_amount + ' on ' + bid.item_name);
                                });
                            }

                            // Handle sold items
                            if (data.sold_items.length > 0) {
                                data.sold_items.forEach(function(item) {
                                    showNotification("Someone just won: " + item.item_name + ' for $' + item.sold_price);
                                });
                            }
                        }
                    }
                });
            }

            function showNotification(message) {
                $('<div class="funky-bidding-notification">')
                    .text(message)
                    .css({
                        'position': 'fixed',
                        'bottom': '20px',
                        'right': '-300px',
                        'width': '250px',
                        'background-color': '#fff',
                        'border': '1px solid #ccc',
                        'padding': '10px',
                        'box-shadow': '0 0 10px rgba(0,0,0,0.1)',
                        'z-index': '9999'
                    })
                    .appendTo('body')
                    .animate({ right: '20px' }, 500)
                    .delay(3000)
                    .fadeOut(10000, function() {
                        $(this).remove();
                    });
            }

            setInterval(checkNewActivity, 1000);
        });
        </script>
        <?php
    }

    public function enqueue_assets() {
        wp_enqueue_style('funky-bidding-styles', plugin_dir_url(__FILE__) . '../assets/css/funky-bidding-styles.css');
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('funky-bidding-scripts', plugin_dir_url(__FILE__) . '../assets/js/funky-bidding-scripts.js', array('jquery'), '1.0', true);
        wp_localize_script('funky-bidding-scripts', 'funkyBidding', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('funky_bidding_nonce')
        ));
    }

    public function create_bidding_users_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bidding_users';
        
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                user_email VARCHAR(100) NOT NULL PRIMARY KEY,
                user_phone VARCHAR(20)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
public function check_new_activity() {
    // Verify the nonce
    if (!check_ajax_referer('funkyBiddingLiveFeed', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    global $wpdb;
    $last_check_time = isset($_POST['last_check_time']) ? sanitize_text_field($_POST['last_check_time']) : '';

    // Get new bids
    $new_bids = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, i.item_name 
        FROM {$wpdb->prefix}bidding_bids b
        JOIN {$wpdb->prefix}bidding_items i ON b.item_id = i.id
        WHERE b.bid_time > %s
        ORDER BY b.bid_time DESC",
        $last_check_time
    ));

    // Get newly sold items
    $sold_items = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, b.bid_amount as sold_price, b.user_name as buyer
        FROM {$wpdb->prefix}bidding_items i
        JOIN {$wpdb->prefix}bidding_bids b ON i.id = b.item_id
        JOIN {$wpdb->prefix}bidding_campaigns c ON i.campaign_id = c.id
        WHERE (
            (i.max_bid > 0 AND b.bid_amount >= i.max_bid) 
            OR (c.end_date <= %s)
        )
        AND b.bid_time > %s
        AND b.bid_amount = (
            SELECT MAX(bid_amount) 
            FROM {$wpdb->prefix}bidding_bids 
            WHERE item_id = i.id
        )
        ORDER BY b.bid_time DESC",
        current_time('mysql'),
        $last_check_time
    ));

    $response = array(
        'new_bids' => $new_bids,
        'sold_items' => $sold_items,
        'current_time' => current_time('mysql')
    );

    wp_send_json_success($response);
}

    public function create_bidding_watchers_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bidding_watchers';
        
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                item_id mediumint(9) NOT NULL,
                user_email varchar(100) NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public function display_campaigns($atts) {
        global $wpdb;
        $campaigns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bidding_campaigns");

        if (empty($campaigns)) {
            return '<p class="funky-bidding-message">No campaigns are currently running.</p>';
        }

        wp_enqueue_style('funky-bidding-campaign-styles', plugin_dir_url(__FILE__) . '../assets/css/funky-bidding-campaign-styles.css');

        ob_start();
        echo '<div class="funky-bidding-campaigns">';
        foreach ($campaigns as $campaign) {
            $end_time = strtotime($campaign->end_date);
            $is_active = time() < $end_time;
            $class = $is_active ? 'active' : 'inactive';
            
            $total_money = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(b.bid_amount) 
                FROM {$wpdb->prefix}bidding_bids b
                JOIN {$wpdb->prefix}bidding_items i ON b.item_id = i.id
                WHERE i.campaign_id = %d",
                $campaign->id
            ));
            $items_sold = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT b.item_id) 
                FROM {$wpdb->prefix}bidding_bids b
                JOIN {$wpdb->prefix}bidding_items i ON b.item_id = i.id
                WHERE i.campaign_id = %d",
                $campaign->id
            ));
            $total_items = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bidding_items WHERE campaign_id = %d",
                $campaign->id
            ));
            $items_to_be_sold = $total_items - $items_sold;
            
            echo '<div class="funky-bidding-campaign ' . $class . '" data-campaign-id="' . esc_attr($campaign->id) . '" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1c1e21;">';
            echo '<div class="funky-bidding-campaign-header">';
            echo '<h2 class="funky-bidding-campaign-title" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1c1e21;">' . esc_html($campaign->name) . '</h2>';
            if ($is_active) {
                echo '<div class="funky-bidding-timer" data-end-time="' . esc_attr($end_time) . '" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1c1e21;"></div>';
            } else {
                echo '<div class="funky-bidding-cancelled" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1c1e21;"><span class="dashicons dashicons-no-alt"></span> Campaign Ended</div>';
            }
            echo '</div>';
            
            echo '<div class="funky-bidding-campaign-content">';
            echo '<div class="funky-bidding-campaign-image-container">';
            if ($campaign->sponsorship_image) {
                echo '<img class="funky-bidding-campaign-image" src="' . esc_url($campaign->sponsorship_image) . '" alt="Sponsorship Image" style="width: 100%; height: auto;">';
            }
            echo '</div>';
            
            echo '<div class="funky-bidding-campaign-details">';
            echo '<p class="funky-bidding-campaign-description">' . esc_html($campaign->description) . '</p>';
            echo '<div class="funky-bidding-campaign-stats">';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">Total Raised:</span> <span class="funky-bidding-stat-value">$' . number_format($total_money, 2) . '</span></div>';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">Items Sold:</span> <span class="funky-bidding-stat-value">' . $items_sold . '</span></div>';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">Items Remaining:</span> <span class="funky-bidding-stat-value">' . $items_to_be_sold . '</span></div>';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">Time Remaining:</span> <span class="funky-bidding-stat-value"><span class="hourglass">⏳</span> <span class="time-remaining" data-end-time="' . esc_attr($end_time) . '"></span></span></div>';      
            echo '<div class="funky-bidding-stat">';
            echo '<span class="funky-bidding-stat-label">Start Date:</span>';
            echo '<div class="funky-bidding-stat-value date-display">';
            echo '<div class="date-day">' . date('d', strtotime($campaign->start_date)) . '</div>';
            echo '<div class="date-month-year">';
            echo '<div>' . date('M Y', strtotime($campaign->start_date)) . '</div>';
            echo '<div>' . date('h:i A', strtotime($campaign->start_date)) . ' CDT</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">End Date:</span> ';
            echo '<div class="funky-bidding-stat-value date-display">';
            echo '<div class="date-day">' . date('d', strtotime($campaign->end_date)) . '</div>';
            echo '<div class="date-month-year">';
            echo '<div>' . date('M Y', strtotime($campaign->end_date)) . '</div>';
            echo '<div>' . date('h:i A', strtotime($campaign->end_date)) . ' CDT</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            if ($is_active) {
                $view_items_url = esc_url(get_permalink() . '?campaign_id=' . $campaign->id);
                echo '<a href="' . $view_items_url . '" class="view-items-button" id="view-items-' . esc_attr($campaign->id) . '">View Items</a><br>&nbsp;';
                
                static $first_active_campaign = true;
                if ($first_active_campaign) {
                    echo '<script>
                    jQuery(document).ready(function($) {
                        $("#view-items-' . esc_js($campaign->id) . '").trigger("click");
                    });
                    </script>';
                    $first_active_campaign = false;
                }
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<script>
        jQuery(document).ready(function($) {
            function updateTimeRemaining() {
                $(".time-remaining").each(function() {
                    var endTime = $(this).data("end-time");
                    var now = Math.floor(Date.now() / 1000);
                    var timeLeft = endTime - now;
                    
                    if (timeLeft > 0) {
                        var days = Math.floor(timeLeft / 86400);
                        var hours = Math.floor((timeLeft % 86400) / 3600);
                        var minutes = Math.floor((timeLeft % 3600) / 60);
                        var seconds = timeLeft % 60;
                        $(this).text(days + "d " + hours + "h " + minutes + "m " + seconds + "s");
                    } else {
                        $(this).text("Ended");
                    }
                });
                
                $(".hourglass").toggleClass("flip");
            }
            
            setInterval(updateTimeRemaining, 1000);
            
            $("<style>")
                .prop("type", "text/css")
                .html("\
                .hourglass { display: inline-block; transition: transform 0.5s; }\
                .hourglass.flip { transform: rotate(180deg); }\
                ")
                .appendTo("head");
        });
        </script>';

        return ob_get_clean();
    }

    public function display_items($atts) {
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        if (!$campaign_id) {
            return '<p>No campaign selected.</p>';
        }

        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bidding_campaigns WHERE id = %d", $campaign_id));
        
        if (!$campaign) {
            return '<p>Invalid campaign selected.</p>';
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');

        wp_localize_script('jquery', 'funkyBidding', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('funky_bidding_nonce')
        ));
        ob_start();
        echo '<div id="funky-bidding-items-container" class="funky-bidding-items-container" data-campaign-id="' . esc_attr($campaign_id) . '">';
        echo '<div id="funky-bidding-items" class="funky-bidding-items"></div>';
        echo '<div id="funky-bidding-loader" class="funky-bidding-loader">Loading...</div>';
        echo '</div>';
        echo '<div id="bid-result-dialog" title="Bid Result" style="display:none;"></div>';

        echo '<script>
        jQuery(document).ready(function($) {
            var page = 1;
            var loading = false;
            var $container = $("#funky-bidding-items-container");
            var $itemsContainer = $("#funky-bidding-items");
            var $loader = $("#funky-bidding-loader");
            var hasMoreItems = true;

            // Load user info from cookie
            var userInfo = JSON.parse(getCookie("funky_bidding_user_info") || "{}");

            function getCookie(name) {
                var value = "; " + document.cookie;
                var parts = value.split("; " + name + "=");
                if (parts.length == 2) return parts.pop().split(";").shift();
            }

            function loadItems() {
                if (loading || !hasMoreItems) return;
                loading = true;
                
                $loader.show();

                $.ajax({
                    url: funkyBidding.ajaxurl,
                    type: "POST",
                    data: {
                        action: "load_more_items",
                        campaign_id: $container.data("campaign-id"),
                        page: page,
                        nonce: funkyBidding.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.items.length > 0) {
                            $itemsContainer.append(response.data.items.join(""));
                            page++;
                            initializeNewItems();
                        } else {
                            hasMoreItems = false;
                            $loader.text("No more items to load.");
                        }
                        loading = false;
                        $loader.hide();
                    },
                    error: function() {
                        loading = false;
                        $loader.hide();
                        $loader.text("Error loading items. Please try again.");
                    }
                });
            }

            function checkScroll() {
                var scrollPosition = $(window).scrollTop() + $(window).height();
                var containerBottom = $container.offset().top + $container.height();
                
                if (scrollPosition > containerBottom - 200 && !loading && hasMoreItems) {
                    loadItems();
                }
            }

            $(window).on("scroll", checkScroll);
            $(window).on("resize", checkScroll);

            loadItems();

            function initializeNewItems() {
                $(".watch-item").off("click").on("click", function(e) {
                    e.preventDefault();
                    var itemId = $(this).data("item-id");
                    var userEmail = prompt("Enter your email to watch this item:");
                    if (userEmail) {
                        $.ajax({
                            url: funkyBidding.ajaxurl,
                            type: "POST",
                            data: {
                                action: "watch_item",
                                item_id: itemId,
                                user_email: userEmail,
                                nonce: funkyBidding.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert("You are now watching this item!");
                                } else {
                                    alert("Error: " + response.data);
                                }
                            }
                        });
                    }
                });

                updateTimers();
            }

            function updateTimers() {
                $(".timer").each(function() {
                    var $timer = $(this);
                    var endTime = $timer.data("end-time");
                    var now = Math.floor(Date.now() / 1000);
                    var timeLeft = endTime - now;

                    if (timeLeft > 0) {
                        var days = Math.floor(timeLeft / 86400);
                        var hours = Math.floor((timeLeft % 86400) / 3600);
                        var minutes = Math.floor((timeLeft % 3600) / 60);
                        var seconds = timeLeft % 60;

                        $timer.text(days + "d " + hours + "h " + minutes + "m " + seconds + "s");
                    } else {
                        $timer.text("Bidding ended");
                        var $item = $timer.closest(".funky-bidding-item");
                        $item.addClass("sold");
                        $item.find("form").remove();
                        $item.prepend("<div class=\'sold-banner\'>SOLD</div>");
                    }
                });
            }

            setInterval(updateTimers, 1000);

            $(document).on("click", ".funky-bidding-button", function(e) {
                e.preventDefault();
                var form = $(this).closest("form");
                var userName = form.find("input[name=\'user_name\']").val();
                var userEmail = form.find("input[name=\'user_email\']").val();
                
                if (!userName || !userEmail) {
                    alert("Please enter your name and email before placing a bid.");
                    return;
                }
                
                var formData = form.serialize();
                var $button = $(this);
                var $message = $("<div>").text("Placing Bid...").insertAfter($button);
                $button.prop("disabled", true);

                // Save user info to cookie and update all forms
                var newUserInfo = {
                    user_name: userName,
                    user_phone: form.find("input[name=\'user_phone\']").val(),
                    user_email: userEmail
                };
                document.cookie = "funky_bidding_user_info=" + JSON.stringify(newUserInfo) + "; path=/; max-age=31536000; SameSite=Strict";
                
                // Loop through all forms and update user info fields
                $(".funky-bidding-form").each(function() {
                    $(this).find("input[name=\'user_name\']").val(newUserInfo.user_name);
                    $(this).find("input[name=\'user_phone\']").val(newUserInfo.user_phone);
                    $(this).find("input[name=\'user_email\']").val(newUserInfo.user_email);
                });

                $.ajax({
                    url: funkyBidding.ajaxurl,
                    type: "POST",
                    data: formData + "&action=place_bid&nonce=" + funkyBidding.nonce,
                    success: function(response) {
                        $message.remove();
                        $button.prop("disabled", false);
                        if (response.success) {
                            var $dialog = $("#bid-result-dialog");
                            $dialog.html("<p>" + response.message + "</p>");
                            $dialog.dialog({
                                modal: true,
                                buttons: {
                                    Ok: function() {
                                        $(this).dialog("close");
                                    }
                                }
                            });

                            // Update the item"s values
                            var $form = form;
                            if (response.item.max_bid != null && parseFloat(response.item.max_bid) > 0) {
                                $form.find("#max_bid").val("Max Price: $" + parseFloat(response.item.max_bid).toFixed(2));
                            }
                            if (response.item.min_bid != null) {
                                $form.find("#min_bid").val("Minimum Bid: $" + parseFloat(response.item.min_bid).toFixed(2));
                            }
                            if (response.item.current_bid != null) {
                                $form.find("#current_price").val("Current Bid: $" + parseFloat(response.item.current_bid).toFixed(2));
                            }
                            if (response.item.bid_increment != null) {
                                $form.find("#next_increment").val("Bid Increment: $" + parseFloat(response.item.bid_increment).toFixed(2));
                            }
                            $form.find("#highest-bidder").val(response.item.highest_bidder);

                            // Update the suggested bid
                            if (response.item.current_bid != null && response.item.bid_increment != null) {
                                var newSuggestedBid = parseFloat(response.item.current_bid) + parseFloat(response.item.bid_increment);
                                if (response.item.max_bid != null && parseFloat(response.item.max_bid) > 0) {
                                    newSuggestedBid = Math.min(newSuggestedBid, parseFloat(response.item.max_bid));
                                }
                                $form.find("#suggested_bid_label").val("Suggested Bid: $" + newSuggestedBid.toFixed(2));
                                $form.find("#bid_amount").attr("min", newSuggestedBid).val(newSuggestedBid);
                            }
                            if (response.item.is_won) {
                                $("<div>").text("Congratulations! You have won the item...").insertAfter($button);
                                $button.prop("disabled", true);
                                $button.text("You Won!");
                            }else if (response.item.is_sold != 0) {
                                $("<div>").text("Sorry! This item is already sold...").insertAfter($button);
                                $button.prop("disabled", true);
                                $button.text("Sold");
                            }
                        } else {
                            var errorMessage = response.message || "An unknown error occurred";
                            alert("Error: " + errorMessage);
                        }
                    },
                    error: function() {
                        $message.remove();
                        $button.prop("disabled", false);
                        alert("An error occurred. Please try again.");
                    }
                });
            });
        });
        </script>';

        return ob_get_clean();
    }

    public function load_more_items() {
        check_ajax_referer('funky_bidding_nonce', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $items_per_page = 10; // You can adjust this number as needed
        $offset = ($page - 1) * $items_per_page;

        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, c.end_date, 
            CASE 
                WHEN i.max_bid > 0 AND EXISTS (SELECT 1 FROM {$wpdb->prefix}bidding_bids b WHERE b.item_id = i.id AND b.bid_amount >= i.max_bid) THEN 1
                WHEN c.end_date < NOW() THEN 1
                ELSE 0
            END AS is_sold
            FROM {$wpdb->prefix}bidding_items i 
            JOIN {$wpdb->prefix}bidding_campaigns c ON i.campaign_id = c.id 
            WHERE i.campaign_id = %d
            ORDER BY is_sold ASC, i.item_id ASC
            LIMIT %d OFFSET %d",
            $campaign_id,
            $items_per_page,
            $offset
        ));

        $html_items = array();
        $active_items = array();
        $sold_items = array();

        foreach ($items as $item) {
            $highest_bid = $wpdb->get_var($wpdb->prepare("SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item->id));
            $bid_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item->id));

            $end_time = strtotime($item->end_date);
            $current_time = current_time('timestamp');
            $time_left = $end_time - $current_time;

            $is_sold = ($highest_bid >= $item->max_bid && $item->max_bid != 0) || ($time_left <= 0);

            ob_start();
            $item_id = esc_attr($item->id);
            $max_bid_ref = "max_bid";
            $min_bid_ref = "min_bid";
            $current_price_ref = "current_price";
            $next_increment_ref = "next_increment";
            $bid_count_ref = "bid_count";

            echo '<div id="item-' . esc_attr($item_id) . '" class="funky-bidding-item' . ($is_sold ? ' sold' : '') . '" style="background-color: #212121;">';
            echo '<p class="funky-bidding-item-title" style="color: white; font-size: 18px; font-weight: bold;">';
            if (!empty($item->item_id)) {
                echo '<span class="funky-bidding-item-id" style="background-color: #212121;">' . esc_html($item->item_id) . '.</span>';
            }
            echo esc_html($item->item_name);
            echo '</p>';
            echo '<div class="funky-bidding-item-image-container" style="background-color: #212121; position: relative;">';
            if (!$is_sold) {
                echo '<button class="watch-item" data-item-id="' . esc_attr($item_id) . '" style="position: absolute; top: 10px; right: 10px; background: none; border: none; cursor: pointer; z-index: 10;" title="Watch Item">';
                echo '<div class="heart">';
                echo '<div class="heart-piece-0"></div>';
                echo '<div class="heart-piece-1"></div>';
                echo '<div class="heart-piece-2"></div>';
                echo '<div class="heart-piece-3"></div>';
                echo '<div class="heart-piece-4"></div>';
                echo '<div class="heart-piece-5"></div>';
                echo '<div class="heart-piece-6"></div>';
                echo '<div class="heart-piece-7"></div>';
                echo '<div class="heart-piece-8"></div>';
                echo '</div>';
                echo '</button>';
                echo '<style>
                    .heart {
                        width: 24px;
                        height: 24px;
                        background-color: red;
                        position: relative;
                        transform: rotate(45deg);
                        animation: heartBeat 1.2s infinite;
                    }
                    .heart-piece-0 {
                        width: 24px;
                        height: 24px;
                        background-color: red;
                        position: absolute;
                        top: 0;
                        left: 0;
                    }
                    .heart-piece-1, .heart-piece-2 {
                        width: 24px;
                        height: 24px;
                        background-color: red;
                        border-radius: 50%;
                        position: absolute;
                    }
                    .heart-piece-1 {
                        top: -12px;
                        left: 0;
                    }
                    .heart-piece-2 {
                        top: 0;
                        left: -12px;
                    }
                    .heart-piece-3, .heart-piece-4, .heart-piece-5, .heart-piece-6, .heart-piece-7, .heart-piece-8 {
                        width: 8px;
                        height: 8px;
                        background-color: rgba(255,0,0,0.5);
                        position: absolute;
                        border-radius: 50%;
                    }
                    .heart-piece-3 { top: -4px; left: 4px; }
                    .heart-piece-4 { top: 4px; left: -4px; }
                    .heart-piece-5 { top: 4px; right: -4px; }
                    .heart-piece-6 { bottom: -4px; left: 4px; }
                    .heart-piece-7 { bottom: 4px; left: -4px; }
                    .heart-piece-8 { bottom: 4px; right: -4px; }
                    @keyframes heartBeat {
                        0% { transform: rotate(45deg) scale(1); }
                        14% { transform: rotate(45deg) scale(1.3); }
                        28% { transform: rotate(45deg) scale(1); }
                        42% { transform: rotate(45deg) scale(1.3); }
                        70% { transform: rotate(45deg) scale(1); }
                    }
                </style>';
            }
            if ($item->item_image) {
                echo '<img class="funky-bidding-item-image" src="' . esc_url($item->item_image) . '" alt="Item Image">';
            }
            if ($is_sold) {
                echo '<div class="funky-bidding-sold-banner" style="background-color: #212121;">SOLD</div>';
            }
            echo '</div>';
            echo '<div class="funky-bidding-item-details" style="background-color: #212121;">';
            echo '<p class="funky-bidding-item-description" style="background-color: #212121; margin: 0px;">' . esc_html(wp_trim_words($item->item_description, 20)) . '</p>';
            echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '" class="funky-bidding-form" style="background-color: #212121;">';
            echo '<div class="funky-bidding-item-stats" style="background-color: #212121; display: grid; grid-template-columns: 1fr 1fr; gap: 1px;">';
            echo '<input type="text" value="Current Bid: $' . number_format($highest_bid, 2) . '" id="' . esc_attr($current_price_ref) . '" class="funky-bidding-highest-bid" disabled style="background-color: #212121;color: white;border-color: black;border-width: 0px;width: 125px;padding-left: 0px;padding-top: 0px;padding-right: 0px;padding-bottom: 0px;margin-bottom: 0px;height: 20px;">';
            echo '<input type="text" value="Minimum Bid: $' . esc_html($item->min_bid) . '" id="' . esc_attr($min_bid_ref) . '" class="funky-bidding-highest-bid" disabled style="background-color: #212121;color: white;border-color: black;border-width: 0px;width: 125px;padding-left: 0px;padding-top: 0px;padding-right: 0px;padding-bottom: 0px;margin-bottom: 0px;height: 20px;">';
            if ($item->max_bid > 0) {
                echo '<input type="text" value="Max Price: $' . esc_html($item->max_bid) . '" id="' . esc_attr($max_bid_ref) . '" class="funky-bidding-highest-bid" disabled style="background-color: #212121;color: white;border-color: black;border-width: 0px;width: 125px;padding-left: 0px;padding-top: 0px;padding-right: 0px;padding-bottom: 0px;margin-bottom: 0px;height: 20px;">';
            }
            echo '<input type="text" value="Bid Increment: $' . esc_html($item->bid_increment) . '" id="' . esc_attr($next_increment_ref) . '" disabled style="background-color: #212121;color: white;border-color: black;border-width: 0px;width: 125px;padding-left: 0px;padding-top: 0px;padding-right: 0px;padding-bottom: 0px;margin-bottom: 0px;height: 20px;">';
            echo '<input type="text" value="Total Bids: ' . $bid_count . '" id="' . esc_attr($bid_count_ref) . '" disabled style="background-color: #212121;color: white;border-color: black;border-width: 0px;width: 125px;padding-left: 0px;padding-top: 0px;padding-right: 0px;padding-bottom: 0px;margin-bottom: 0px;height: 20px;">';
          
            if (!$is_sold) {
                echo '<p class="time-left" style="margin: 2px;">Time Left: <span class="timer" data-end-time="' . esc_attr($end_time) . '"></span></p>';
                echo '</div>';
                echo '<input type="hidden" name="action" value="place_bid">';
                echo '<input type="hidden" name="item_id" value="' . esc_attr($item->id) . '">';
                echo '<input type="hidden" name="redirect_anchor" value="item-' . esc_attr($item->id) . '">';
                
                $user_info = isset($_COOKIE['funky_bidding_user_info']) ? json_decode(stripslashes($_COOKIE['funky_bidding_user_info']), true) : null;
                echo '<div class="user-info-fields">';
                echo '<div class="funky-bidding-form" style="background-color: #212121;">';
                echo '<input type="text" style="margin-bottom:0px;height:22px;" name="user_name" id="user_name" placeholder="Name" value="' . esc_attr($user_info['user_name'] ?? '') . '" required>';
                echo '</div>';
                
                echo '<div class="funky-bidding-form" style="background-color: #212121;">';
                echo '<input type="tel" name="user_phone" id="user_phone" placeholder="Phone (Example: 1234567890)" value="' . esc_attr($user_info['user_phone'] ?? '') . '" pattern="[0-9]{10}" required>';
                echo '</div>';
                echo '<div class="funky-bidding-form" style="background-color: #212121;">';
                echo '<input type="email" style="height:16px;" name="user_email" id="user_email" placeholder="Email" value="' . esc_attr($user_info['user_email'] ?? '') . '" required>';
                echo '</div>';
                echo '</div>';
                echo '<div class="funky-bidding-form" style="background-color: #212121;">';
                echo '<label for="bid_amount" style="color:white;margin-bottom:0px;">Your Bid:</label>';
                echo '<div class="bid-input-container" style="background-color: #212121;">';
                $suggested_bid = $highest_bid > 0 ? $highest_bid + $item->bid_increment : $item->min_bid;
                if ($item->max_bid > 0 && $suggested_bid > $item->max_bid) {
                    $suggested_bid = $item->max_bid;
                }
                echo '<input type="number" style="color:black;margin-bottom:0px;height:20px;" name="bid_amount" id="bid_amount" step="5" min="' . esc_attr($suggested_bid) . '" value="' . esc_attr($suggested_bid) . '" required>';
                echo '</div>';
                echo '<input type="text" name="suggested_bid_label" id="suggested_bid_label" value="Suggested Bid: $' . esc_attr($suggested_bid) . '" disabled style="border: none;background: none;font-weight: 400;width: 100%;font-family: -apple-system, system-ui, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size: 12px;height: 25px;padding-top: 0px;padding-bottom: 0px;padding-left: 0px;padding-right: 0px;margin-bottom: 0px;color: white;font-size: 12px;">';
                echo '<button type="button" class="funky-bidding-button ' . $form_class . '">Place Bid</button>';
                echo '<div id="bid-success-message" style="display:none;">Congratulations! Your bid has been placed successfully.</div>';
                echo '</div>';
                echo '</form>';
                
            } else {
                echo '</form>';
                echo '<p class="sold-price">Sold for: $' . number_format($highest_bid, 2) . '</p>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
            
            if ($is_sold) {
                $sold_items[] = ob_get_clean();
            } else {
                $active_items[] = ob_get_clean();
            }
        }
        
        $html_items = array_merge($active_items, $sold_items);

        wp_send_json_success(['items' => $html_items]);
    }

    public function handle_place_bid() {
        // Log the start of the function and all POST data
        error_log('handle_place_bid function started');
        error_log('POST data: ' . print_r($_POST, true));

        $response = array('success' => false, 'message' => '');

        if (isset($_POST['item_id'], $_POST['user_name'], $_POST['user_phone'], $_POST['bid_amount'])) {
            global $wpdb;
            $item_id = intval($_POST['item_id']);
            $user_name = sanitize_text_field($_POST['user_name']);
            $user_phone = sanitize_text_field($_POST['user_phone']);
            $user_email = sanitize_email($_POST['user_email']);
            $bid_amount = floatval($_POST['bid_amount']);
            $is_sold = $wpdb->get_var($wpdb->prepare(
                "SELECT CASE 
                    WHEN max_bid > 0 AND EXISTS (SELECT 1 FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d AND bid_amount >= max_bid) THEN 1
                    WHEN (SELECT end_date FROM {$wpdb->prefix}bidding_campaigns WHERE id = campaign_id) < NOW() THEN 1
                    ELSE 0
                END AS is_sold
                FROM {$wpdb->prefix}bidding_items 
                WHERE id = %d",
                $item_id,
                $item_id
            ));
            // Check if the item is already sold
            if ($is_sold) {
                $response['success'] = false;
                $response['message'] = 'This item is already sold and not available for bidding.';
                wp_send_json($response);
                return;
            }

            // Log the input values
            error_log("Input values: item_id=$item_id, user_name=$user_name, user_phone=$user_phone,user_email=$user_email, bid_amount=$bid_amount");

            $item = $wpdb->get_row($wpdb->prepare("SELECT id, item_name, min_bid, bid_increment, max_bid FROM {$wpdb->prefix}bidding_items WHERE id = %d", $item_id));
            
            // Log the item details
            error_log("Item details: " . print_r($item, true));

            if (!$item) {
                $response['message'] = 'Item not found.';
                wp_send_json($response);
                return;
            }

            $highest_bid = $wpdb->get_var($wpdb->prepare("SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item_id));
            $min_next_bid = ($highest_bid) ? $highest_bid + $item->bid_increment : $item->min_bid;
            if ($item->max_bid > 0) {
                $min_next_bid = min($min_next_bid, $item->max_bid);
            }

            // Log the bid validation details
            error_log("Highest bid: $highest_bid, Minimum next bid: $min_next_bid");

            if ($bid_amount >= $min_next_bid && ($item->max_bid == 0 || $bid_amount <= $item->max_bid)) {
                $result = $wpdb->insert(
                    "{$wpdb->prefix}bidding_bids",
                    array(
                        'item_id' => $item_id,
                        'user_name' => $user_name,
                        'user_phone' => $user_phone,
                        'user_email' => $user_email,
                        'bid_amount' => $bid_amount,
                        'bid_time' => current_time('mysql')
                    )
                );

                // Log the database insertion result
                error_log("Database insertion result: " . ($result !== false ? "Success" : "Failure"));

                if ($result !== false) {
                    $this->log_bidding_activity($item_id, $user_name, $user_phone, $bid_amount);

                    $is_won = $bid_amount >= $item->max_bid && $item->max_bid > 0;
                    
                   

                    if ($is_won) {
                        $response['success'] = true;
                        $response['message'] = "Congratulations! You've won the auction for {$item->item_name}!";
                    } else {
                        $response['success'] = true;
                        $response['message'] = "Your bid of $" . number_format($bid_amount, 2) . " for {$item->item_name} has been placed successfully.";
                    }

                    // Add item details to the response
                    $response['item'] = array(
                        'id' => $item->id,
                        'name' => $item->item_name,
                        'min_bid' => $item->min_bid,
                        'bid_increment' => $item->bid_increment,
                        'max_bid' => $item->max_bid,
                        'current_bid' => $bid_amount,
                        'highest_bidder' => $user_name,
                        'is_won' => $is_won,
                        'is_sold' => $is_sold
                    );

                    // Notify watchers
                    $watchers = $wpdb->get_results($wpdb->prepare(
                        "SELECT user_email FROM {$wpdb->prefix}bidding_watchers WHERE item_id = %d",
                        $item_id
                    ));

                    if ($watchers) {
                        $subject = "New bid placed on {$item->item_name}";
                        $message = "A new bid of $" . number_format($bid_amount, 2) . " has been placed on {$item->item_name}.\n\n";
                        $message .= "Current highest bid: $" . number_format($bid_amount, 2) . "\n";
                        $message .= "Visit the auction page to place your bid!";

                        foreach ($watchers as $watcher) {
                            wp_mail($watcher->user_email, $subject, $message);
                        }

                        error_log("Notified " . count($watchers) . " watchers about new bid on item ID: $item_id");
                    }

                    // Notify watchers (code omitted for brevity)
                } else {
                    $response['message'] = "Error placing bid. Please contact the treasurer (treasurer@stthomastexas.org) for assistance.";
                }
            } else {
                if($bid_amount >= $min_next_bid){
                                        $response['message'] = "Invalid bid amount. Minimum bid is $" . number_format($min_next_bid, 2) . ".";
                                   }
                                    if($item->max_bid != 0 && $bid_amount >= $item->max_bid){
                        $response['message'] = "Invalid bid amount. Maximum bid is $" . number_format($item->max_bid, 2) . ".";
                }
                // Log why the bid was not accepted
                error_log("Bid not accepted. Bid amount: $bid_amount, Min next bid: $min_next_bid, Max bid: {$item->max_bid}");
            }
        } else {
            $response['message'] = "Missing required information. Please fill in all fields.";
            // Log which POST variables are missing
            $missing = array();
            foreach (['item_id', 'user_name', 'user_phone','user_email', 'bid_amount'] as $key) {
                if (!isset($_POST[$key])) {
                    $missing[] = $key;
                }
            }
            error_log("Missing POST variables: " . implode(", ", $missing));
        }

        wp_send_json($response);
    }

    private function log_bidding_activity($item_id, $user_name, $user_phone, $bid_amount) {
        global $wpdb;
        $browser = $_SERVER['HTTP_USER_AGENT'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $time = current_time('mysql');

        $wpdb->insert(
            "{$wpdb->prefix}bidding_activity",
            array(
                'item_id' => $item_id,
                'user_name' => $user_name,
                'user_phone' => $user_phone,
                'user_email' => $user_email,
                'bid_amount' => $bid_amount,
                'browser' => $browser,
                'ip_address' => $ip_address,
                'time' => $time
            )
        );
    }

    public function handle_watch_item() {
        check_ajax_referer('funky_bidding_nonce', 'nonce');

        if (isset($_POST['item_id']) && isset($_POST['user_email'])) {
            global $wpdb;
            $item_id = intval($_POST['item_id']);
            $user_email = sanitize_email($_POST['user_email']);

            $result = $wpdb->insert(
                "{$wpdb->prefix}bidding_watchers",
                array(
                    'item_id' => $item_id,
                    'user_email' => $user_email
                ),
                array('%d', '%s')
            );
    
            if ($result) {
                wp_send_json_success('You are now watching this item.');
            } else {
                wp_send_json_error('Failed to add watcher.');
            }
        } else {
            wp_send_json_error('Invalid request.');
        }
    }
    
    public function refresh_campaign_data() {
        check_ajax_referer('funky_bidding_nonce', 'nonce');
        
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bidding_campaigns WHERE id = %d", $campaign_id));
        
        if ($campaign) {
            $end_time = strtotime($campaign->end_date);
            $is_active = time() < $end_time;
            
            $response = array(
                'is_active' => $is_active,
                'end_time' => $end_time
            );
             
            wp_send_json_success($response);
        } else {
            wp_send_json_error('Campaign not found');
        }
    }
}

// Add this to your main plugin file or a separate file that's included in your plugin
function funky_bidding_enqueue_styles() {
    wp_enqueue_style('funky-bidding-styles', plugin_dir_url(__FILE__) . 'assets/css/funky-bidding-styles.css');
}
add_action('wp_enqueue_scripts', 'funky_bidding_enqueue_styles');

// CSS for the bidding system
function funky_bidding_inline_styles() {
    echo '<style>
    .funky-bidding-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        max-width: 100%;
        margin: 0 auto;
        padding: 10px;
    }
    .funky-bidding-sidebar {
        width: 100%;
        padding-bottom: 20px;
    }
    .funky-bidding-main-content {
        width: 100%;
    }
    .funky-bidding-campaign-list {
        list-style-type: none;
        padding: 0;
    }
    .funky-bidding-campaign {
        background-color: #f5f5f5;
        border-radius: 5px;
    }
    .funky-bidding-items-container {
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        overflow-x: hidden;
    }
    .funky-bidding-items {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        justify-content: left;
        border-radius: 8px;
        padding: 1px;
        background-color: #212121;
    }
    .funky-bidding-item {
        background-color: #ffffff;
        border-radius: 0px;
        padding: 0px;
        transition: transform 0.2s ease-in-out;
        position: relative;
        width: 100%;
        border: 0px solid #ebebeb;
        box-shadow: none;
    }
    .funky-bidding-item:hover {
        transform: translateY(-5px);
    }
    .funky-bidding-item h5 {
        margin-top: 0;
        font-size: 16px;
        color: #333;
    }
    .item-image-container {
        position: relative;
        margin-bottom: 1px;
    }
    .item-image-container img {
        width: 100%;
        height: auto;
        border-radius: 6px;
        object-fit: cover;
        aspect-ratio: 16 / 9;
    }
    .watch-item {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: rgba(0, 0, 0, 0.7);
        color: white;
        border: none;
        padding: 5px 8px;
        font-size: 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease-in-out;
    }
    .watch-item:hover {
        background-color: rgba(0, 0, 0, 0.9);
    }
    .funky-bidding-item.sold {
        opacity: 0.7;
    }
    .sold-banner {
        background-color: #ff4136;
        color: white;
        padding: 5px 10px;
        position: absolute;
        top: 10px;
        left: 10px;
        font-size: 14px;
        font-weight: bold;
        z-index: 1;
        transform: rotate(-15deg);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .funky-bidding-button {
        background-color: #de9425;
        color: white;
        border: none;
        padding: 8px 50px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease-in-out;
    }
    .funky-bidding-button:hover {
        background-color: #0063b1;
    }
    .funky-bidding-button:disabled {
        background-color: #cccccc;
        color: #666666;
        cursor: not-allowed;
    }
    .funky-bidding-button:disabled:hover {
        background-color: #cccccc;
    }
    .funky-bidding-loader {
        text-align: center;
        padding: 20px;
        font-style: italic;
        color: #666;
    }
    @media (max-width: 767px) {
        .funky-bidding-items {
            grid-template-columns: 1fr;
        }
    }
    </style>';
}
add_action('wp_head', 'funky_bidding_inline_styles');