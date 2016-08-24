<?php

/**
 * Plugin Name: NextPay Gateway For Pro-VIP
 * Created by: NextPay.ir
 * Author: FreezeMan
 * ID: @FreezeMan
 * Date: 7/29/16
 * Time: 5:05 PM
 * Website: NextPay.ir
 * Email: freezeman.0098@gmail.com
 * @copyright 2016
 * @package NextPay_Gateway
 * @version 1.0
 * Description: This plugin lets you use NextPay gateway in pro-vip wp plugin.
 * Plugin URI: http://www.nextpay.ir
 * Author URI: http://www.nextpay.ir
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html.
 */
defined('ABSPATH') or exit;

if (!function_exists('init_Nextpay_gateway_pv_class')) {
    add_action('plugins_loaded', 'init_Nextpay_gateway_pv_class');

    function init_Nextpay_gateway_pv_class()
    {
        add_filter('pro_vip_currencies_list', 'currencies_check');

        function currencies_check($list)
        {
            if (!in_array('IRT', $list)) {
                $list['IRT'] = [
                    'name'   => 'تومان ایران',
                    'symbol' => 'تومان',
                ];
            }

            if (!in_array('IRR', $list)) {
                $list['IRR'] = [
                    'name'   => 'ریال ایران',
                    'symbol' => 'ریال',
                ];
            }

            return $list;
        }

        if (class_exists('Pro_VIP_Payment_Gateway') && !class_exists('Pro_VIP_Nextpay_Gateway')) {
            class Pro_VIP_Nextpay_Gateway extends Pro_VIP_Payment_Gateway
            {
                public $id = 'Nextpay',
                        $settings = [],
                        $frontendLabel = 'نکست پی',
                        $adminLabel = 'نکست پی';

                public function __construct()
                {
                    parent::__construct();
                }

                public function beforePayment(Pro_VIP_Payment $payment)
                {
                    $Api_key = $this->settings['api_key']; //Required
                    $Amount = intval($payment->price); // Required
                    $orderId = $payment->paymentId; // Required
                    $Description = 'پرداخت فاکتور به شماره ی'.$orderId; // Required
                    $CallbackURL = add_query_arg('order', $orderId, $this->getReturnUrl()); // $this->getReturnUrl();
                    //$currency = $order->get_order_currency();

                    if (pvGetOption('currency') === 'IRR') {
                        $Amount /= 10;
                    }

                    include_once "nextpay_payment.php";

                    $nextpay = Nextpay_Payment(array(
                        "api_key"=>$Api_key,
                        "amount"=>$Amount,
                        "callback_uri"=>$CallbackURL));

                    $res = $nextpay->token();

                    if (intval($res->code) == -1) {
                        $payment->key = $orderId;
                        $payment->user = get_current_user_id();
                        $payment->save();

                        //$payment_url = 'https://www.zarinpal.com/pg/StartPay/';

                        //header("Location: $payment_url".$res->Authority);
                        $nextpay->send($res->trans_id);
                    } else {
                        pvAddNotice('خطا در هنگام اتصال به نکست پی.');
                        return;
                    }
                }

                public function afterPayment()
                {
                    if (isset($_GET['order'])) {
                        $orderId = $_GET['order'];
                    } else {
                        $orderId = 0;
                    }

                    if ($orderId) {
                        $payment = new Pro_VIP_Payment($orderId);
                        $Api_key = $this->settings['api_key']; //Required
                        $Amount = intval($payment->price); //  - ریال به مبلغ Required
                        $trans_id = isset($_POST['trans_id'])?$_POST['trans_id'] : false ;

                        if (pvGetOption('currency') === 'IRR') {
                            $amount /= 10;
                        }

                        if ($trans_id) {

                            include_once "nextpay_payment.php";

                            $parameters = array
                            (
                                'api_key'	=> $Api_key,
                                'trans_id' 	=> $trans_id,
                                'amount'	=> $Amount,
                            );

                            $nextpay = Nextpay_Payment();
                            $Result = intval($nextpay->verify_request($parameters));

                            if ($Result == 0) {
                                pvAddNotice('پرداخت شما با موفقیت انجام شد. کد پیگیری: '.$orderId, 'success');
                                $payment->status = 'publish';
                                $payment->save();

                                $this->paymentComplete($payment);
                            } else {
                                pvAddNotice('خطایی به هنگام پرداخت پیش آمده. کد خطا عبارت است از :'.$Result.' . برای آگاهی از دلیل خطا کد آن را به نکست پی ارائه نمایید.');
                                $this->paymentFailed($payment);

                                return false;
                            }
                        } else {
                            pvAddNotice('به نظر می رسد عملیات پرداخت توسط شما لغو گردیده، اگر چنین نیست مجددا اقدام به پرداخت فاکتور نمایید.');
                            $this->paymentFailed($payment);

                            return false;
                        }
                    }
                }

                public function adminSettings(PV_Framework_Form_Builder $form)
                {
                    $form->textfield('api_key')->label('کلید API');
                }
            }

            Pro_VIP_Payment_Gateway::registerGateway('Pro_VIP_Nextpay_Gateway');
        }
    }
}