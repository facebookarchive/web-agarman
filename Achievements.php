<?php
require_once('./config.php');
require_once('./Store.php');

class Achievements {
  // See if the user is eligible for achievements, and grant them
  public static function grantAchievements($user, $score) {

    $scoreTriggers = array(
      10 => 'a10pts',
      100 => 'a100pts',
      200 => 'a200pts',
      500 => 'a500pts',
    );

    global $achievements, $appURL;
    $appToken = findAppToken();
    $titles = array();
    $grantURL = "https://graph.facebook.com/$user/achievements";
    foreach($scoreTriggers as $points => $name) {
      if($score >= $points) {
        $achURL = "http:$appURL?f=ra&n=$name";
        $titles[] = $achievements[$name]['title'];
        // Write to the Graph API with the user and the URL of the achievement
        httpPost($grantURL, array(
                    'achievement' => $achURL,
                    'access_token' => $appToken,
                  )
                 );
      }
    }
/*
    if($score == 0) {
      $name = 'azero';
      $achURL = "http:$appURL?f=ra&n=$name";
      $titles[] = $achievements[$name]['title'];
      httpPost($grantURL, array(
                 'achievement' => $achURL,
                 'access_token' => $appToken,
               )
              );
    }
*/
      return $titles;
  }

  // Associate all of our achievements with the app - we only need to do this at
  // build time when we've changed the achievements, not each time we run the
  // game
  public static function registerAchievements() {
    global $achievements, $appID, $appName, $appURL;
    $appToken = findAppToken();
    $results = array();
    // Read the graph to find out what's already associated with the app
    $current = json_decode(
      file_get_contents(
        "https://graph.facebook.com/$appID/achievements&access_token=$appToken"
      ),
      true
    );
    $current = $current['data'];
    $registered = array();
    foreach ($current as $a) {
      $registered[] = $a['url'];
    }
    foreach ($achievements as $name => $details) {
      $achURL = "http:$appURL?" .
        "f=ra&n=$name";
      if(in_array($achURL, $registered)) {
        continue;
      }
      $order = $details['display_order'];
      // Write to the graph if any achievements have been added or changed
      $results [$achURL] =
        httpPost("https://graph.facebook.com/$appID/achievements",
                 Array(
                   'access_token' => $appToken,
                   'achievement' => $achURL,
                   'display_order' => $order,
                 )
                );
    }
    return array(
      'had' => array_fill_keys($registered, true),
      'added' => $results
    );
  }

  // Render a web page for a given achievement
  public static function getAchievement($name) {
    global $achievements, $appID;
    if(isset($achievements[$name])) {
      $a = $achievements[$name];
    } else {
      emitRedirect($name);
    }

$msg = <<<END
<html>
  <head>
  <title>AGAR MAN Achievement: $a[title]</title>
  <meta property="og:type" content="game.achievement" />
  <meta property="og:title" content="$a[title]" />
  <meta property="og:url" content="http:$appURL?f=ra&n=$name" />
  <Meta property="og:description" content="$a[description]" />
  <meta property="og:image" content="$a[image]" />
  <meta property="game:points" content="$a[points]" />
  <meta property="fb:app_id" content="$appID" />

  <script>
      top.location.href=
       "http://apps.facebook.com/$GLOBALS[appName]?from=$name";
  </script>
</head>
<body>
</body>
</html>
END;
    return $msg;
  }
}

function findAppToken() {
  return $GLOBALS['appID'] .'|'. $GLOBALS['appSecret'];
}

function httpPost ($url, $data) { // utility function
  $options = array('http' => array(
                     'method'  => 'POST',
                     'content' => http_build_query($data)
                   ));
  $context  = stream_context_create($options);
  $result = @file_get_contents($url, false, $context);
  return $result;
}

// Array of achievements, id => (properties)

$achievements = array(
/*  'azero' =? array (
    'display_order' => 40,
    'title' => 'Super Zero',
    'description' => ('You were faced with your nemesis - ' .
                      'a wall of consonants!'),
    'image' =>"http://$GLOBALS[domain]/nagaram/client/agarthumb.png",
    'points' => 20,
    ), */
  'a10pts'  => array (
    'display_order' => 10,
    'title' => 'Origin Story',
    'description' => ('You scored 10 points in AGAR MAN. ' .
                      'Every hero has to start somewhere!'),
    'image' => "http://$GLOBALS[domain]/nagaram/client/agarthumb.png",
    'points' => 20,
  ),
  'a100pts' => array (
    'display_order' => 20,
    'title' => 'Rise to Prominence',
    'description' => 'You scored 100 points in AGAR MAN. ' .
    'That\'s why they call you a hero!',
    'image' => "http://$GLOBALS[domain]/nagaram/client/agarthumb.png",
    'points' => 100,
  ),
  'a200pts' => array (
    'display_order' => 30,
    'title' => 'Transcendence',
    'description' => 'You scored 200 points in AGAR MAN. ' .
    'How does it feel to be a legend?',
    'image' => "http://$GLOBALS[domain]/nagaram/client/agarthumb.png",
    'points' => 200,
  ),
  'a500pts' => array (
    'display_order' => 30,
    'title' => 'Beyond the imposible',
    'description' => 'You scored 500 points in AGAR MAN. ' .
    'You are the ULTIMATE WORD SUPERHERO.',
    'image' => "http://$GLOBALS[domain]/nagaram/client/agarthumb.png",
    'points' => 300,
  ),
);
