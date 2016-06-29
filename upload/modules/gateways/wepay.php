<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see http://docs.whmcs.com/Gateway_Module_Meta_Data_Parameters
 *
 * @return array
 */
function wepay_MetaData()
{
    return array(
        'DisplayName'                 => 'WePay',
        'APIVersion'                  => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => false,
        'TokenisedStorage'            => true,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function wepay_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type'  => 'System',
            'Value' => 'WePay',
        ),
        // a text field type allows for single line text input
        'clientID' => array(
            'FriendlyName' => 'Client ID',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => '',
        ),
        // a password field type allows for masked text input
        'clientSecret' => array(
            'FriendlyName' => 'Client Secret',
            'Type'         => 'password',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => '',
        ),
        // a text field type allows for single line text input
        'accountID' => array(
            'FriendlyName' => 'Account ID',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => '',
        ),
        // a text field type allows for single line text input
        'accessToken' => array(
            'FriendlyName' => 'Access Token',
            'Type'         => 'text',
            'Size'         => '80',
            'Default'      => '',
            'Description'  => '',
        ),
        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type'         => 'yesno',
            'Description'  => 'Tick to enable test mode',
        ),
    );
}

/**
 * Capture payment.
 *
 * Called when a payment is to be processed and captured.
 *
 * The card cvv number will only be present for the initial card holder present
 * transactions. Automated recurring capture attempts will not provide it.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see http://docs.whmcs.com/Payment_Gateway_Module_Parameters
 *
 * @return array Transaction response status
 */
function wepay_capture($params)
{
    if ($params['testMode'] == 'on') {
        $url = 'https://stage.wepayapi.com/v2/';
    } else {
        $url = 'https://wepayapi.com/v2/';
    }

    $data = [
        'account_id' => $params['accountID'],
        'short_description' => $params['description'],
        'type' => 'service',
        'amount' => $params['amount'],
        'currency' => $params['currency'],
        'fee' => [
            'app_fee' => 0,
            'fee_payer' => 'payee',
        ],
        'payment_method' => [
            'type' => 'credit_card',
            'credit_card' => [
                'id' => $params['gatewayid'],
            ],
        ],
    ];

    $ch = curl_init($url . 'checkout/create');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $params['accessToken'],
        'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] ?: 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
        'Content-Type: application/json',
    ]);
    $rawdata = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($rawdata);
    if (isset($result->state) && in_array($result->state, ['authorized', 'captured'])) {
        return [
            'status' => 'success',
            'transid' => $result->checkout_id,
            'rawdata' => $rawdata,
        ];
    } else {
        return [
            'status' => 'declined',
            'rawdata' => $rawdata,
        ];
    }
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see http://docs.whmcs.com/Payment_Gateway_Module_Parameters
 *
 * @return array Transaction response status
 */
function wepay_refund($params)
{
    if ($params['testMode'] == 'on') {
        $url = 'https://stage.wepayapi.com/v2/';
    } else {
        $url = 'https://wepayapi.com/v2/';
    }

    $data = [
        'checkout_id' => $params['transid'],
        'refund_reason' => 'None specified',
    ];

    if ($params['amount']) {
        $data['amount'] = $params['amount'];
    }

    $ch = curl_init($url . 'checkout/refund');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $params['accessToken'],
        'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] ?: 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
        'Content-Type: application/json',
    ]);
    $rawdata = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($rawdata);
    if (isset($result->state) && $result->state == 'refunded') {
        return [
            'status' => 'success',
            'rawdata' => $rawdata,
        ];
    } else {
        return [
            'status' => 'declined',
            'rawdata' => $rawdata,
        ];
    }
}

/**
 * Store credit card.
 *
 * Called when a token is requested for a transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see http://docs.whmcs.com/Payment_Gateway_Module_Parameters
 *
 * @return array Transaction response status
 */
function wepay_storeremote($params)
{
    if ($params['gatewayid']) {
        return [
            'status' => 'success',
            'gatewayid' => $params['gatewayid'],
        ];
    }

    if (!$params['cardnum']) {
        return [
            'status' => 'success',
        ];
    }

    if ($params['testMode'] == 'on') {
        $url = 'https://stage.wepayapi.com/v2/';
    } else {
        $url = 'https://wepayapi.com/v2/';
    }

    $data = [
        'client_id' => $params['clientID'],
        'user_name' => $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'] ,
        'email' => $params['clientdetails']['email'],
        'cc_number' => $params['cardnum'],
        'cvv' => $params['cardcvv'],
        'expiration_month' => substr($params['cardexp'], 0, 2),
        'expiration_year' => '20' . substr($params['cardexp'], 2, 2),
        'address' => [
            'address1' => $params['clientdetails']['address1'],
            'city' => $params['clientdetails']['city'],
            'state' => $params['clientdetails']['state'],
            'country' => $params['clientdetails']['country'],
            'postcode' => $params['clientdetails']['postcode'],
            'zip' => $params['clientdetails']['postcode'],
        ],
    ];

    $ch = curl_init($url . 'credit_card/create');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $params['accessToken'],
        'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] ?: 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
        'Content-Type: application/json',
    ]);
    $rawdata = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($rawdata);
    if (isset($result->credit_card_id)) {
        return [
            'status' => 'success',
            'gatewayid' => $result->credit_card_id,
            'rawdata' => $rawdata,
        ];
    } else {
        return [
            'status' => 'failed',
            'rawdata' => $rawdata,
        ];
    }
}

