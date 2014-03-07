<?php

class cardstream_form {

    var $code, $title, $description, $enabled;

    // class constructor
    function cardstream_form() {
	
	global $order;

	$this->code = 'cardstream_form';
	$this->version = "Cardstream";

	// Perform error checking of module's configuration ////////////////////////////////////////
	$critical_config_problem = false;

	$this->form_action_url = "https://gateway.cardstream.com/hosted/";

	$cardstream_form_config_messages = '';

	$cardstream_form_config_messages .= '<fieldset style="background: #d0d0d0; margin-bottom: 1.5em"><legend style="font-size: 1.2em; font-weight: bold">Module Version Information</legend>';
	$cardstream_form_config_messages .= '<p>File Version: ' . $this->version;
	$this->description = '';

	$this->title = "Cardstream";


	$this->enabled = ((MODULE_PAYMENT_CARDSTREAM_FORM_STATUS == 'True') ? true : false);
	$this->sort_order = MODULE_PAYMENT_CARDSTREAM_FORM_SORT_ORDER;

	if ((int) MODULE_PAYMENT_CARDSTREAM_FORM_ORDER_STATUS_ID > 0) {
	    $this->order_status = MODULE_PAYMENT_CARDSTREAM_FORM_ORDER_STATUS_ID;
	}

	if (is_object($order)) {
	    $this->update_status();
	}

	}

	function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_CARDSTREAM_FORM_ZONE > 0) ) {
        $check_flag = false;
        $check = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_CARDSTREAM_FORM_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
        while (!$check->EOF) {
          if ($check->fields['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
          $check->MoveNext();
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

	function javascript_validation() {
	    return false;
	}

	function selection() {
	    return array('id' => $this->code,
		'module' => $this->title);
	}

	function pre_confirmation_check() {
	    return false;
	}

	function confirmation() {
	    return false;
	}

	function process_button() {
	    global $order, $currencies, $currency, $customer_id, $cart, $products, $contents;

	    $amount = round($order->info['total']*100);

		$transU = md5(mktime());
		$retURL = tep_href_link(FILENAME_CHECKOUT_PROCESS, tep_session_name() . '=' . tep_session_id(), 'SSL', false);
	    $process_button_string = '';

		$fields = array(
			'transactionUnique' => $transU,
			'amount' => $amount,
			'merchantID' => MODULE_PAYMENT_CARDSTREAM_FORM_MERCHANT_ID,
			'countryCode' => MODULE_PAYMENT_CARDSTREAM_FORM_COUNTRY_ID,
			'currencyCode' => MODULE_PAYMENT_CARDSTREAM_FORM_CURRENCY_ID,
			'redirectURL' => $retURL,
			'customerName' => $order->customer['firstname'].' '.$order->customer['lastname'],
			'customerAddress' => $order->customer['street_address']."\n".$order->customer['city']."\n".$order->customer['state'],
			'customerPostcode' => $order->customer['postcode'],
			'customerPhone' => $order->customer['telephone'],
			'customerEmail' => $order->customer['email_address']
		);

		ksort($fields);
		$fields['signature'] = hash('SHA512',http_build_query($fields, '', '&').MODULE_PAYMENT_CARDSTREAM_FORM_PRE_SHARED_KEY).'|'.implode(',',array_keys($fields));

		foreach($fields as $k => $v){
			$process_button_string .= tep_draw_hidden_field($k, $v);
		}

	    return $process_button_string;
	}

	function after_order_create($zf_order_id)
	{
		global $order, $currencies, $currency, $customer_id, $cart, $products, $contents;
		// Save response from cardstream in the database
		$cardstream_form_response_array = array(
			'transid' => $_POST['transactionUnique'],
			'zen_order_id' => $zf_order_id,
			'received' => $_POST['amountReceived'],
			);
		zen_db_perform("cardstream_form", $cardstream_form_response_array);
	}

	function get_error(){
	    return array('title' => "Payment Error",
		 'error' => "There has been an error with your payment. Please try again.");
	}

	function admin_notification($zf_order_id)
	{

		$sql = "
			SELECT
				*
			FROM
				cardstream_form
			WHERE
				zen_order_id = '" . $zf_order_id . "'";

		$cardstream_form_transaction_info = tep_db_query($sql);

		require(DIR_FS_CATALOG. DIR_WS_MODULES .
			'payment/cardstream_form/cardstream_form_admin_notification.php');

		return $output;
	}
	
	function before_process() {
	    global  $messageStack,$order,$code;

	    $amount = round($order->info['total']*100);

	    if (($_POST["responseCode"] != "0") || ($_POST["amountReceived"] != $amount)) {
		    //$errorcode = "Payment Failed. Please try again.";

		    tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . (tep_not_null($error) ? '&error=' . $error : ''), 'SSL'));
	    }

	}

	function after_process() {
	    return false;
	}

	function check() {
	    if (!isset($this->_check)) {
		$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_CARDSTREAM_FORM_STATUS'");
		$this->_check = tep_db_num_rows($check_query);
	    }
	    return $this->_check;
	}

	function install() {
	    // General Config Options
	    $background_colour = '#d0d0d0';
	    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('</b><fieldset style=\"background: " . $background_colour . "; margin-bottom: 1.5em;\"><legend style=\"font-size: 1.4em; font-weight: bold\">General Config</legend><b>Enable Cardstream Module', 'MODULE_PAYMENT_CARDSTREAM_FORM_STATUS', 'True', 'Do you want to accept Cardstream payments?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
	    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', 'MODULE_PAYMENT_CARDSTREAM_FORM_MERCHANT_ID', '', '', '2', '1', now())");
	    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Signature Key', 'MODULE_PAYMENT_CARDSTREAM_FORM_PRE_SHARED_KEY', '', '', '2', '1', now())");
	    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Front End Name', 'MODULE_PAYMENT_CARDSTREAM_FORM_CATALOG_TEXT_TITLE', '', '', '3', '1', now())");
	    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Currency ID', 'MODULE_PAYMENT_CARDSTREAM_FORM_CURRENCY_ID', '', '', '4', '1', now())");
	    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Country ID', 'MODULE_PAYMENT_CARDSTREAM_FORM_COUNTRY_ID', '', '', '5', '1', now())");
	    $background_colour = '#eee';
	}

	function remove() {
	    tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	function keys() {
	    return array(
		'MODULE_PAYMENT_CARDSTREAM_FORM_MERCHANT_ID',
		'MODULE_PAYMENT_CARDSTREAM_FORM_CURRENCY_ID',
		'MODULE_PAYMENT_CARDSTREAM_FORM_PRE_SHARED_KEY',
		'MODULE_PAYMENT_CARDSTREAM_FORM_CATALOG_TEXT_TITLE',
		'MODULE_PAYMENT_CARDSTREAM_FORM_COUNTRY_ID',
		'MODULE_PAYMENT_CARDSTREAM_FORM_STATUS'
	    );
	}

    }

?>