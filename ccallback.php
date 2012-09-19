<?php
/**
* Copyright 2012 Facebook, Inc.
*
* Licensed under the Apache License, Version 2.0 (the "License"); you may
* not use this file except in compliance with the License. You may obtain
* a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
* WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
* License for the specific language governing permissions and limitations
* under the License.
*/

require_once('./config.php');
require_once('./Store.php');
$app_secret = $appSecret;

// Validate request is from Facebook and parse contents for use.
$request = parse_signed_request($_POST['signed_request'], $app_secret);

// Get request type.
// Two types:
//   1. payments_get_items.
//   2. payments_status_update.

$request_type = $_POST['method'];
// OK to die on illegal inputs - ignore cheaters

// Setup response.
$response = '';

if ($request_type == 'payments_get_items') {
  // Get order info from Pay Dialog's order_info.
  // Assumes order_info is a JSON encoded string.
  $order_info = json_decode($request['credits']['order_info'], true);

  // Get item id.
  $item_id = $order_info['item_id'];

  // Perform the item lookup based on Pay Dialog's order_info.
  $item = $GLOBALS['catalog'][$item_id];
  $item['item_id'] = $item_id;

  // Construct response.
  $response = array(
    'content' => array(
      0 => $item,
    ),
    'method' => $request_type,
  );
  // Response must be JSON encoded.
  $response = json_encode($response);
} else if ($request_type == "payments_status_update") {
  // Get order details.
  $order_details = json_decode($request['credits']['order_details'], true);

  // Determine if this is an earned currency order.
  $item_data = json_decode($order_details['items'][0]['data'], true);
  $earned_currency_order = (isset($item_data['modified'])) ?
                             $item_data['modified'] : null;

  // Get order status.
  $current_order_status = $order_details['status'];

  if ($current_order_status == 'placed') {
    // Fulfill order based on $order_details - we only have one item we sell,
    // so we'll just credit the user one in-game currency unless...

    if ($earned_currency_order) {
      // Fulfill order based on the information below...
      // URL to the application's currency webpage.
      $product = $earned_currency_order['product'];
      // Title of the application currency webpage.
      $product_title = $earned_currency_order['product_title'];
      // Amount of application currency to deposit.
      $product_amount = $earned_currency_order['product_amount'];
      // If the order is settled, the developer will receive this
      // amount of credits as payment.
      $credits_amount = $earned_currency_order['credits_amount'];

      // We're giving out our virtual currency for all offers
      $product_name = $GLOBALS['catalog']['1a']['item_name'];
    } else {
      $request_item = $order_details['items'][0];
      $sku = $GLOBALS['catalog'][$request_item['item_id']];
      $product_amount = $sku['item_quantity'];
      $product_name = $sku['item_name'];
      $credits_amount = $request_item['price'] * $GLOBALS['payrate'];
    }

    $oldBalance = Store::get(
      Store::META, $order_details['receiver'], $product_name
    );
    Store::set(Store::META, $order_details['receiver'], $product_name,
               $oldBalance + $product_amount);
    Store::set(Store::META, 'ourCut', 'creds',
               Store::get(Store::META, 'ourCut', 'creds') + $credits_amount
              );

    $next_order_status = 'settled';

    // Construct response.
    $response = array(
                  'content' => array(
                                 'status' => $next_order_status,
                                 'order_id' => $order_details['order_id'],
                               ),
                  'method' => $request_type,
                );
    // Response must be JSON encoded.
    $response = json_encode($response);

  } else if ($current_order_status == 'disputed') {
    // 1. Track disputed item orders.
    // 2. Investigate user's dispute and resolve by settling or refunding the order.
    // 3. Update the order status asychronously using Graph API.

  } else if ($current_order_status == 'refunded') {
    // Track refunded item orders initiated by Facebook. No need to respond.

  } else {
    // Track other order statuses.

  }
}

// Send response.
echo $response;

// These methods are documented here:
// https://developers.facebook.com/docs/authentication/signed_request/
function parse_signed_request($signed_request, $secret) {
  list($encoded_sig, $payload) = explode('.', $signed_request, 2);

  // decode the data
  $sig = base64_url_decode($encoded_sig);
  $data = json_decode(base64_url_decode($payload), true);

  if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
    error_log('Unknown algorithm. Expected HMAC-SHA256');
    return null;
  }

  // check sig
  $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
  if ($sig !== $expected_sig) {
    error_log('Bad Signed JSON signature!');
    return null;
  }

  return $data;
}

function base64_url_decode($input) {
  return base64_decode(strtr($input, '-_', '+/'));
}
