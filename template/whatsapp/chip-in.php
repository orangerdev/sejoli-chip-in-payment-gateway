<?php
$payment_link = add_query_arg(array(
    'order_id'  => $order['ID']
),site_url('checkout/thank-you'));

$user_info = get_userdata($order['user_id']);

?>
<?php printf(__('%s bisa melanjutkan pembayaran melalui link dibawah ini', 'sejoli-chip-in'), $user_info->display_name);?>

<?php echo $payment_link; ?> .
