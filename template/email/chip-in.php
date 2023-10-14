<?php
$payment_link = add_query_arg(array(
    'order_id'  => $order['ID']
),site_url('checkout/thank-you'));

$user_info = get_userdata($order['user_id']);
?>

<?php printf(__('%s bisa melanjutkan pembayaran melalui link dibawah ini', 'sejoli-chip-in'), $user_info->display_name);?>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td>
      <div style='text-align:center;'>
        <!--[if mso]>
          <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="http://litmus.com" style="height:36px;v-text-anchor:middle;width:150px;" arcsize="5%" strokecolor="#EB7035" fillcolor="#EB7035">
            <w:anchorlock/>
            <center style="color:#ffffff;font-family:Helvetica, Arial,sans-serif;font-size:16px;">I am a button &rarr;</center>
          </v:roundrect> 
        <![endif]-->
        <a href='<?php echo $payment_link; ?>' target="_blank" style="background-color:#EB7035;border:1px solid #EB7035;border-radius:3px;color:#ffffff;display:inline-block;font-family:sans-serif;font-size:16px;line-height:44px;text-align:center;text-decoration:none;width:80%;padding:12px 20px;-webkit-text-size-adjust:none;mso-hide:all;"><?php _e('Selesaikan pembayaran', 'sejoli-chip-in'); ?></a>
        <br/>
      </div>
    </td>
  </tr>
</table>
