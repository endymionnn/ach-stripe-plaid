<?php
session_start();
require_once('config.php');

//需要支付的金额
$amount = rand(100, 999);

//用session模拟保存customer id
if (!$_SESSION['customer']) {
    //获取 link token
    $curl_post = json_encode([
        'client_id' => $plaid_client,
        'secret'    => $plaid_secret,
        'user'      => [
            'client_user_id' => '1111111111', //must be string,
        ],
        'client_name'     => 'Stripe/Plaid Test',
        'products'        => ['auth'],
        'country_codes'   => ['US'],
        'language'        => 'en',
    ]);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_URL, 'https://sandbox.plaid.com/link/token/create');
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(
        $curl,
        CURLOPT_HTTPHEADER,
        array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($curl_post))
    );
    $link_token = json_decode(curl_exec($curl), true);
    curl_close($curl);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<title>Example</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/js/bootstrap.min.js"></script>
</head>
<body>
<div class="col-md-6">
    <h2>ACCOUNT VERIFICATION FORM</h2>
    <div class="form-group" id="verify" <?php if ($_SESSION['customer']) { ?> style="display: none;" <?php } ?> >
        <div class="col-md-12">
            <button type="button" class="form-control btn btn-primary" id='linkButton'>Verify Bank Account</button>
        </div>
    </div>
    <div id="pay" <?php if (!$_SESSION['customer']) { ?> style="display: none;" <?php } ?>>
        <div class="col-md-6 cvc required">
            <label class="control-label">Amount to Pay</label>
            <div class="left-inner-addon">
                <input type="text" disabled="disabled" class="form-control"  id="amount" placeholder="Price"  value="<?php echo $amount; ?>" />
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-md-12">
            <button id="pay_now" class="form-control btn btn-primary" type="button" onclick="pay()">Pay Now</button>
        </div>
    </div>
</div>

<?php
if (!$_SESSION['customer']) {
?>
<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
<script>
var linkHandler = Plaid.create({
    env: 'sandbox',
    clientName: 'Stripe/Plaid Test',
    key: "<?php echo $plaid_secret; ?>",
    product: ['auth'],
    selectAccount: true,
    token: '<?php echo $link_token["link_token"]; ?>',
    onSuccess: function(public_token, metadata) {
        console.log(public_token);
        console.log(metadata);
        $.ajax({
            type: "POST",
            url: "ajax.php",
            data: {
                'action': 'verify',
                'token': public_token,
                'account_id': metadata.account_id,
            },
            cache: false,
            dataType: "json",
            success: function(result) {
                console.log(result);
                if (result.status) {
                    $("#verify").hide();
                    $("#pay").show();
                }
            }
        });
    },
    onExit: function(err, metadata) {

    },
});

document.getElementById('linkButton').onclick = function() {
    linkHandler.open();
};
</script>
<?php } ?>

<script type="text/javascript">
function pay() {
    var amount = $("#amount").val();
    $.ajax({
        type: "POST",
        url: "ajax.php",
        data: {
            'action': 'pay',
            'amount': amount,
        },
        cache: false,
        dataType: "json",
        success: function(result) {
            console.log(result);
            alert('success');
        }
    });
}
</script>
</body>
</html>
