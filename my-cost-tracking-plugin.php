<?php
/**
 * Plugin Name: Profit Tracking Plugin
 * Plugin URI: YourPluginURI
 * Description: Calculate profits in WooCommerce.
 * Version: 1.0.0
 * Author: Vladimir
 * Author URI: YourAuthorURI
 * Text Domain: my-cost-tracking-plugin
 */

add_action('plugins_loaded', 'my_cost_tracking_plugin_init');

function my_cost_tracking_plugin_init() {
    add_action('add_meta_boxes', 'my_cost_tracking_add_product_cost_meta_box');
    add_action('woocommerce_process_product_meta', 'my_cost_tracking_save_product_cost');
    add_action('woocommerce_order_status_completed', 'my_cost_tracking_calculate_profit');
    add_action('woocommerce_variation_options_pricing', 'my_cost_tracking_add_variation_cost_field', 10, 3);
    add_action('woocommerce_save_product_variation', 'my_cost_tracking_save_variation_cost', 10, 2);
    add_action('woocommerce_admin_order_data_after_shipping_address', 'my_cost_tracking_add_shipping_cost_metabox', 10, 1);
    add_action('woocommerce_process_shop_order_meta', 'my_cost_tracking_save_shipping_cost');
}

function my_cost_tracking_add_product_cost_meta_box() {
    add_meta_box(
        'my_cost_tracking_product_cost',
        __('Product Cost', 'my-cost-tracking-plugin'),
        'my_cost_tracking_render_product_cost_meta_box',
        'product',
        'normal',
        'default'
    );
}

function my_cost_tracking_render_product_cost_meta_box($post) {
    $product_cost = get_post_meta($post->ID, '_product_cost', true);
    ?>
    <p>
        <label for="product_cost"><?php _e('Cost:', 'my-cost-tracking-plugin'); ?></label>
        <input type="number" step="0.01" min="0" id="product_cost" name="product_cost" value="<?php echo esc_attr($product_cost); ?>" />
    </p>
    <?php
}

function my_cost_tracking_save_product_cost($product_id) {
    if (isset($_POST['product_cost'])) {
        $product_cost = wc_format_decimal($_POST['product_cost']);
        update_post_meta($product_id, '_product_cost', $product_cost);
    }
}

function my_cost_tracking_add_variation_cost_field($loop, $variation_data, $variation) {
    woocommerce_wp_text_input(
        array(
            'id'            => '_variation_cost[' . $loop . ']',
            'label'         => __('Cost', 'my-cost-tracking-plugin'),
            'name'          => '_variation_cost[' . $loop . ']',
            'value'         => get_post_meta($variation->ID, '_variation_cost', true),
            'data_type'     => 'price',
            'wrapper_class' => 'form-row form-row-full',
        )
    );
}

function my_cost_tracking_save_variation_cost($variation_id, $i) {
    if (isset($_POST['_variation_cost'][$i])) {
        $variation_cost = wc_format_decimal($_POST['_variation_cost'][$i]);
        update_post_meta($variation_id, '_variation_cost', $variation_cost);
    }
}

function my_cost_tracking_calculate_profit($order_id) {
    $order = wc_get_order($order_id);
    $order_total = $order->get_total();
    $profit = $order_total;

    // Iterate through the order items to calculate cost
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();

        $quantity = $item->get_quantity(); // Get the item's quantity

        if ($variation_id > 0) {
            // Variation cost
            $variation_cost = get_post_meta($variation_id, '_variation_cost', true);

            // Subtract the variation cost multiplied by quantity from the profit
            $profit -= $variation_cost * $quantity;
        } else {
            // Product cost
            $product_cost = get_post_meta($product_id, '_product_cost', true);

            // Subtract the product cost multiplied by quantity from the profit
            $profit -= $product_cost * $quantity;
        }
    }

    // Subtract the shipping cost from the profit calculation
    $shipping_cost = get_post_meta($order_id, '_shipping_cost', true);
    $profit -= $shipping_cost;

    // Subtract the additional fee from the profit calculation
    $additional_fee = get_post_meta($order_id, '_additional_fee', true);
    $profit -= $additional_fee;

    update_post_meta($order_id, '_order_profit', $profit);
}

