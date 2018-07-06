<?php
/*
 * Plugin Name: Accesstrade Tracking
 * Version: 1.0.0
 * Description: The largest and most reputable affiliate marketing platform
 * Author: Pham Van Ke
 * Author URI: https://accesstrade.vn
 * Plugin URI: https://accesstrade.vn
 * Text Domain: accesstrade.vn
 * Domain Path: /languages
 * License: GPLv1
 * License URI: https://accesstrade.vn
*/

// Add scripts to wp_head()
add_action('wp_head', 'accesstrade_tracking_script');
function accesstrade_tracking_script()
{ ?>
    <script src="//cdn.accesstrade.vn/js/trackingtag/tracking.min.js"></script>
    <script type="text/javascript">
        AT.track();
    </script>
<?php }


add_action('woocommerce_after_order_notes', 'accesstrade_checkout_hidden_field', 10, 1);
function accesstrade_checkout_hidden_field($checkout)
{
    ?>
    <!-- Output the hidden aff_sid off accesstrade -->
    <div id="accesstrade_checkout_hidden_field">
        <input type="hidden" class="input-hidden" name="accesstrade_aff_sid" id="accesstrade_aff_sid" value="">
    </div>
    <script>
        function getCookieAccesstrade(cname) {
            var name = cname + "=";
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                    return c.substring(name.length, c.length);
                }
            }
            return "";
        }

        document.getElementById("accesstrade_aff_sid").value = getCookieAccesstrade('_aff_sid');
    </script>
<?php }


add_action('woocommerce_checkout_update_order_meta', 'save_accesstrade_checkout_hidden_field', 10, 1);
function save_accesstrade_checkout_hidden_field($order_id)
{
    if (!empty($_POST['accesstrade_aff_sid']))
        update_post_meta($order_id, '_accesstrade_aff_sid', sanitize_text_field($_POST['accesstrade_aff_sid']));
}


add_action('woocommerce_thankyou', 'accesstrade_tracking_2_class');
function accesstrade_tracking_2_class($order_id)
{
    $order = new WC_Order($order_id);
    $items = $order->get_items();
    ?>

    <script src="//cdn.accesstrade.vn/js/trackingtag/tracking.min.js"></script>
    <script type="text/javascript">
        var order_items = [];

        <?php
        $items_api = array();
        $order_id_to_accesstrade = (string)$order_id;
        foreach ( $items as $item ) {
        $product_id = $item['product_id'];
        $terms = get_the_terms($product_id, 'product_cat');
        $cat_id = "DEFAUL";
        foreach ($terms as $term) {
            $cat_id = $term->term_id;
            break;
        }
        array_push($items_api, array(
            "id" => (string)$item['product_id'],
            "sku" => (string)$item['product_id'],
            "name" => $item['name'],
            "price" => (int)str_replace(".", "", wc_format_decimal($item['line_total'], get_option('woocommerce_price_num_decimals'))),
            "quantity" => $item['qty'],
            "category" => (string)$cat_id,
            "category_id" => (string)$cat_id));

        ?>

        order_items.push({
            itemid: "<?php echo $item['product_id']; ?>",
            quantity: <?php echo $item['qty']; ?>,
            price:<?php echo str_replace(".", "", wc_format_decimal($item['line_total'], get_option('woocommerce_price_num_decimals'))); ?>,
            catid: "<?php echo $cat_id; ?>"
        });

        <?php }
        ?>

        AT.init({"campaign_id": <?php echo get_option('accesstrade_campaign_id')?> });
        accesstrade_order_info = {
            order_id: "<?php echo $order_id_to_accesstrade; ?>",
            amount: <?php echo str_replace(".", "", wc_format_decimal($order->total, get_option('woocommerce_price_num_decimals'))); ?>,
            discount: <?php echo str_replace(".", "", wc_format_decimal($order->discount_total, get_option('woocommerce_price_num_decimals'))); ?>,
            order_items: order_items
        };
        AT.track_order(accesstrade_order_info);
    </script>

    <?php
    $tracking_id = get_post_meta($order_id, '_accesstrade_aff_sid', true);
    if ($tracking_id != "" and $tracking_id != null) {
        $data = array(
            "conversion_id" => $order_id_to_accesstrade,
            "conversion_result_id" => (string)get_option('accesstrade_result_id'),
            "tracking_id" => get_post_meta($order_id, '_accesstrade_aff_sid', true),
            "transaction_id" => $order_id_to_accesstrade,
            "transaction_time" => date("Y-m-d H:i:s"),
            "transaction_value" => (int)str_replace(".", "", wc_format_decimal($order->total, get_option('woocommerce_price_num_decimals'))),
            "transaction_discount" => (int)str_replace(".", "", wc_format_decimal($order->discount_total, get_option('woocommerce_price_num_decimals'))),
            "items" => $items_api
        );
        push_conversions_to_accesstrade($data, "POST");
    }
}


