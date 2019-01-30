<?php
  // get title, description, and confirmed tracks from posted
  $title = $req->getParsedBody()['title'];
  $description = $req->getParsedBody()['description'];
  $tracks = array();
  if (isset($req->getParsedBody()['tracks'])) {
    $tracks = $req->getParsedBody()['tracks'];
  }
  $privacy = $req->getParsedBody()['privacy'];
  // filter inputs
  $cleanTitle = filter_var($title, FILTER_SANITIZE_STRING);
  $cleanDesc = filter_var($description, FILTER_SANITIZE_STRING);
  $cleanPrivacy = filter_var($privacy, FILTER_SANITIZE_STRING);
  $cleanTracks = array();
  foreach ($tracks as $track) {
    $cleanTrack=explode("~#~", filter_var($track, FILTER_SANITIZE_STRING));
    if (sizeof($cleanTrack) == 2) {
      $cleanTracks[] =[
        "title"=>$cleanTrack[0],
        "artist"=>$cleanTrack[1],
        "link"=>''
      ];
    } else if (sizeof($cleanTrack) > 2) {
      $cleanTracks[] =[
        "title"=>$cleanTrack[0],
        "artist"=>$cleanTrack[1],
        "link"=>$cleanTrack[2]
      ];
    }
  }
  // save playlist to cookie incase of redirect
  $playlist = [
    "title"=>$cleanTitle,
    "description"=>$cleanDesc,
    "tracks"=>$cleanTracks,
    "privacy"=>$cleanPrivacy
  ];

  session_name('playlist');
  session_id("playlist");
  session_set_cookie_params(3600,'/',getenv("COOKIE_DOMAIN"));
  session_start();
  $_SESSION['playlist'] = $playlist;
  session_write_close();
  // check length
  if (sizeof($cleanTracks) >= 100) {
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor($redirect, $pattern))
               ->withHeader('Set-Cookie', "msg=Maximum playlist length of 100 exceeded; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
  }
  // check user is logged in and get userId if not redirect
  if (isAuthenticated()) {
    $userId = getUserId();
    if ($userId === false) {
      $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect, 'pattern_key' => $pattern_key, 'pattern_value' => $playlistId]);
      return $res;
    }
  } else {
    $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect, 'pattern_key' => $pattern_key, 'pattern_value' => $playlistId]);
    return $res;
  }

  // check that playlist has title
  if (empty($cleanTitle)) {
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor($redirect, $pattern))
               ->withHeader('Set-Cookie', "msg=Playlists must have a title; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
  }

  // check that privacy is set
  if (empty($privacy) || ($privacy != "public" && $privacy != "private")) {
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor($redirect, $pattern))
               ->withHeader('Set-Cookie', "msg=Please select either public or private status; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
  }
  // check that playlist has songs in it
  if (sizeof($cleanTracks) < 1) {
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor($redirect, $pattern))
               ->withHeader('Set-Cookie', "msg=Playlists must have at least one song in them; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
  }
  return null;
?>
