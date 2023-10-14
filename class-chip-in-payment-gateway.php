<?php
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Illuminate\Database\Capsule\Manager as Capsule;

final class SejoliChipIn extends \SejoliSA\Payment{

    /**
     * Prevent double method calling
     * @since   1.0.0
     * @access  protected
     * @var     boolean
     */
    protected $is_called = false;

    /**
     * Redirect urls
     * @since   1.0.0
     * @var     array
     */
    public $endpoint = array(
        'sandbox' => 'https://gate.chip-in.asia/api/v1/',
        'live'    => 'https://gate.chip-in.asia/api/v1/'
    );

    /**
     * Order price
     * @since 1.0.0
     * @var float
     */
    protected $order_price = 0.0;

    /**
     * Table name
     * @since 1.0.0
     * @var string
     */
    protected $table = 'sejolisa_chip_in_transaction';

    /**
     * Construction
     */
    public function __construct() {
        
        global $wpdb;

        $this->id          = 'chip-in';
        $this->name        = __( 'Chip In', 'sejoli-chip-in' );
        $this->title       = __( 'Chip In', 'sejoli-chip-in' );
        $this->description = __( 'Transaksi via Chip In Payment Gateway.', 'sejoli-chip-in' );
        $this->table       = $wpdb->prefix . $this->table;

        add_action('admin_init',                     [$this, 'register_trx_table'],  1);
        add_filter('sejoli/payment/payment-options', [$this, 'add_payment_options']);
        add_filter('query_vars',                     [$this, 'set_query_vars'],     999);
        add_action('sejoli/thank-you/render',        [$this, 'check_for_redirect'], 1);
        add_action('init',                           [$this, 'set_endpoint'],       1);
        add_action('parse_query',                    [$this, 'check_parse_query'],  100);

    }

    /**
     * Register transaction table
     * Hooked via action admin_init, priority 1
     * @since   1.0.0
     * @return  void
     */
    public function register_trx_table() {

        if( !Capsule::schema()->hasTable( $this->table ) ):

            Capsule::schema()->create( $this->table, function( $table ) {
                $table->increments('ID');
                $table->datetime('created_at');
                $table->datetime('last_check')->default('0000-00-00 00:00:00');
                $table->integer('order_id');
                $table->string('status');
                $table->text('detail')->nullable();
            });

        endif;

    }

    /**
     * Get duitku order data
     * @since   1.0.0
     * @param   int $order_id
     * @return  false|object
     */
    protected function check_data_table( int $order_id ) {

        return Capsule::table($this->table)
            ->where(array(
                'order_id'  => $order_id
            ))
            ->first();

    }

    /**
     * Add transaction data
     * @since   1.0.0
     * @param   integer $order_id Order ID
     * @return  void
     */
    protected function add_to_table( int $order_id ) {

        Capsule::table($this->table)
            ->insert([
                'created_at' => current_time('mysql'),
                'last_check' => '0000-00-00 00:00:00',
                'order_id'   => $order_id,
                'status'     => 'pending'
            ]);
    
    }

    /**
     * Update data status
     * @since   1.0.0
     * @param   integer $order_id [description]
     * @param   string $status [description]
     * @return  void
     */
    protected function update_status( $order_id, $status ) {
        
        Capsule::table($this->table)
            ->where(array(
                'order_id' => $order_id
            ))
            ->update(array(
                'status'    => $status,
                'last_check'=> current_time('mysql')
            ));

    }

    /**
     * Update data detail payload
     * @since   1.0.0
     * @param   integer $order_id [description]
     * @param   array $detail [description]
     * @return  void
     */
    protected function update_detail( $order_id, $detail ) {
        
        Capsule::table($this->table)
            ->where(array(
                'order_id' => $order_id
            ))
            ->update(array(
                'detail' => serialize($detail),
            ));

    }

