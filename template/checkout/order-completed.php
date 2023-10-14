<?php
    include 'header-thankyou.php';
    include 'header-logo.php';

    $thumbnail_url = get_the_post_thumbnail_url($order['product']->ID,'full');
?>
<div class="ui text container">
    <div class="thankyou">
        <h2><?php _e('Halo ', 'sejoli-chip-in'); echo $order['user']->display_name; ?></h2>
        <div class="thankyou-info-1">
            <p><?php _e('Terima kasih.', 'sejoli-chip-in'); ?></p>
            <p><?php printf(__('Pesanan untuk order INV %s telah selesai.', 'sejoli-chip-in'), $order['ID']); ?></p>
            <p><?php _e('Silahkan cek email anda untuk infomasi selanjutnya.', 'sejoli-chip-in'); ?></p>
        </div>
    </div>
</div>
<?php
    include 'footer-secure.php';
    include 'footer.php';
?>