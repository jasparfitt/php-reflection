<?php

function getToken() {
  $url = "https://accounts.spotify.com/api/token";
  $header = "Content-Type: application/x-www-form-urlencoded\n".
            "Authorization: Basic ".getenv("SPOTIFY_SECRET");
  $body = ['grant_type'=>'client_credentials'];

  $postdata = http_build_query($body);
  $opts = array('http' =>
      array(
          'method'  => 'POST',
          'header'  => $header,
          'content' => $postdata
      )
  );
  $context  = stream_context_create($opts);
  $result = file_get_contents($url, false, $context);
  $data = json_decode($result);
  return $data;
}

function callApi($url, $redirect=false) {
  checkToken();
  try {
    session_name("token");
    session_id("token");
    session_set_cookie_params(3400,'/',getenv("COOKIE_DOMAIN"));
    session_start();
    $header = "Content-Type: application/x-www-form-urlencoded\n".
              "Authorization: Bearer ".$_SESSION['token']->access_token;
    session_write_close();
    $opts = array('http' =>
        array(
            'method'  => 'GET',
            'header'  => $header
        )
    );
    set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
      // error was suppressed with the @-operator
      if (0 === error_reporting()) {
          return false;
      }

      throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
    $context  = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);
    $data = json_decode($result);
    restore_error_handler();
    return $data;
  } catch (Exception $e) {
    $message = $e->getMessage();
    if ($redirect) {
      return ["error"=>$e->getMessage()];
    }
    if (strpos($message, "401") !== false) {
      destroyCookie('token');
      return callApi($url, true);
    } else {
      return ["error"=>$e->getMessage()];
    }
  }
}

function getTrack($id) {
  $url = "https://api.spotify.com/v1/tracks/$id";
  return callApi($url);
}

function getPlaylist($id) {
  $url = "https://api.spotify.com/v1/playlists/$id/tracks";
  return callApi($url);
}

function getArtist($id) {
  $url = "https://api.spotify.com/v1/artists/$id/top-tracks?market=GB";
  return callApi($url);
}

function getAlbum($id) {
  $url = "https://api.spotify.com/v1/albums/$id/tracks";
  return callApi($url);
}

function checkToken () {
  if (!isset($_COOKIE['token'])) {
    session_name('token');
    session_id("token");
    session_set_cookie_params(3400,'/',getenv("COOKIE_DOMAIN"));
    session_start();
    $_SESSION['token'] = getToken();
    session_write_close();
  }
}

function isAuthenticated ($username = null) {
  if (!isset($_COOKIE["access_token"])) {
    return false;
  }
  try {
    \Firebase\JWT\JWT::$leeway = 1;
    $decoded_jwt=\Firebase\JWT\JWT::decode($_COOKIE['access_token'], getenv("SITE_SECRET"), array('HS256'));
    if (!isset($username)) {
      return true;
    } else if (strtolower($username) == strtolower($decoded_jwt->sub)) {
      return true;
    } else if ($decoded_jwt->is_admin == true) {
      return true;
    } else {
      return false;
    }
  } catch (Exception $e) {
    destroyCookie('access_token');
    return false;
  }
}

function getUsername() {
  if (!isset($_COOKIE["access_token"])) {
    return false;
  }
  try {
    \Firebase\JWT\JWT::$leeway = 1;
    $decoded_jwt=\Firebase\JWT\JWT::decode($_COOKIE['access_token'], getenv("SITE_SECRET"), array('HS256'));
    return $decoded_jwt->sub;
  } catch (Exception $e) {
    destroyCookie('access_token');
    return false;
  }
}

function getUserId() {
  if (!isset($_COOKIE["access_token"])) {
    return false;
  }
  try {
    \Firebase\JWT\JWT::$leeway = 1;
    $decoded_jwt=\Firebase\JWT\JWT::decode($_COOKIE['access_token'], getenv("SITE_SECRET"), array('HS256'));
    return $decoded_jwt->userId;
  } catch (Exception $e) {
    destroyCookie('access_token');
    return false;
  }
}

function makeJWT ($expTime, $req, $user, $userId) {
  $token = array(
    'iss' => $req->getUri()->getBaseUrl(),
    'sub' => $user['username'],
    'exp' => $expTime,
    'iat' => time(),
    'nbf' => time(),
    'is_admin' => $user['roleId'] == 1,
    'userId' => $userId
  );
  $jwt = \Firebase\JWT\JWT::encode($token, getenv("SITE_SECRET"), 'HS256');
  return $jwt;
}

function destroyCookie($name) {
  setcookie($name, '', time() - 3600, '/', getenv("COOKIE_DOMAIN"));
  unset($_COOKIE[$name]);
}

function getPlaylistOwner ($playlistId) {
  include __DIR__."/connection.php";
  try {
    $result = $db->prepare("SELECT privacy, userId FROM playlists WHERE playlistId = ?;");
    $result->bindParam(1, $playlistId, PDO::PARAM_INT);
    $result->execute();
    return $result->fetch(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    echo "bad query ".$e->getMessage();
    die("died");
  }
}

function findUniqueSongs ($cleanTracks) {
  $uniqueTracks = array();
  foreach ($cleanTracks as $song) {
    $add = true;
    foreach ($uniqueTracks as $checkedSong) {
      if ($song['title'] == $checkedSong["title"] && $song["artist"] == $checkedSong["artist"]) {
        $add = false;
        if (empty($checkedSong['link']) && !empty($song['link'])) {
          $key = array_search($checkedSong, $uniqueTracks);
          $uniqueTracks[$key]["link"] = $song['link'];
        }
      }
    }
    if ($add) {
      $uniqueTracks[] = $song;
    }
  }
  return $uniqueTracks;
}
?>