    /**
     *  Set end point custom menu
     *  Hooked via action init, priority 999
     *  @since   1.0.0
     *  @access  public
     *  @return  void
     */
    public function set_endpoint() {
        
        add_rewrite_rule( '^chip-in/([^/]*)/?', 'index.php?chip-in-method=1&action=$matches[1]', 'top' );

        flush_rewrite_rules();
    
    }

    /**
     * Set custom query vars
     * Hooked via filter query_vars, priority 100
     * @since   1.0.0
     * @access  public
     * @param   array $vars
     * @return  array
     */
    public function set_query_vars( $vars ) {

        $vars[] = 'chip-in-method';

        return $vars;
    
    }

    /**
     * Check parse query and if duitku-method exists and process
     * Hooked via action parse_query, priority 999
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function check_parse_query() {

        global $wp_query;

        if( is_admin() || $this->is_called ) :

            return;

        endif;

        if(
            isset( $wp_query->query_vars['chip-in-method'] ) &&
            isset( $wp_query->query_vars['action'] ) && !empty( $wp_query->query_vars['action'] )
        ) :

            if( 'callback' === $wp_query->query_vars['action'] ) :

                $this->is_called = true;
                $this->process_callback();

            elseif( 'redirect' === $wp_query->query_vars['action'] ) :

                $this->is_called = true;
                $this->receive_redirect();

            elseif( 'webhook' === $wp_query->query_vars['action'] ) :

                $this->is_called = true;
                $this->get_webhook();

            endif;

        endif;

    }

    /**
     * Set option in Sejoli payment options, we use CARBONFIELDS for plugin options
     * Called from parent method
     * @since   1.0.0
     * @return  array
     */
    public function get_setup_fields() {

        return array(

            Field::make('separator', 'sep_chip_in_transaction_setting', __('Pengaturan Chip In', 'sejoli-chip-in')),

            Field::make('checkbox', 'chip_in_active', __('Aktifkan pembayaran melalui Chip In', 'sejoli-chip-in')),
            
            Field::make('select', 'chip_in_mode', __('Payment Mode', 'sejoli-chip-in'))
            ->set_options(array(
                'sandbox' => __('Sandbox', 'sejoli-chip-in'),
                'live'    => __('Live', 'sejoli-chip-in'),
            ))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                )
            )),

            Field::make('separator', 'sep_chip_in_credentials_setting', __('Credentials', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                )
            )),

            Field::make('text', 'chip_in_brand_id_sandbox', __('Brand ID (Sandbox)', 'sejoli-chip-in'))
            ->set_required(true)
            ->set_help_text(__('Brand ID can be obtained from CHIP Collect Dashboard >> Developers >> Brands.', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                ),array(
                    'field' => 'chip_in_mode',
                    'value' => 'sandbox'
                )
            )),

            Field::make('text', 'chip_in_secret_key_sandbox', __('Secret API Key (Sandbox)', 'sejoli-chip-in'))
            ->set_required(true)
            ->set_help_text(__('Secret key can be obtained from CHIP Collect Dashboard >> Developers >> Keys.', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                ),array(
                    'field' => 'chip_in_mode',
                    'value' => 'sandbox'
                )
            )),

            Field::make('text', 'chip_in_brand_id_live', __('Brand ID (Live)', 'sejoli-chip-in'))
            ->set_required(true)
            ->set_help_text(__('Brand ID can be obtained from CHIP Collect Dashboard >> Developers >> Brands.', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                ),array(
                    'field' => 'chip_in_mode',
                    'value' => 'live'
                )
            )),

            Field::make('text', 'chip_in_secret_key_live', __('Secret API Key (Live)', 'sejoli-chip-in'))
            ->set_required(true)
            ->set_help_text(__('Secret key can be obtained from CHIP Collect Dashboard >> Developers >> Keys.', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                ),array(
                    'field' => 'chip_in_mode',
                    'value' => 'live'
                )
            )),

            Field::make('separator', 'sep_chip_in_webhook_setting', __('Webhooks', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                )
            )),

            Field::make('text', 'chip_in_webhook_public_key', __('Webhook Public Key', 'sejoli-chip-in'))
            ->set_required(false)
            ->set_help_text(__('This option to set public key that are generated through CHIP Dashboard >> Webhooks page. The callback url is: '. site_url('/chip-in/webhook'), 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                )
            )),

            Field::make('separator', 'sep_chip_in_miscellaneous_setting', __('Miscellaneous', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                )
            )),

            Field::make('select', 'chip_in_purchase_time_zone', __('Purchase Time Zone', 'sejoli-chip-in'))
            ->set_required(true)  
            ->set_options($this->get_timezone_list())
            ->set_default_value('Asia/Kuala_Lumpur')
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                )
            )),

            Field::make('checkbox', 'chip_in_due_active', __('Enable Due Strict', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                )
            )),

            Field::make('text', 'chip_in_due', __('Due Strict Timing (minutes)', 'sejoli-chip-in'))
            ->set_required(false)
            ->set_help_text(__('Due strict timing in minutes. Default to hold stock minutes: 60. This will only be enforced if Due Strict option is activated.', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                ),
                array(
                    'field' => 'chip_in_due_active',
                    'value' => true
                )
            )),

            Field::make('checkbox', 'chip_in_purchase_send_receipt', __('Purchase Send Receipt', 'sejoli-chip-in'))
            ->set_help_text(__('Tick to ask CHIP to send receipt upon successful payment. If activated, CHIP will send purchase receipt upon payment completion.', 'sejoli-chip-in'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'chip_in_active',
                    'value' => true
                )
            )),

        );

    }

    /**
     * Display chip in payment options in checkout page
     * Hooked via filter sejoli/payment/payment-options, priority 100
     * @since   1.0.0
     * @param   array $options
     * @return  array
     */
    public function add_payment_options( array $options ) {
        
        $active = boolval( carbon_get_theme_option('chip_in_active') );

        if( true === $active ) :

            $methods = array(
                'chip-in'
            );
            $image_source_url = plugin_dir_url(__FILE__);

            foreach($methods as $method_id) :

                // MUST PUT ::: after payment ID
                $key = 'chip-in:::' . $method_id;

                switch( $method_id ) :

                    case 'chip-in' :

                        $options[$key] = [
                            'label' => __( 'Transaksi via Chip_in', 'sejoli-chip-in' ),
                            'image' => $image_source_url . 'img/chip-in-logo.png'
                        ];

                        break;

                endswitch;

            endforeach;

        endif;

        return $options;

    }

    /**
     * Set order price if there is any fee need to be added
     * @since   1.0.0
     * @param   float $price
     * @param   array $order_data
     * @return  float
     */
    public function set_price( float $price, array $order_data ) {

        if( 0.0 !== $price ) :

            $this->order_price = $price;

            return floatval( $this->order_price );

        endif;

        return $price;

    }

    /**
     * Get setup values
     * @return array
     */
    protected function get_setup_values() {

        $mode               = carbon_get_theme_option('chip_in_mode');
        $endpoint           = $this->endpoint[$mode];
        $brand_id           = trim( carbon_get_theme_option('chip_in_brand_id_'.$mode) );
        $secret_key         = trim( carbon_get_theme_option('chip_in_secret_key_'.$mode) );
        $webhook_public_key = carbon_get_theme_option('chip_in_webhook_public_key');
        $base_url           = get_bloginfo('url');

        return array(
            'mode'               => $mode,
            'endpoint'           => $endpoint,
            'brand_id'           => $brand_id,
            'secret_key'         => $secret_key,
            'webhook_public_key' => $webhook_public_key,
            'base_url'           => $base_url
        );

    }

    /**
     * Set order meta data
     * @since   1.0.0
     * @param   array $meta_data
     * @param   array $order_data
     * @param   array $payment_subtype
     * @return  array
     */
    public function set_meta_data( array $meta_data, array $order_data, $payment_subtype ) {

        $trans_id = $order_data['user_id'].$order_data['grand_total'];

        $meta_data['chip-in'] = [
            'trans_id'   => substr( md5( $trans_id ), 0, 20 ),
            'unique_key' => substr( md5( rand( 0,1000 ) ), 0, 16 ),
            'method'     => $payment_subtype
        ];

        return $meta_data;

    }

    /**
     * Prepare Chip In Data
     * @since   1.0.0
     * @return  array
     */
    public function prepare_chip_in_data( array $order ) {

        extract( $this->get_setup_values() );

        $redirect_link         = '';
        $request_to_chip_in    = false;
        $data_order            = $this->check_data_table( $order['ID'] );
        $payment_method        = "".$order['meta_data']['chip-in']['method']."";
        $product_name          = $order['product_name'];
        $qty                   = $order['quantity'];
        $purchase_send_receipt = boolval(carbon_get_theme_option('chip_in_purchase_send_receipt'));
        $due_strict            = boolval(carbon_get_theme_option('chip_in_due_active'));
        $purchase_time_zone    = carbon_get_theme_option('chip_in_purchase_time_zone');
        $currency              = carbon_get_theme_option('sejoli_currency_type');

        if ( isset( $order['meta_data']['shipping_data'] ) ) {

            $product_price             = $order['product']->price;
            $grand_total               = $order['grand_total'] - $order['meta_data']['shipping_data']['cost']; 
            $discount                  = $order['meta_data']['coupon']['discount'];
            $receiver_destination_id   = $order['meta_data']['shipping_data']['district_id'];
            $receiver_destination_city = sejolise_get_subdistrict_detail( $receiver_destination_id );
            $receiver_city             = $receiver_destination_city['type'].' '.$receiver_destination_city['city'];
            $receiver_province         = $receiver_destination_city['province'];
            $recipient_name            = $order['meta_data']['shipping_data']['receiver'];
            $recipient_address         = $order['address'];
            $recipient_phone           = $order['meta_data']['shipping_data']['phone'];
            $recipient_email           = $order['user_email'];
            $payment_amount            = (int) $grand_total; 
        
        } else {
            
            if ( isset( $order['product']->subscription ) ){
                $grand_total = $order['grand_total'];
                $grand_total = $grand_total;
            } else {
                $grand_total = $order['grand_total'];
                $grand_total = $grand_total;
            }
            
            $product_price             = $order['product']->price;
            $discount                  = $order['meta_data']['coupon']['discount'];
            $receiver_destination_id   = $order['user']->data->meta->destination;
            $receiver_destination_city = sejolise_get_subdistrict_detail( $receiver_destination_id );
            $receiver_city             = $receiver_destination_city['type'].' '.$receiver_destination_city['city'];
            $receiver_province         = $receiver_destination_city['province'];
            $recipient_name            = $order['user']->data->display_name;
            $recipient_address         = $order['user']->data->meta->address;
            $recipient_phone           = $order['user']->data->meta->phone;
            $recipient_email           = $order['user_email'];
            $payment_amount            = (int) $grand_total; 
        
        }

        if(isset($data_order->detail)):
        
            $detail = unserialize( $data_order->detail );
        
        endif;

        if( NULL === $data_order ) :
            
            $request_to_chip_in = true;
        
        else :

            if( !isset( $detail->invoice_url ) || empty( $detail->invoice_url ) ) :
                $request_to_chip_in = true;
            else :
                $redirect_link = $detail->invoice_url;
            endif;

        endif;

        $previx_refference = carbon_get_theme_option('chip_in_inv_prefix');

        if( true === $request_to_chip_in ) :

            $this->add_to_table( $order['ID'] );

            if(true === $purchase_send_receipt):
                $send_receipt = 1;
            else:
                $send_receipt = 0;
            endif;

            if ( !empty( $brand_id ) && !empty( $secret_key ) ) :

                $chip = new \Chip\ChipApi($brand_id, $secret_key, $endpoint);

                $client = new \Chip\Model\ClientDetails();
                $client->email = $recipient_email;
                $client->phone = $recipient_phone;
                $client->full_name = $recipient_name;
                $client->street_address = $recipient_address;
                $client->city = $receiver_city;
                $client->shipping_street_address = $recipient_address;
                $client->shipping_city = $receiver_city;
                $purchase = new \Chip\Model\Purchase();
                $purchase->client = $client;
                $details = new \Chip\Model\PurchaseDetails();
                $product = new \Chip\Model\Product();
                $product->name = $product_name;
                $product->quantity = $qty;
                $product->discount = $discount;
                $product->price = round( ($product_price * 100) / $qty );
                $details->subtotal_override = round( $payment_amount * 100 );
                $details->total_override = round( $payment_amount * 100 );
                $details->due_strict = $due_strict;
                $details->currency = $currency;
                $details->timezone = $purchase_time_zone;
                $details->products = [$product];
                $purchase->purchase = $details;
                $purchase->creator_agent = "Sejoli";
                $purchase->reference = $order['ID'];
                $purchase->platform = "sejoli";
                if(true === $due_strict) :
                    $purchase->due = $this->get_due_timestamp();
                endif;
                // $purchase->payment_method_whitelist = $payment_method;
                $purchase->brand_id = $brand_id;
                $purchase->send_receipt = $send_receipt;
                $purchase->platform = "web";
                $purchase->success_redirect = add_query_arg(array(
                                                    'order_id' => $order['ID'],
                                                    'success'  => 1
                                            ), site_url('/chip-in/redirect'));
                $purchase->failure_redirect = add_query_arg(array(
                                                    'order_id' => $order['ID'],
                                                    'success'  => 0
                                            ), site_url('/chip-in/redirect'));
                $purchase->cancel_redirect = site_url();
                $purchase->success_callback = add_query_arg(array(
                                            ), site_url('/chip-in/callback'));

                $result = $chip->createPurchase($purchase);
                $result->invoice_url = add_query_arg(array(
                            'order_id' => $order['ID'],
                        ), site_url('checkout/thank-you'));

                $this->update_detail( $order['ID'], $result );

                do_action( 'sejoli/log/write', 'success-chip-in', $result );

                if ($result && $result->checkout_url) {
                    // Redirect user to checkout           
                    $redirect_link = $result->checkout_url;
                }

            endif;

        endif;

        wp_redirect( $redirect_link );

        exit;

    }

    /**
     * Get Chip In Data
     * @since   1.0.0
     * @return  array
     */
    public function get_chip_in_data( $purchase_id ) {

        extract( $this->get_setup_values() );

        $chip = new \Chip\ChipApi($brand_id, $secret_key, $endpoint);

        $purchase = $chip->getPurchase($purchase_id);

        $response = json_encode($purchase);

        return $response;

    }

    /**
     * Get Public Key
     * @since   1.0.0
     * @return  array
     */
    public function get_public_key() {

        extract( $this->get_setup_values() );

        $chip = new \Chip\ChipApi($brand_id, $secret_key, $endpoint);

        # GET PUBLIC KEY
        $url = $endpoint . "public_key/";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
          "Authorization: Bearer " . $secret_key,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $publicKey = json_decode(curl_exec($curl));
        curl_close($curl);

        header("Content-Type: application/json");
        echo $publicKey;

    }

    /**
     * Get Webhook
     * @since   1.0.0
     * @return  array
     */
    public function get_webhook() {

        extract( $this->get_setup_values() );

        if ( !isset($headers["X-Signature"]) ) {
            exit;
        }

        $chip = new \Chip\ChipApi($brand_id, $secret_key, $endpoint);

        $post = file_get_contents('php://input'); # lib/Model/Purchase.php
        $headers = getallheaders();
        $xSignature = $headers["X-Signature"];

        if ( openssl_verify( $post,  base64_decode( $headers["X-Signature"] ), $webhook_public_key, 'sha256WithRSAEncryption' ) != 1 ) {
            exit;
        }

        $data = json_decode($post, true );

        # GET PUBLIC KEY
        $publicKey = $webhook_public_key;

        $verify = \Chip\ChipApi::verify($post, $xSignature, $publicKey);
        error_log("/webhook EVENT: " . $data->event_type);
        error_log("/webhook VERIFIED: " . ($verify ? "true" : "false"));

    }

    /**
     * Update order status based on product type ( digital or physic)
     * It's fired when payment module confirm the order payment
     *
     * @since   1.0.0
     * @param   int     $order_id
     * @return  void
     */
    protected function set_order_status($order_id, $status) {

        $respond = sejolisa_get_order(['ID' => $order_id]);

        if(false !== $respond['valid']) :

            $order   = $respond['orders'];
            $product = sejolisa_get_product($order['product_id']);

            do_action('sejoli/order/update-status',[
                'ID'       => $order['ID'],
                'status'   => $status
            ]);

        endif;

    }

    /**
     * Process callback from chip-in
     * @since   1.0.0
     * @return  void
     */
    protected function process_callback() {

        extract( $this->get_setup_values() );

        $chip = new \Chip\ChipApi($brand_id, $secret_key, $endpoint);

        # Option 1: Use success_callback parameter of the Purchase object
        $post = file_get_contents('php://input'); # lib/Model/Purchase.php
        $headers = getallheaders();
        $xSignature = $headers["X-Signature"];

        # GET PUBLIC KEY
        $url = $endpoint . "public_key/";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            "Authorization: Bearer " . $secret_key,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $publicKey = json_decode(curl_exec($curl));
        curl_close($curl);

        $verify = \Chip\ChipApi::verify($post, $xSignature, $publicKey);
        error_log("/callback VERIFIED: " . ($verify ? "true" : "false"));

    }

    /**
     * Process redirect from chip-in
     * @since   1.0.0
     * @return  void
     */
    protected function receive_redirect() {

        $args = wp_parse_args($_GET, array(
            'order_id' => NULL,
            'success'  => NULL
        ));

        if(
            !empty( $args['order_id'] )
        ) :

            if($args['success'] === '1') :

                $data_order = $this->check_data_table( $args['order_id'] );
                $order_id   = intval( $args['order_id'] );
                $response   = sejolisa_get_order( array( 'ID' => $order_id ) );

                if( false !== $response['valid'] ) :

                    $order   = $response['orders'];
                    $product = $order['product'];

                    // if product is need of shipment
                    if( 'physical' === $product->type ) :
                        $set_status = 'in-progress';
                    else :
                        $set_status = 'completed';
                    endif;

                    sejolisa_update_order_meta_data($order_id, array(
                        'chip-in' => array(
                            'status' => esc_attr($set_status)
                        )
                    ));

                    $this->set_order_status( $order_id, $set_status );

                    wp_redirect(add_query_arg(array(
                        'order_id' => $order_id,
                        'status'   => "success"
                    ), site_url('checkout/thank-you')));

                    do_action( 'sejoli/log/write', 'chip-in-update-order', $args );

                    exit;

                else :

                    do_action( 'sejoli/log/write', 'chip-in-wrong-order', $args );
                
                endif;
                    
            else:
                    
                $order_id   = intval($args['order_id']);
                $set_status = 'cancelled';

                sejolisa_update_order_meta_data($order_id, array(
                    'chip-in' => array(
                        'status' => esc_attr($set_status)
                    )
                ));

                $this->set_order_status( $order_id, $set_status );
 
                wp_redirect(add_query_arg(array(
                    'order_id' => $order_id,
                    'status'   => "failure"
                ), site_url('checkout/thank-you')));

                exit;
                        
            endif;
        
        endif;

    }

    /**
     * Check if current order is using chip-in and will be redirected to chip-in payment channel options
     * Hooked via action sejoli/thank-you/render, priority 100
     * @since   1.0.0
     * @param   array  $order Order data
     * @return  void
     */
    public function check_for_redirect( array $order ) {

        extract( $this->get_setup_values() );

        if(
            isset( $order['payment_info']['bank'] ) &&
            'chip-in' === $order['payment_info']['bank']
        ) :

            if( 'on-hold' === $order['status'] ) :
                 
                $this->prepare_chip_in_data( $order );

            elseif( in_array( $order['status'], array( 'refunded', 'cancelled' ) ) ) :

                $title = __('Order telah dibatalkan', 'sejoli-chip-in');
                require 'template/checkout/order-cancelled.php';

            elseif( in_array( $order['status'], array( 'completed' ) ) ) :

                $title = __('Order selesai', 'sejoli-chip-in');
                require 'template/checkout/order-completed.php';

            else :

                $title = __('Order sudah diproses', 'sejoli-chip-in');
                require 'template/checkout/order-processed.php';

            endif;

            exit;

        endif;
    
    }

    /**
     * Get email content from given template
     * @since   1.0.0
     * @param   string      $filename   The filename of notification
     * @param   string      $media      Notification media, default will be email
     * @param   null|array  $args       Parsing variables
     * @return  null|string
     */
    function chip_in_get_notification_content( $filename, $media = 'email', $vars = NULL ) {
        
        $content    = NULL;
        $email_file = plugin_dir_path( __FILE__ ) . '/template/'.$media.'/' . $filename . '.php';

        if( file_exists( $email_file ) ) :

            if( is_array( $vars ) ) :
                extract( $vars );
            endif;

            ob_start();
           
            require $email_file;
            $content = ob_get_contents();
           
            ob_end_clean();
            
        endif;

        return $content;

    }

    /**
     * Display payment instruction in notification
     * @since   1.0.0
     * @param   array    $invoice_data
     * @param   string   $media email,whatsapp,sms
     * @return  string
     */
    public function display_payment_instruction( $invoice_data, $media = 'email' ) {
        
        if( 'on-hold' !== $invoice_data['order_data']['status'] ) :

            return;

        endif;

        $content = $this->chip_in_get_notification_content(
                        'chip-in',
                        $media,
                        array(
                            'order' => $invoice_data['order_data']
                        )
                    );

        return $content;
    
    }

    /**
     * Display simple payment instruction in notification
     * @since   1.0.0
     * @param   array    $invoice_data
     * @param   string   $media
     * @return  string
     */
    public function display_simple_payment_instruction( $invoice_data, $media = 'email' ) {

        if( 'on-hold' !== $invoice_data['order_data']['status'] ) :

            return;

        endif;

        $content = __('via Chip In', 'sejoli-chip-in');

        return $content;

    }

    /**
     * Set payment info to order data
     * @since   1.0.0
     * @param   array $order_data
     * @return  array
     */
    public function set_payment_info( array $order_data ) {

        $trans_data = [
            'bank' => 'chip-in'
        ];

        return $trans_data;

    }

    /**
     * Get Due Timestamp
     * @since   1.0.0
     * @return  time
     */
    public function get_due_timestamp() {

        $due_strict_timing = carbon_get_theme_option('chip_in_due');
        
        if ( empty( $this->due_str_t ) ) {
            $due_strict_timing = 60;
        }

        return time() + ( absint ( $due_strict_timing ) * 60 );

    }

    /**
     * Get Timezone List
     * @since   1.0.0
     * @return  time
     */
    private function get_timezone_list() {

        $list_time_zones      = DateTimeZone::listIdentifiers( DateTimeZone::ALL );
        $formatted_time_zones = array();

        foreach ( $list_time_zones as $mtz ) :
            $formatted_time_zones[$mtz] = str_replace( "_"," ",$mtz );;
        endforeach;
        
        return $formatted_time_zones;

    }

}