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

// ALL storage-implementation-dependent code goes here.
// No exceptions.

// We'll expose just the static methods set(k,v) and get(k)

require_once('./LetterSet.php');
require_once('./Match.php');


class Store {
  // Field order constants - these are array offsets
  const TYPE = 0;

  // Object type constants (to deal with storing non-objects easily)
  const WORDLIST = '37f7e807';
  const MATCH    = '5f4cbf08';
  const META     = '7c5519a0';

  private static $mc = false;
  private static $dbh = false;

/**
 * Read the value associated with a key from the store
 * @param mixed ... The values composed into the key; first must be a type const
 * @return mixed The value associated with the composed key, or false on failure
 **/
  public static function get() {
    $key = func_get_args();
    $value = self::readCache($key);
    if ($value === false) {
      $value = self::readDB($key);
      self::writeCache($key, $value);
    }
    return $value;
  }

/**
 * Associate $value with $key in the store
 * @param mixed ... The values composed into the key; first must be a type const
 * @param mixed The last argument is treated as the value to store
 * @return boolean true on successful write, false otherwise
 **/

  public static function set() {
    $args = func_get_args();
    $value = array_pop($args);
    $key = $args;
    if(self::writeDB($key, $value)) {
      self::writeCache($key, $value);
      return true;
    }
    return false;
  }

  private static function readDB($key) {
    $value = false;
    if($key[self::TYPE] == self::WORDLIST) {

      // we'll store these as a cute little sqlite DB to keep it compact for
      // deployment purposes; there's no obvious need to implement a DB write
      // method for this storage type

      $bitmask = $key[1];
      $db = new PDO('sqlite:./wordhashes.sqlite');
      $st = $db->prepare('select words from wordhash where hash = :hash');
      $st->execute(array(':hash' => $bitmask));
      $value = $st->fetchColumn();
      if($value !== false) $value = explode(' ', $value);
    }
    if($key[self::TYPE] == self::MATCH) {
      $matchID = $key[1];
      $db = self::getDB();
      $st = $db->prepare(
'select player, letters, timelimit, words, starttime, details
 from matches where matchid = :match;'
      );
      $st->execute(array(':match' => $matchID));
      $participants = array();
      $startTimes = array();
      $letterSet = '';
      $details = '';
      $timeLimit = 60;
      $rows = 0;
      while($row = $st->fetch()) {
        ++$rows;
        $participants[$row['player']] = empty($row['words']) ?
          array() :
          json_decode($row['words'], true);
        $startTimes[$row['player']] = $row['starttime'];
        $details = json_decode($row['details'], true);
        $letterSet = $row['letters'];
        $timeLimit = $row['timelimit'];
        $startTime = $row['starttime'];
      }
      if($rows > 0) {
        $value = new Match($participants, $letterSet, $timeLimit, $startTimes,
                           $details, $matchID);
      } else {
        return false;
      }
    }

    if($key[self::TYPE] == self::META) {
      $db = self::getDB();
      $st = $db->prepare(
'select metavalue from metadata where objectid = :oid and metafield = :field;'
      );
      $st->execute(
        array( ':oid' => $key[1], ':field' => $key[2])
      );
      $row = $st->fetch();
      if($row) $value = $row[0];
      else return false;
    }
    return $value;
  }

  private static function writeDB($key, $value) {
    if($key[self::TYPE] == self::MATCH) {
      $matchID = $key[1];
      $db = self::getDB();
      $st = $db->prepare(
'insert into matches (matchid, player, letters, details,
 timelimit, words, starttime)
  values (:matchid, :player, :letters, :details, :time, :words, :starttime)
 on duplicate key update words = :words, starttime = :starttime;'
      );
      $timeLimit = $value->getTimeLimit();
      $letters = join('', $value->getLetterSet()->getLetters());
      $details = json_encode($value->getLetterSet()->getDetails());
      $params = array (
        ':matchid' => $matchID,
        ':player' => 0,
        ':letters' => $letters,
        ':details' => $details,
        ':time' => $timeLimit,
        ':words' => '',
        ':starttime' => 0,
      );
      foreach($value->getParticipants() as $p => $words) {
        $params[':player'] = $p;
        $params[':words'] = json_encode($words);
        $params[':starttime'] = $value->getStartTime($p);
        $st->execute($params);
      }
    } else if ($key[self::TYPE] == self::META) {
      $db = self::getDB();
      $st = $db->prepare(
'insert into metadata (objectid, metafield, metavalue)' .
'values (:oid, :field, :value)' .
'on duplicate key update metavalue = :value;'
      );
      $st->execute(
        array( ':oid' => $key[1], ':field' => $key[2], ':value' => $value)
      );
    }
    return true;
  }

  private static function readCache($key) {
    $k = self::composeKey($key);
    $value = self::getCache()->get($k);
     return $value;
  }

  private static function writeCache($key, $value) {
    $type = $key[self::TYPE];
    $key = self::composeKey($key);
    return self::getCache()->set($key, $value);
  }

  private static function composeKey($list) {
    $ax = '';
    foreach ($list as $element) $ax .= $element;
    return $ax;
  }

  private static function getCache() {
    global $memcacheServers; // from config.php
    if(! self::$mc) {
    self::$mc = new Memcache;
    foreach ($memcacheServers as $hostport)
      self::$mc->addServer($hostport[0], $hostport[1]);
    }
    return self::$mc;
  }

  private static function getDB() {
    // The values of the parameters to the PDO constructor come from config.php
    // This should get a PDO resource for the main DB, which the setup script
    //  main.sql assumes will be mysql - change as appropriate.
    if(!self::$dbh) {
      global $dbms, $dbname, $dbhost, $dbuser, $dbpass;
      self::$dbh = new PDO("$dbms:dbname=$dbname;host=$dbhost",
                           $dbuser, $dbpass);
    }
    return self::$dbh;
  }

  public static function stash($key, $value) {
    return self::getCache()->set($key, $value);
  }

  public static function fetch($key) {
    return self::getCache()->get($key);
  }
}
