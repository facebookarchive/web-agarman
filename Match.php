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
require_once('./LetterSet.php');

class Match {
  private $matchID, $letterSet, $participants, $timeLimit, $rules, $startTimes;
  const SALT = '9ad6acd6';

  public function __construct($participants, $letterSet=false, $timeLimit=false,
                              $startTimes=false, $details = false,
                              $matchID=false, $rules = false) {
    if(!is_array($participants)) {
      $participants = array($participants => array());
    }

    // TODO: factor out a Participant class
    $this->participants = $participants;

    $this->letterSet = new LetterSet($letterSet, $details);

    $this->timeLimit = $timeLimit ? $timeLimit : 60;

    $this->matchID = $matchID ? $matchID : mt_rand();

    $this->startTimes = $startTimes ?
      $startTimes :
      array_fill_keys(array_keys($participants), 0);

    $this->rules = $GLOBALS['scoringRules']['default'];
    $this->rules->lengths[0] = 1; // no length can have a multiplier <1

    // TODO: Generalize the rules code a bit. Create a Scorer class, give
    // one to Match as a property.
  }

/**
 * Sets a participant's response and scores it
 * @param mixed $participant
 * @param array $words
 * @return int The score for this set of words, specified by $rules
 **/

  public function updateParticipant($participant, $words) {
    if($words) { // we're adding this user's response
      if ($this->participants[$participant] ||
          ($this->startTimes[$participant] && // in case this somehow failed
           ((time() - $this->startTimes[$participant]) >
            ($this->timeLimit + $GLOBALS['timeFudge']))
          )
         ) {
        // double or too-late update? return old score
        $total = 0;
        foreach(array_values($this->participants[$participant]) as $s) {
          $total += $s;
        }
        return $total;
      }
      $total = 0;
      foreach($words as $w) {
        $score = $this->scoreWord($w);
        if ($score > 0) {
          $this->participants[$participant][$w] = $score;
        }
        $total += $score;
      }
      return $score;
    } else { // here comes a new challenger
      $this->participants[$participant] = array();
      $this->startTimes[$participant] = 0;
    }
    return $this;
  }

  public function setStartTime($user, $time = false) {
    $time = $time ? $time : time();
    $this->startTimes[$user] = $time;
    return $this;
  }

  public function getStartTime($user = false) {
    if(!$user) { //no user specified, get all
      return $this->startTimes;
    } else if($user && isset($this->startTimes[$user])) {
      return $this->startTimes[$user];
    } else { // user specified has no start time
      $this->startTimes[$user] = 0;
      return 0;
    }
  }

  public function getParticipants() {
    return $this->participants;
  }

  public function getLetterSet() {
    return $this->letterSet;
  }

  public function getMatchID() {
    return $this->matchID;
  }

  public function getTimeLimit() {
    return $this->timeLimit;
  }

  public function getRules() {
    return $this->rules;
  }

// false/unspecified to get all scores
  public function getScores($participants = false) {
    if(!$participants) {
      $participants = array_keys($this->participants);
    } else {
      if(!is_array($participants)) {
        $participants = array($participants);
      }
      $participants = array_intersect(
        array_keys($this->participants),
        $participants
      );
    }
    $scores = array();
    foreach($participants as $p) {
      $scores[$p] = array_sum(array_values($this->participants[$p]));
    }
    return $scores;
  }

  public function scoreWord($word) {
    if(!in_array($word, $this->letterSet->getWordlist())) {
      return 0; // rampant cheatery? possible powerup?
    }

    $letters = str_split($word);
    $scorelen = count($letters);
    while(@!$this->rules['lengths'][$scorelen] && $scorelen >=0) {
      --$scorelen;
    }
    $multiplier = max($this->rules['lengths'][$scorelen], 1);

    $score = 0;
    foreach($letters as $l) {
      $score += $this->rules['letters'][$l];
    }

    return $score * $multiplier;
  }
}