function my_cost_tracking_display_profit($order) {
    $profit = get_post_meta($order->get_id(), '_order_profit', true);

    if ($profit !== '') {
        // Apply green color for positive profit and red color for negative profit
        $profit_color = $profit >= 0 ? 'green' : 'red';
        $profit_formatted = wc_price($profit);

        echo '<strong style="color: ' . $profit_color . ';">' . __('Profit:', 'my-cost-tracking-plugin') . '</strong> ' . $profit_formatted;
    }
}

function my_cost_tracking_add_profit_column($columns) {
    $columns['order_profit'] = __('Profit', 'my-cost-tracking-plugin');
    return $columns;
}

function my_cost_tracking_display_profit_column($column) {
    if ($column === 'order_profit') {
        global $post;
        $order = wc_get_order($post->ID);
        my_cost_tracking_display_profit($order);
    }
}

function my_cost_tracking_add_shipping_cost_metabox($order) {
    woocommerce_wp_text_input(
        array(
            'id'            => '_shipping_cost',
            'label'         => __('Shipping Cost', 'my-cost-tracking-plugin'),
            'placeholder'   => '',
            'desc_tip'      => true,
            'description'   => __('Enter the shipping cost for this order.', 'my-cost-tracking-plugin'),
            'value'         => get_post_meta($order->get_id(), '_shipping_cost', true),
        )
    );

    woocommerce_wp_text_input(
        array(
            'id'            => '_additional_fee',
            'label'         => __('Additional Fee', 'my-cost-tracking-plugin'),
            'placeholder'   => '',
            'desc_tip'      => true,
            'description'   => __('Enter any additional fee for this order.', 'my-cost-tracking-plugin'),
            'value'         => get_post_meta($order->get_id(), '_additional_fee', true),
        )
    );
}

function my_cost_tracking_save_shipping_cost($order_id) {
    if (isset($_POST['_shipping_cost'])) {
        $shipping_cost = wc_format_decimal($_POST['_shipping_cost']);
        update_post_meta($order_id, '_shipping_cost', $shipping_cost);
    } else {
        delete_post_meta($order_id, '_shipping_cost');
    }

    if (isset($_POST['_additional_fee'])) {
        $additional_fee = wc_format_decimal($_POST['_additional_fee']);
        update_post_meta($order_id, '_additional_fee', $additional_fee);
    } else {
        delete_post_meta($order_id, '_additional_fee');
    }

      // Calculate profit on order update
    my_cost_tracking_calculate_profit($order_id);
}

add_filter('manage_edit-shop_order_columns', 'my_cost_tracking_add_profit_column');
add_action('manage_shop_order_posts_custom_column', 'my_cost_tracking_display_profit_column');


// Add the dashboard widget
add_action('wp_dashboard_setup', 'my_cost_tracking_add_dashboard_widget');

function my_cost_tracking_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'my_cost_tracking_dashboard_widget',
        __('Profit Tracking', 'my-cost-tracking-plugin'),
        'my_cost_tracking_render_dashboard_widget'
    );
}

// Render the dashboard widget
function my_cost_tracking_render_dashboard_widget() {
    // Get the current month and year
    $current_month = date('m');
    $current_year = date('Y');

    // Calculate the start and end date of the current month
    $start_date = date('Y-m-01', strtotime("{$current_year}-{$current_month}-01"));
    $end_date = date('Y-m-t', strtotime("{$current_year}-{$current_month}-01"));

    // Query all orders within the current month
    $args = array(
        'post_type'      => 'shop_order',
        'post_status'    => array('wc-completed', 'wc-processing', 'wc-on-hold'),
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_order_profit',
                'compare' => 'EXISTS',
            ),
            array(
                'key'     => '_completed_date',
                'value'   => array($start_date, $end_date),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        ),
    );

    $orders = get_posts($args);

    // Calculate total profit for the current month
    $total_profit = 0;
    foreach ($orders as $order) {
        $order_profit = get_post_meta($order->ID, '_order_profit', true);
        $total_profit += floatval($order_profit);
    }

    // Output the profit information
    echo '<p>' . __('Total Profit for Current Month:', 'my-cost-tracking-plugin') . ' ' . wc_price($total_profit) . '</p>';
}
