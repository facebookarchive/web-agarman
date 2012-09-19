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

// Database connection information and credentials for PDO
$dbms = 'mysql';
$dbhost = 'localhost';
$dbname = 'cc';
$dbuser = 'dbappuser_33a';
$dbpass = 'akunutizehu';

// an array of host/port pairs where memcache servers live
$memcacheServers = array(
  array('localhost', 11211),
//array('localhost', 11212),
//array('otherhost', 11211), //...
);

// some global salt strings
$uidsalt = '6e3332d6';
$wordsalt = '4996b3f9';

// Latency for submitting scores, in seconds
$timeFudge = 10;

// Default letterset spec
$defaultSpec = 7;

// first cut scoring rules
// TODO generalize better, s.t. Match has-a Scorer
$scoringRules = array(
  'default' => array(
    'letters' => array( // score for each letter
      'e' => 1,
      'a' => 1,
      'o' => 1,
      't' => 1,
      'i' => 1,
      'n' => 1,
      'r' => 1,
      's' => 1,
      'l' => 1,
      'u' => 1,

      'd' => 2,
      'g' => 2,

      'b' => 3,
      'c' => 3,
      'm' => 3,
      'p' => 3,

      'f' => 4,
      'h' => 4,
      'v' => 4,
      'y' => 4,
      'w' => 4,

      'k' => 5,

      'j' => 8,
      'x' => 8,

      'q' => 10,
      'z' => 10,
    ),
    'lengths' => array ( // multiplier for word length
      0 => 1,
      5 => 2,
      7 => 3,
      9 => 4,
      11 => 5,
    )
  )
);

//TAG:AUTH
$appID = '104191753063425';
$appSecret = '335eb74b4d3dfb3643bb8f51ec3c0038';
$appURL = '//apps.facebook.com/agarman/';
$domain = 'freeeel.com';
$appName = 'agarman';
$serverURL = 'http://freeeel.com/nagaram/index.php';

//TAG:PAYMENTS
$payrate = 0.7; // how much money you get for each credit spent

$catalog = array( // an array of things to sell in the game
  '1o' => array(
    'title' => '1 Agar Buck',
    'description' => 'Use Agar Bucks to buy great power-ups in AGAR MAN',
    // Price must be denominated in credits.
    'price' => 3,
    'image_url' => "http://$domain/nagaram/og/bucks.png",
    'item_name' => 'moneys',
    'item_quantity' => 1,
  ),
  '1a' => array(
    'title' => '3 Agar Bucks',
    'description' => 'Use Agar Bucks to buy great power-ups in AGAR MAN',
    // Price must be denominated in credits.
    'price' => 6,
    'image_url' => "http://$domain/nagaram/og/bucks.png",
    'item_name' => 'moneys',
    'item_quantity' => 3,
  ),
  '1b' => array(
    'title' => '15 Agar Bucks',
    'description' => 'Use Agar Bucks to buy great power-ups in AGAR MAN',
    // Price must be denominated in credits.
    'price' => 25,
    'image_url' => "http://$domain/nagaram/og/bucks.png",
    'item_name' => 'moneys',
    'item_quantity' => 15,
  ),
);
