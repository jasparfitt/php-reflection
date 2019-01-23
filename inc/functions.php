<?php

function getToken() {
  $url = "https://accounts.spotify.com/api/token";
  $header = "Authorization: Basic ".getenv("SPOTIFY_SECRET");
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

function getTrack($track) {
  checkToken();
  $url = "https://api.spotify.com/v1/tracks/$track";
  session_name("token");
  session_start();
  $header = "Authorization: Bearer ".$_SESSION['token']->access_token;
  session_write_close();
  $opts = array('http' =>
      array(
          'method'  => 'GET',
          'header'  => $header
      )
  );
  $context  = stream_context_create($opts);
  $result = file_get_contents($url, false, $context);
  $data = json_decode($result);
  return $data;
}

function checkToken () {
  if (!isset($_COOKIE['token'])) {
    session_name('token');
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
  try {
    \Firebase\JWT\JWT::$leeway = 1;
    $decoded_jwt=\Firebase\JWT\JWT::decode($_COOKIE['access_token'], getenv("SITE_SECRET"), array('HS256'));
    return $decoded_jwt->sub;
  } catch (Exception $e) {
    destroyCookie('access_token');
    return false;
  }
}

function makeJWT ($expTime, $req, $user) {
  $token = array(
    'iss' => $req->getUri()->getBaseUrl(),
    'sub' => $user['username'],
    'exp' => $expTime,
    'iat' => time(),
    'nbf' => time(),
    'is_admin' => $user['roleId'] == 1
  );
  $jwt = \Firebase\JWT\JWT::encode($token, getenv("SITE_SECRET"), 'HS256');
  return $jwt;
}

function destroyCookie($name) {
  unset($_COOKIE[$name]);
  setcookie($name, '', time() - 3600, '/', getenv("COOKIE_DOMAIN"));
}

function confirm () {

}
?>
