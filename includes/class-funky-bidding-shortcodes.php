<?php
class Funky_Bidding_Shortcodes {
    public function __construct() {
        add_shortcode('funky_bidding_campaigns', array($this, 'display_campaigns'));
        add_shortcode('funky_bidding_items', array($this, 'display_items'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('init', array($this, 'create_bidding_users_table'));
        add_action('init', array($this, 'create_bidding_watchers_table'));
        add_action('wp_ajax_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('wp_ajax_nopriv_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('admin_post_place_bid', array($this, 'handle_place_bid'));
        add_action('admin_post_nopriv_place_bid', array($this, 'handle_place_bid'));
        add_action('wp_ajax_watch_item', array($this, 'handle_watch_item'));
        add_action('wp_ajax_nopriv_watch_item', array($this, 'handle_watch_item'));
        add_action('wp_ajax_load_more_items', array($this, 'load_more_items'));
        add_action('wp_ajax_nopriv_load_more_items', array($this, 'load_more_items'));
        add_action('wp_ajax_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('wp_ajax_nopriv_refresh_campaign_data', array($this, 'refresh_campaign_data')); 
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
                user_phone VARCHAR(20) NOT NULL
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
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
            echo '<div>' . date('h:i A T', strtotime($campaign->end_date)) . '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            if ($is_active) {
                $view_items_url = esc_url(get_permalink() . '?campaign_id=' . $campaign->id);
                echo '<a href="' . $view_items_url . '" class="funky-bidding-button" id="view-items-' . esc_attr($campaign->id) . '">View Items</a><br>&nbsp;';
                
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

        echo '<script>
        jQuery(document).ready(function($) {
            var page = 1;
            var loading = false;
            var $container = $("#funky-bidding-items-container");
            var $itemsContainer = $("#funky-bidding-items");
            var $loader = $("#funky-bidding-loader");
            var hasMoreItems = true;

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
                if ($container.height() + $container.scrollTop() >= $itemsContainer.height() - 200) {
                    loadItems();
                }
            }

            $container.on("scroll", checkScroll);
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
        });
        </script>';

        return ob_get_clean();
    }

    public function load_more_items() {
        check_ajax_referer('funky_bidding_nonce', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, c.end_date FROM {$wpdb->prefix}bidding_items i 
            JOIN {$wpdb->prefix}bidding_campaigns c ON i.campaign_id = c.id 
            WHERE i.campaign_id = %d",
            $campaign_id
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
            echo '<div id="item-' . esc_attr($item->id) . '" class="funky-bidding-item' . ($is_sold ? ' sold' : '') . '">';
            echo '<h5 style="text-align: left; margin: 2px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">';
            if (!empty($item->item_id)) {
                echo ' <span class="item-id">' . esc_html($item->item_id) . '.</span>';
            }
            echo '&nbsp' . esc_html($item->item_name);
            echo '</h5>';
            echo '<div class="item-image-container" style="float: center; padding: 5px; box-sizing: border-box;">';
            if ($item->item_image) {
                echo '<img src="' . esc_url($item->item_image) . '" alt="Item Image">';
            }
            if ($is_sold) {
                echo '<div class="sold-banner">SOLD</div>';
            } elseif (!$is_sold) {
                echo '<button class="watch-item" data-item-id="' . esc_attr($item->id) . '">Watch Item</button>';
            }
            echo '</div>';
            echo '<div class="item-details" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.4; background-color: #ccc;">';
            echo '<p class="item-description">' . esc_html(wp_trim_words($item->item_description, 20)) . '</p>';
            echo '<div class="item-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2px;">';
            echo '<p style="margin: 2px;">Current Bid: $<span class="highest-bid">' . number_format($highest_bid, 2) . '</span></p>';
            echo '<p style="margin: 2px;">Minimum Bid: $' . esc_html($item->min_bid) . '</p>';
            if ($item->max_bid > 0) {
                echo '<p>Max Price: $' . esc_html($item->max_bid) . '</p>';
            }
            echo '<p style="margin: 2px;">Bid Increment: $' . esc_html($item->bid_increment) . '</p>';
            echo '<p style="margin: 2px;">Total Bids: ' . $bid_count . '</p>';
          
            if (!$is_sold) {
                echo '<p class="time-left" style="margin: 2px;">Time Left: <span class="timer" data-end-time="' . esc_attr($end_time) . '"></span></p>';
                echo '</div>';
                echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '" class="bidding-form">';
                echo '<input type="hidden" name="action" value="place_bid">';
                echo '<input type="hidden" name="item_id" value="' . esc_attr($item->id) . '">';
                echo '<input type="hidden" name="redirect_anchor" value="item-' . esc_attr($item->id) . '">';
                
                $user_info = isset($_COOKIE['funky_bidding_user_info']) ? json_decode(stripslashes($_COOKIE['funky_bidding_user_info']), true) : null;
                
                if ($user_info) {
                    echo '<div class="stored-user-info" style="background-color: #ccc;">';
                    echo '<p>Temporary Login: </p>';
                    echo '<p>' . esc_html($user_info['user_name'] ? 'UserName: ' . $user_info['user_name'] : 'Phone: ' . $user_info['user_phone']) . '</p>';
                    echo '<button type="button" class="clear-user-info">Change User Info</button>';
                    echo '<p class="user-info-disclaimer">(Your information is not shared with other users.)</p>';
                    echo '</div>';
                    echo '<div class="user-info-fields" style="display:none;">';
                    echo '<input type="hidden" name="user_name" value="' . esc_attr($user_info['user_name']) . '">';
                    echo '<input type="hidden" name="user_phone" value="' . esc_attr($user_info['user_phone']) . '">';
                } else {
                    echo '<div class="user-info-fields">';
                }
                
                echo '<div class="funky-bidding-form">';
                echo '<input type="text" name="user_name" id="user_name" placeholder="Name" value="' . esc_attr($user_info['user_name'] ?? '') . '" required>';
                echo '</div>';
                
                echo '<div class="funky-bidding-form">';
                echo '<input type="tel" name="user_phone" id="user_phone" placeholder="Phone (Example: 1234567890)" value="' . esc_attr($user_info['user_phone'] ?? '') . '" pattern="[0-9]{10}" required>';
                echo '</div>';
                echo '</div>';
                echo '<div class="funky-bidding-form">';
                echo '<label for="bid_amount">Your Bid:</label>';
                echo '<div class="bid-input-container">';
                $suggested_bid = $highest_bid > 0 ? $highest_bid + $item->bid_increment : $item->min_bid;
                echo '<input type="number" name="bid_amount" id="bid_amount" step="5" min="' . esc_attr($suggested_bid) . '" value="' . esc_attr($suggested_bid) . '" required>';
                echo '</div>';
                echo '<p id="bid_suggestion">Suggested bid: $<span id="suggested_bid">' . number_format($highest_bid > 0 ? $highest_bid + $item->bid_increment : $item->min_bid, 2) . '</span></p>';
                echo '<br>';
                echo '&nbsp;';
                echo '<input type="submit" value="Place Bid" class="funky-bidding-button">';
                echo '</div>';
                echo '</form>';
                echo '<script>
                    jQuery(document).ready(function($) {
                        $(".clear-user-info").on("click", function() {
                            $(".stored-user-info").hide();
                            $(".user-info-fields").show();
                            document.cookie = "funky_bidding_user_info=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                        });
                        
                        $(".bidding-form").on("submit", function(e) {
                            e.preventDefault();
                            var formData = $(this).serializeArray();
                            var userInfo = {
                                user_name: "",
                                user_phone: ""
                            };
                            
                            $.each(formData, function(i, field) {
                                if (field.name === "user_name") {
                                    userInfo.user_name = field.value;
                                } else if (field.name === "user_phone") {
                                    userInfo.user_phone = field.value;
                                }
                            });

                            if (userInfo.user_name && userInfo.user_phone) {
                                document.cookie = "funky_bidding_user_info=" + JSON.stringify(userInfo) + "; path=/; max-age=2592000; SameSite=Strict";
                                $(this).unbind("submit").submit();
                            } else {
                                alert("Please fill in both name and phone number.");
                            }
                        });
                    });
                </script>';
            } else {
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
        if (isset($_POST['item_id'], $_POST['user_name'], $_POST['user_phone'], $_POST['bid_amount'])) {
            global $wpdb;
            $item_id = intval($_POST['item_id']);
            $user_name = sanitize_text_field($_POST['user_name']);
            $user_phone = sanitize_text_field($_POST['user_phone']);
            $bid_amount = floatval($_POST['bid_amount']);

            $item = $wpdb->get_row($wpdb->prepare("SELECT min_bid, bid_increment, max_bid FROM {$wpdb->prefix}bidding_items WHERE id = %d", $item_id));
            $highest_bid = $wpdb->get_var($wpdb->prepare("SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item_id));
            $min_next_bid = ($highest_bid) ? $highest_bid + $item->bid_increment : $item->min_bid;

            if ($bid_amount >= $min_next_bid && ($item->max_bid == 0 || $bid_amount <= $item->max_bid)) {
                $result = $wpdb->insert(
                    "{$wpdb->prefix}bidding_bids",
                    array(
                        'item_id' => $item_id,
                        'user_name' => $user_name,
                        'user_phone' => $user_phone,
                        'bid_amount' => $bid_amount,
                        'bid_time' => current_time('mysql')
                    )
                );

                if ($result !== false) {
                    $watchers = $wpdb->get_results($wpdb->prepare("SELECT user_email FROM {$wpdb->prefix}bidding_watchers WHERE item_id = %d", $item_id));
                    foreach ($watchers as $watcher) {
                        $subject = "New bid placed on watched item";
                        $message = "A new bid of $" . number_format($bid_amount, 2) . " has been placed on the item you're watching.";
                        wp_mail($watcher->user_email, $subject, $message);
                    }

                    $this->log_bidding_activity($item_id, $user_name, $user_phone, $bid_amount);

                    $redirect_url = add_query_arg(array(
                        'bid_success' => '1',
                        'item_id' => $item_id
                    ), wp_get_referer());
                    wp_safe_redirect($redirect_url . '#item-' . $item_id);
                    exit;
                }
            }
        }

        $redirect_url = add_query_arg(array(
            'bid_error' => '1',
            'item_id' => $item_id
        ), wp_get_referer());
        wp_safe_redirect($redirect_url . '#item-' . $item_id);
        exit;
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
        background-color: #ccc;
    }
    .funky-bidding-item {
        background-color: #ffffff;
        border-radius: 0px;
        padding: 0px;
        transition: transform 0.2s ease-in-out;
        position: relative;
        width: 100%;
        border: 2px solid #ebebeb;
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