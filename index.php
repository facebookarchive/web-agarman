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

// The main user-facing page plus REST endpoint
require_once('./Match.php');
require_once('./Store.php');
require_once('./LetterSet.php');
require_once('./config.php');

// Settings for the demo
// Turn various features on and off from here
$on = array(
  'auth' => true,
  'requests' => true,
  'feed' => true,
  'scores' => true,
  'achievements' => true,
  'payments' => true,
  'offers' => true,
);

//TAG: AUTH
// Code for decoding and parsing a signed request
// (we'll actually use the function built into the FB PHP SDK,
// but this helps explain what a signed request actually is)
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

//TAG:ACHIEVEMENTS
// Handle the case where the user wants to read an achievement
if(getParam('f')=='ra') { // don't need to user-validate, just fetch achievement
  require "Achievements.php";
  $name = getParam('n');
  echo(Achievements::getAchievement($name));
  exit(0);
}

$user = validatePlayer();

if(!$user) {
  $msg = "ERROR: Could not validate user";
  restReturn($msg);
  trigger_error($msg, E_USER_ERROR);
}

$params = $_REQUEST;

$functions = array (
  'i' => 'initGame',
  'h' => 'getWordHashes',
  's' => 'submitWordList',
  'c' => 'challengeOtherUser',
  'r' => 'getMyResult',
  'r' => 'respondToInvite',
  'f' => 'getFbRequests',
  'ra'=> 'getAchievement',
  'b' => 'getBalance',
);

$passToClient['uid'] =  $user;

// Which function should we dispatch? PHP 5.2 doesn't have lambdas. :(
$f = getParam('f');
if ($f) {
  $call = isset($functions[$f]) ? $functions[$f] : false;
} else {
  render_client();
  return 0; // all OK
}

if($call) {
  call_user_func($call, $user, $params);
  return 0;
} else {
  // we're being probed - TODO think about response
  trigger_error("Error: Invalid f ($params[f]) user $user", E_USER_WARNING);
  render_client();
}

// ------------ END of executed global code

function validatePlayer() { // Figure out who's playing
  global $passToClient;
// A simple auth system built to be replaced:
  if(!$GLOBALS['on']['auth']) {
    if(isset($_COOKIE['uid'])) {
      $uid = explode('.', $_COOKIE['uid']);
      if(count($uid) == 2) {
        $hashedID = md5($uid[0] . $GLOBALS['uidsalt']);
        if($hashedID == $uid[1]){
          // good - extend and return
          setcookie('uid', $_COOKIE['uid'], time() + 2592000, '/');
          return $uid[0];
        }
      }
    }
    // if we're here, we need to set a new UID
    $uid[0] = mt_rand();
    $uid[1] = md5($uid[0] . $GLOBALS['uidsalt']);
    $uidCookie = implode('.', $uid);
    setcookie('uid', $uidCookie, time() + 2592000, '/');
    return $uid[0];
  }
//TAG:AUTH
// If $on['auth'], instead use Facebook's server auth flow
  global $facebook;
  if($GLOBALS['on']['auth']) {
    require_once('./fb/facebook.php');
    // Initialize the Facebook PHP SDK
    $facebook = new Facebook(
      array (
        'appId' => $GLOBALS['appID'],
        'secret' => $GLOBALS['appSecret'],
      )
    );

    $sr = $facebook->getSignedRequest();
// Turn these on to get a dribble of the browser state/security interaction:
//  error_log('function [' . getparam('f') . ', sr: ]' . var_export($sr, true));
//  error_log(var_export($_REQUEST, true));
//  error_log(var_export($_COOKIE, true));

    if(isset($sr['user_id'])){
      $GLOBALS['fbid'] = $facebook->getUser();
      $GLOBALS['userToken'] = $facebook->getAccessToken();
//      error_log("using browser state for auth, user $GLOBALS[fbid], " .
//                "token $GLOBALS[userToken]");

      // work around Safari 3p cookie weirdness
      $rawRequest = getParam('signed_request');
      if($rawRequest) $passToClient['sr'] = "signed_request=$rawRequest";
    } else { // Send the user to the auth dialog
      authRedirect();
    }
    try {
      $fbProfile = $facebook->api( // Read some user details from the Graph API
        '/me?fields=first_name,gender,id,currency,locale',
        'GET'
      );
      $passToClient['profile'] = $fbProfile;
//TAG:REQUESTS
      if ($GLOBALS['on']['requests']) {
        $passToClient['appRequests'] = getRequests();
      }
    } catch (FacebookApiException $e) {
      // TODO deal with it
      throw $e;
    }
    return $GLOBALS['fbid'];
  }
}