add_action('woocommerce_order_status_changed', 'accesstrade_update_status_conversion');
function accesstrade_update_status_conversion($order_id, $checkout = null)
{
    global $woocommerce;
    $order = new WC_Order($order_id);
    $tracking_id = get_post_meta($order_id, '_accesstrade_aff_sid', true);
    if ($tracking_id != "" and $tracking_id != null) {
        $status = 0;
        if ($order->status == 'completed') {
            $status = 1;
        }

        if ($order->status == 'failed' or $order->status == 'cancelled') {
            $status = 2;
        }

        if ($status != 0) {
            $data = array(
                "transaction_id" => (string)$order_id,
                "status" => $status
            );
            push_conversions_to_accesstrade($data, "PUT");
        }
    }
}


function push_conversions_to_accesstrade($data, $method)
{
    $data_string = json_encode($data);
    $ch = curl_init('https://api.accesstrade.vn/v1/postbacks/conversions');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Token '.(string)get_option('accesstrade_access_key')
    ));
    $result = curl_exec($ch);
?>
<pre>
    <h2>
        <?php echo('Authorization: Token '.(string)get_option('accesstrade_access_key')); ?>
    </h2>

    <h1>

        <?php echo var_dump($result); ?>
    </h1>
</pre>


<?php
    return $result;
}

?>

<?php
function register_mysettings()
{
    register_setting('at-tracking-settings-group', 'at-tracking_option_name');
}

function at_tracking_create_menu()
{
    add_menu_page('Accesstrade tracking', 'At tracking', 'administrator', __FILE__, 'at_tracking_settings_page', plugins_url('/images/accesstrade_icon.png', __FILE__), 10);
    add_action('admin_init', 'register_mysettings');
}

add_action('admin_menu', 'at_tracking_create_menu');


add_action('admin_init', function () {
    register_setting('at-tracking-settings-group', 'accesstrade_access_key');
    register_setting('at-tracking-settings-group', 'accesstrade_result_id');
    register_setting('at-tracking-settings-group', 'accesstrade_campaign_id');
});

function at_tracking_settings_page()
{
    ?>
    <div>
        <h2>Cấu hình cho Accesstrade tracking:</h2>
        <?php if (isset($_GET['settings-updated'])) { ?>
            <div id="message">
                <p><strong><?php _e('Settings saved.') ?></strong></p>
            </div>
        <?php } ?>
        <form method="post" action="options.php">
            <?php settings_fields('at-tracking-settings-group'); ?>
            <?php do_settings_sections('at-tracking-settings-group'); ?>

            <table>
                <tr>
                    <td width="30%">Advertiser name:</td>
                    <td><input type="text" name="at-tracking_option_name"
                               value="<?php echo get_option('at-tracking_option_name'); ?>"/></td>
                </tr>
                <tr>
                    <td width="30%">Accesstrade access_key:</td>
                    <td><input type="text" name="accesstrade_access_key"
                               value="<?php echo get_option('accesstrade_access_key'); ?>"/></td>
                </tr>
                <tr>
                    <td width="30%">Accesstrade campaign_id:</td>
                    <td><input type="text" name="accesstrade_campaign_id"
                               value="<?php echo get_option('accesstrade_campaign_id'); ?>"/></td>
                </tr>
                <tr>
                    <td width="30%">Accesstrade result_id:</td>
                    <td><input type="text" name="accesstrade_result_id"
                               value="<?php echo get_option('accesstrade_result_id'); ?>"/></td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
<?php } ?>

