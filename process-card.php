<?php

// Note this line needs to change if you don't use Composer:
// require('connect-php-sdk/autoload.php');
require 'vendor/autoload.php';
require 'sheets.php';

// dotenv is used to read from the '.env' file created for credentials
$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

# Replace these values. You probably want to start with your Sandbox credentials
# to start: https://docs.connect.squareup.com/articles/using-sandbox/

# The access token to use in all Connect API requests. Use your *sandbox* access
# token if you're just testing things out.
$access_token = ($_ENV["USE_PROD"] == 'true')  ?  $_ENV["PROD_ACCESS_TOKEN"]
                                               :  $_ENV["SANDBOX_ACCESS_TOKEN"];
$location_id =  ($_ENV["USE_PROD"] == 'true')  ?  $_ENV["PROD_LOCATION_ID"]
                                               :  $_ENV["SANDBOX_LOCATION_ID"];

// Initialize the authorization for Square
\SquareConnect\Configuration::getDefaultConfiguration()->setAccessToken($access_token);

# Helps ensure this code has been reached via form submission
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
  error_log("Received a non-POST request");
  echo "Request not allowed";
  http_response_code(405);
  return;
}

# Fail if the card form didn't send a value for `nonce` to the server
$nonce = $_POST['nonce'];
if (is_null($nonce)) {
  echo "Invalid card data";
  http_response_code(422);
  return;
}

$transactions_api = new \SquareConnect\Api\TransactionsApi();

# Creating a new Charge
$chargeBody = new \SquareConnect\ChargeRequest() ;

// Create an Address object with billing info
$billingAddress = new \SquareConnect\Address() ;
$billingAddress->setAddressLine1($_POST['b-street-address']);
$billingAddress->setAddressLine2($_POST['b-unit-number']);
$billingAddress->setLocality($_POST['b-city']);
$billingAddress->setAdministrativeDistrictLevel1($_POST['b-province']);
$billingAddress->setPostalCode($_POST['b-postal-code']);
$billingAddress->setCountry($_POST['b-country']);

$billingAddress->setFirstName($_POST['b-first-name']);
$billingAddress->setLastName($_POST['b-last-name']);

// Set the customer info
$chargeBody->setBuyerEmailAddress($_POST['b-email']);
$chargeBody->setBillingAddress($billingAddress);

$idempotencyKey = uniqid();

// Set the charge amount to 103 CAD
$chargeAmount =  new \SquareConnect\Money() ;
$chargeAmount->setAmount(10300);
$chargeAmount->setCurrency("CAD");

// Set the payment information
$chargeBody->setIdempotencyKey($idempotencyKey);
$chargeBody->setCardNonce($nonce);
$chargeBody->setAmount($chargeAmount);

$chargeBody->setNote("CSSA-AEI 101 Week Kit");

# To learn more about splitting transactions with additional recipients,
# see the Transactions API documentation on our [developer site]
# (https://docs.connect.squareup.com/payments/transactions/overview#mpt-overview).
$request_body = array (
  "card_nonce" => $nonce,
  # Monetary amounts are specified in the smallest unit of the applicable currency.
  # This amount is in cents. It's also hard-coded for $1.00, which isn't very useful.
  "amount_money" => array (
    "amount" => 10000,
    "currency" => "CAD"
  ),
  # Every payment you process with the SDK must have a unique idempotency key.
  # If you're unsure whether a particular payment succeeded, you can reattempt
  # it with the same idempotency key without worrying about double charging
  # the buyer.
  "idempotency_key" => uniqid()
);

# The SDK throws an exception if a Connect endpoint responds with anything besides
# a 200-level HTTP code. This block catches any exceptions that occur from the request.
try {
  $result = $transactions_api->charge($location_id, $chargeBody);
  echo "<pre>";
  print_r($result);
  echo "</pre>";
  echo print_r($_POST);
  $values = [
	[
		$_POST['first-name'],
		$_POST['last-name'],
		$_POST['student-num'],
		$_POST['email'],
		$_POST['diet'],
		$_POST['disabled'],
		$_POST['health-num'],
		$_POST['emerg'],
		$_POST['emerg-num'],
	],
  ];
  $valueInputOption = 'RAW';
  $range = "Sheet2";
  $body = new Google_Service_Sheets_ValueRange([
	  'values' => $values
  ]);
  $params = [
	      'valueInputOption' => $valueInputOption
  ];
  $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

} catch (\SquareConnect\ApiException $e) {
  echo "Caught exception!<br/>";
  print_r("<strong>Response body:</strong><br/>");
  echo "<pre>"; var_dump($e->getResponseBody()); echo "</pre>";
  echo "<br/><strong>Response headers:</strong><br/>";
  echo "<pre>"; var_dump($e->getResponseHeaders()); echo "</pre>";
}
