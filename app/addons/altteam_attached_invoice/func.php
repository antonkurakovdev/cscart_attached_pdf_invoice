<?php

/*****************************************************************************
 * This is a commercial software, only users who have purchased a  valid
 * license and accepts the terms of the License Agreement can install and use  
 * this program.
 *----------------------------------------------------------------------------
 * @copyright  LCC Alt-team: http://www.alt-team.com
 * @module     "Alt-team: Attached invoice"
 * @version    4.2.x and higher
 * @license    http://www.alt-team.com/addons-license-agreement.html
 ****************************************************************************/

use Tygh\Registry;
use Tygh\Mailer;
use Tygh\Pdf;

if ( !defined('BOOTSTRAP') ) { die('Access denied'); }

// [HOOKS]
/**
 *
 * Attached pdf and send invoice email for customer. Disabled default send email order notify for customer.
 *
 **/

function fn_altteam_attached_invoice_send_order_notification(&$order_info, &$edp_data, &$force_notification, &$notified, &$send_order_notification)
{

    $take_surcharge_from_vendor = false;
    if (fn_allowed_for('MULTIVENDOR')) {
        $take_surcharge_from_vendor = fn_take_payment_surcharge_from_vendor($order_info['products']);
    }

    if (!$send_order_notification) {
        return true;
    }

    if (!is_array($force_notification)) {
        $force_notification = fn_get_notification_rules($force_notification, !$force_notification);
    }
    $order_statuses = fn_get_statuses(STATUSES_ORDER, array(), true, false, ($order_info['lang_code'] ? $order_info['lang_code'] : CART_LANGUAGE), $order_info['company_id']);
    $status_params = $order_statuses[$order_info['status']]['params'];

    $notify_user = isset($force_notification['C']) ? $force_notification['C'] : (!empty($status_params['notify']) && $status_params['notify'] == 'Y' ? true : false);

    if ($notify_user == true) {

        $notified[$order_info['order_id']][$order_info['status']] = true;

        $order_status = $order_statuses[$order_info['status']];
        $payment_method = fn_get_payment_data((!empty($order_info['payment_method']['payment_id']) ? $order_info['payment_method']['payment_id'] : 0), $order_info['order_id'], $order_info['lang_code']);
        $status_settings = $order_statuses[$order_info['status']]['params'];
        $profile_fields = fn_get_profile_fields('I', '', $order_info['lang_code']);
        $secondary_currency = '';

        list($shipments) = fn_get_shipments_info(array('order_id' => $order_info['order_id'], 'advanced_info' => true));
        $use_shipments = !fn_one_full_shipped($shipments);

        // restore secondary currency
        if (!empty($order_info['secondary_currency']) && Registry::get("currencies.{$order_info['secondary_currency']}")) {
            $secondary_currency = $order_info['secondary_currency'];
        }


        // Notify customer
        if ($notify_user == true) {

	        //	order data array
	        $_data = array(
	            'order_info' => $order_info,
	            'shipments' => $shipments,
	            'use_shipments' => $use_shipments,
	            'order_status' => $order_status,
	            'payment_method' => $payment_method,
	            'status_settings' => $status_settings,
	            'profile_fields' => $profile_fields,
	            'secondary_currency' => $secondary_currency,
	            'take_surcharge_from_vendor' => $take_surcharge_from_vendor
	        );

	        //	assign order data for create pdf
	        foreach ($_data as $k => $v) {
	            Registry::get('view')->assign($k, $v);
	        }

	        //	create html for pdf
	        // fn_disable_live_editor_mode();
	        $html = Registry::get('view')->displayMail('orders/print_invoice.tpl', false, 'C', $order_info['company_id'], $order_info['lang_code']);

	        //	pdf path + name
	        $pdf_name = 'var/files/' . $order_info['company_id'] . '/invoice/' . __('invoice') . '-' . $order_info['order_id'] . '.pdf';

	        //	html to pdf
			$temp_status = Pdf::render($html, $pdf_name, true);
			  
			//	for mailer attache
			$attachments[__('invoices') . '-' . $order_info['order_id'] . '.pdf'] = $pdf_name;
  
			//	send mail
            @Mailer::sendMail(array(
                'to' => $order_info['email'],
                'from' => 'company_orders_department',
                'data' => $_data,
                'tpl' => 'orders/order_notification.tpl',
                'company_id' => $order_info['company_id'],
                'attachments' => $attachments
            ), 'C', $order_info['lang_code']);

            //	remove pdf file
            fn_rm($pdf_name);
  
            if (!empty($edp_data)) {
                Mailer::sendMail(array(
                    'to' => $order_info['email'],
                    'from' => 'company_orders_department',
                    'data' => array(
                        'order_info' => $order_info,
                        'edp_data' => $edp_data,
                    ),
                    'tpl' => 'orders/edp_access.tpl',
                    'company_id' => $order_info['company_id'],
                ), 'C', $order_info['lang_code']);
            }
        }
  
        //	Disabled default customer notification
        $force_notification['C'] = false;

    }

}
// [/HOOKS]