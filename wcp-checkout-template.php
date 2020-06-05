<?php
get_header();
$show_order = isset($_REQUEST["show_order"]) ? $_REQUEST["show_order"] : ""; //order key
$order_id = wc_get_order_id_by_order_key($show_order);
$order = wc_get_order($order_id);
$WC_Payment_Gateway = wc_get_payment_gateway_by_order($order);

// Redirect to "order received" page if the order is already paid or in other statuse
if ($order->get_status() != "pending") {
    switch ($order->get_status()) {
        case "cancelled":
            $redirect = $order->get_checkout_order_received_url() . "&order-pay=" . $order_id;
            break;
        default:
            $redirect = $order->get_checkout_order_received_url();
            break;
    }
    wp_safe_redirect($redirect);
    exit;
}

$instructions = $WC_Payment_Gateway->fill_in_instructions($order);
?>

<section class="page type-page status-publish hentry entry">
    <header class="entry-header">

        <h1 class="entry-title"><?php echo __('Pay for order', 'woocommerce') ?></h1>
    </header>
    <div class="entry-content">
        <div class="woocommerce">
            <div class="woocommerce-notices-wrapper"></div>

            <ul class="order_details">
                <li class="order">
                    <?php esc_html_e('Order number:', 'woocommerce'); ?>
                    <strong><?php echo esc_html($order->get_order_number()); ?></strong>
                </li>
                <li class="date">
                    <?php esc_html_e('Date:', 'woocommerce'); ?>
                    <strong><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></strong>
                </li>
                <li class="total">
                    <?php esc_html_e('Total:', 'woocommerce'); ?>
                    <strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong>
                </li>
                <?php if ($order->get_payment_method_title()) : ?>
                    <li class="method">
                        <?php esc_html_e('Payment method:', 'woocommerce'); ?>
                        <strong><?php echo wp_kses_post($order->get_payment_method_title()); ?></strong>
                    </li>
                <?php endif; ?>
            </ul>

            <?php
            echo wpautop(wptexturize($instructions));
            ?>

</section>

<?php
get_footer();
?>