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

require_once('./Store.php');
require_once('./config.php');

class LetterSet {
  private $letterSet = array();
  private $bitmasks = array();
  private $wordlists = array();
  private $details = array();

  public function __construct($spec = false, $details = false) {
    $spec = $spec ? $spec : $GLOBALS['defaultSpec'];
    if (is_numeric($spec)) {
      $this->chooseLetters($spec);
    }
    else {
      $this->letterSet = array_unique(
        str_split(
          preg_replace('/[^a-z]/', '', strtolower($spec))
        )
      );
      sort($this->letterSet);
    }
    $this->details = $details ? $details : array('spec' => $spec);
    $this->buildBitmasks();
    $this->lookupWordlists();
    return $this;
  }

  private function chooseLetters($setSize) {
    $vowels = array('a', 'e', 'i', 'o');
    $rest = str_split('qwrtyupsdfghjklzxcvbnm');
    shuffle($vowels);
    $this->letterSet[] = array_pop($vowels);
    array_splice($rest, 0, 0, $vowels);
    shuffle($rest);
    for ($i = 1; $i < $setSize; ++$i) {
      $this->letterSet[] = array_pop($rest);
    }
    sort($this->letterSet);
  }

  private function buildBitmasks() {
    $offsets = array();
    foreach ($this->letterSet as $c) {
      $offsets[] = ord($c) - ord('a');
    }

    for ($coef = 1; $coef < (1<<count($offsets)); ++$coef) {
      $v = 0;
      for($i = 0; $i<count($offsets); ++$i) {
        $v |= (($coef >> $i) % 2) << $offsets[$i];
        // print(" coef $coef i $i v $v\n");
      }
      $this->bitmasks[] = $v;
    }
  }

  private function lookupWordlists() {
    foreach($this->bitmasks as $mask) {
      $addend = Store::get(Store::WORDLIST, $mask);
      if ($addend) {
        $this->wordlists = array_merge(
        $this->wordlists,
        $addend
      );
      }
    }
  }

  public function getWordlist() {
    return $this->wordlists;
  }

  public function getLetters() {
    return $this->letterSet;
  }

  public function getDetails() {
    return $this->details;
  }
}