// Emit some Javascript to load the authentication dialog.
// We can't just 302 because we're in an iframe.
function authRedirect() {
  global $facebook;
  $loginURL = $facebook->
    getLoginUrl(
      array('scope' => array('email', 'publish_actions'),
            'redirect_uri' => 'https:' . $GLOBALS['appURL'],
           )
    );
  echo "<script>window.top.location = '$loginURL';</script>";
//  error_log("emitted loginurl $loginURL");
  exit(0);
}

function getAppToken($refresh = false) {
    return $GLOBALS['appID'] .'|' . $GLOBALS['appSecret'];
}

//TAG:REQUESTS
function getRequests() {
  global $facebook, $userToken, $fbid;
  $appToken = getAppToken();
  // usually, the SDK works with a user token; we need to switch to an app token
  $facebook->setAccessToken($appToken);
  // read the user's outstanding requests from the Graph
  $fbRequests = $facebook->api("/$fbid/apprequests", 'GET');
  // and switch back:
  $facebook->setAccessToken($userToken);
  return $fbRequests['data'];
  return array();
}

function getFbRequests($user, $param) {
  restReturn(getRequests());
}

function initGame($user, $params) {
// start a new game, should be flexible as to letters and time
  $letterSet = getParam('l');
  $timeLimit = getParam('t');
  $difficulty = getParam('d');
  $length = getParam('ln');
  if($difficulty !== false && $length !== false) {
    switch($difficulty) {
      case 0:
        $letterSet = pickNiceWord();
        break;
      case 1:
        $letterSet = 10;
        break;
      case 2:
        $letterSet = 7;
        break;
      default:
        $letterSet = 5; // how did you get here, I wonder?
    }

    $lengths = array(15, 60, 99);
    $timeLimit = @$lengths[$length];
    $timeLimit = is_null($timeLimit) ? 60 : $timeLimit;
  }
  $m = new Match($user, $letterSet, $timeLimit);
  Store::set(Store::MATCH, $m->getMatchID(), $m);
  $spec = array (
    'matchID' => $m->getMatchID(),
    'timeLimit' => $m->getTimeLimit(),
    'letterSet' => $m->getLetterSet()->getLetters(),
    'details' => $m->getLetterSet()->getDetails(),
    'rules' => $m->getRules(),
  );
  restReturn($spec);
}

function getWordHashes($user, $params) {
// get this list of hashed words
  $matchID = getParam('m');
  $m = Store::get(Store::MATCH, $matchID);
  if(!$m) {
    // catch this
    trigger_error("ERROR: h Invalid mid ($matchID)", E_USER_ERROR);
  }

  $participants = $m->getParticipants();
  if(isset($participants[$user])) {
    $shadows = array();
    foreach ($m->getLetterSet()->getWordlist() as $word) {
      $shadows[] = md5($word . $matchID);
    }
    $m->setStartTime($user, time());
    Store::set(Store::MATCH, $matchID, $m);
    restReturn($shadows);
  } else {
    trigger_error("ERROR: h user $user not in match $matchID", E_USER_ERROR);
  }
}

function submitWordList($user, $params) {
// check for achievements, win/loss, publish; return scores + words
  $matchID = getParam('m');
  $m = Store::get(Store::MATCH, $matchID);
  if(!$m) {
    // catch this
    trigger_error("ERROR: s Invalid mid ($matchID) user $user", E_USER_ERROR);
  }
  $words = getParam('w') ? getParam('w') : array();
  $participants = $m->getParticipants();
  if(array_key_exists($user, $participants)) {
    $m->updateParticipant($user, $words);
  } else {
    trigger_error("ERROR: s user $user not in match $matchID", E_USER_ERROR);
  }
  Store::set(Store::MATCH, $matchID, $m);
  $scores = $m->getScores();
//TAG:SCORES
  if($GLOBALS['on']['scores']) {
    publishScore($user, $scores[$user]);
  }

//TAG:ACHIEVEMENTS
  if($GLOBALS['on']['achievements']) {
    require_once('Achievements.php');
    Achievements::grantAchievements($user, $scores[$user]);
  }
  restReturn($scores);
}

