<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once('config.php');
//require_once('vendor/autoload.php');

//未使用 Composer，可手动引入init.php
require_once('vendor/stripe/init.php');

\Stripe\Stripe::setApiKey($stripe_secret_key);

if ($_POST['action'] == 'verify') {
    $headers = [
        'Content-Type: application/json'
    ];

    //access_token
    $params = [
        'client_id'    => $plaid_client,
        'secret'       => $plaid_secret,
        'public_token' => $_POST['token'],
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://sandbox.plaid.com/item/public_token/exchange");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 80);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if(!$result = curl_exec($ch)) {
       trigger_error(curl_error($ch));
    }
    curl_close($ch);

    $result = json_decode($result, true);

    //stripe_bank_account_token
    $params = [
        'client_id'    => $plaid_client,
        'secret'       => $plaid_secret,
        'access_token' => $result['access_token'],
        'account_id'   => $_POST['account_id'],
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://sandbox.plaid.com/processor/stripe/bank_account_token/create");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 80);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if(!$result = curl_exec($ch)) {
       trigger_error(curl_error($ch));
    }
    curl_close($ch);
    $result = json_decode($result, true);

    //创建一个Customer
    $customer = \Stripe\Customer::create([
        'source'      => $result['stripe_bank_account_token'],
        'description' => 'description for client',
        'email'       => 'email@email.com',
    ]);

    //写session，如返回No such token，请检查参数是否正确以及是否集成
    $_SESSION['customer'] = $customer->id;

    echo json_encode(['status' => $customer->id ? true : false]);
}

if ($_POST['action'] == 'pay') {
    $result = \Stripe\Charge::create([
        'amount'      => $_POST['amount'] * 100,  //精确到分的整数
        'currency'    => 'usd',
        'description' => 'Example charge',
        'customer'    => $_SESSION['customer'],
    ]);

    echo '<pre>';
    print_r($result);
    exit();

    $arr = array(
        'name'        => $result->source->account_holder_name,
        'acount_no'   => $result->source->last4,
        'routing_no'  => $result->source->routing_number,
        'bank_status' => $result->source->status,
        'id'          => $result->source->id,
        'customer'    => $result->source->customer,
    );
    echo json_encode($arr);
}
exit();