function challengeOtherUser($user, $params) { // not currently used in client
// add a new participant to a match
  $matchID = getParam('m');
  $m = Store::get(Store::MATCH, $matchID);
  if(!$m) {
    // catch this
    trigger_error("ERROR: c Invalid mid ($matchID) user $user", E_USER_ERROR);
  }
  $words = getParam('w') ? getParam('w') : array();
  $participants = $m->getParticipants();
  if(array_key_exists($user, $participants)) {
    $m->updateParticipant($user, $words);
  } else {
    trigger_error("ERROR: c user $user not in match $matchID", E_USER_ERROR);
  }

  $opponents = (getParam('o')) ? json_decode(getParam('o')) : array();
  foreach($opponents as $o) {
    if(!array_key_exists($o, $participants)) {
      $m->updateParticipant($o, array());
    }
  }
  Store::set(Store::MATCH, $matchID, $m);
}

function getMyResult($user, $params) {
// look up my old boards plus opponents'
  $matchID = getParam('m');
  $m = Store::get(Store::MATCH, $matchID);
  if(!$m) {
    // catch this
    trigger_error("ERROR: c Invalid mid ($matchID) user $user", E_USER_ERROR);
  }
  $who = (getParam('p')) ? json_decode(getParam('p')) : false;
  $participants = $m->getParticipants();

  if(array_key_exists($user, $participants)) {
    $scores = $m->getScores($who);
    restReturn($scores);
  } else {
    trigger_error("ERROR: c user $user not in match $matchID", E_USER_ERROR);
  }
}

function respondToInvite($user, $params) {
  $matchID = getParam('m');
  $m = Store::get(Store::MATCH, $matchID);
  if(!$m) {
    // catch this
    trigger_error("ERROR: r Invalid mid ($matchID) user $user", E_USER_ERROR);
  }
  $participants = $m->getParticipants();
  if($participants[$user]) {
    trigger_error("ERROR: r user $user already played $matchID", E_USER_ERROR);
  }
  $m->updateParticipants($user, array());
  Store::set(Store::MATCH, $matchID, $m);

  $spec = array (
    'matchID' => $m->getMatchID(),
    'timeLimit' => $m->getTimeLimit(),
    'letterSet' => $m->getLetterSet()->getLetters(),
    'details' => $m->getLetterSet()->getDetails(),
    'rules' => $m->getRules(),
  );
  restReturn($spec);
}

function restReturn($value) {
  echo json_encode($value);
  flush();
  return $value;
}

function getParam($name) {
  return isset($_REQUEST[$name]) ? $_REQUEST[$name] : false;
}

function pickNiceWord() {
  return 'indescribable';
}

function render_client() {
  global $passToClient; // how we pass data from client to server at init
  $passToClient['appID'] = $GLOBALS['appID'];
  $passToClient['domain'] = $GLOBALS['domain'];
  $passToClient['appName'] = $GLOBALS['appName'];
  foreach($GLOBALS['on'] as $flag => $value) {
    $passToClient[$flag] = $value;
  }
  $passToClient['catalog'] = $GLOBALS['catalog'];
  $GLOBALS['clientFragment'] =
    '<script id="clientFragment"> var settings = ' .
    json_encode($passToClient) .
    '</script>';
  require_once('./client/agarman.html');
}


//TAG:PAYMENTS
function getBalance($user, $params) {
  $balance = Store::get(Store::META, $user, 'moneys');
  restReturn($balance ? (0+$balance) : 0);
}

if($GLOBALS['on']['payments']) {
  $GLOBALS['passToClient']['catalog'] = $GLOBALS['catalog'];
}

//TAG:SCORES
function publishScore($user, $score) {
  global $facebook, $userToken;
  // Switch to the app access token
  $facebook->setAccessToken(getAppToken());
  // write to the Graph API
  $facebook->api("/$user/scores", 'POST', array('score' => $score));
  // and switch back to the user token
  $facebook->setAccessToken($userToken);
}
