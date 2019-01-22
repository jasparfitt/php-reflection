<?php

function getToken() {
  $url = "https://accounts.spotify.com/api/token";
  $header = "Authorization: Basic NWJhYWYyNTExOTg3NGU0OGExYmM1MzgwMjRlNGI1YTQ6MTU3ZmMzNWI1MTVjNDYzNWJmOWZlZjlhNDkxYjhkMjQ=";
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
  $data->expires = time() + $data->expires_in;
  return $data;
}

function getTrack($track) {
  checkToken();
  $url = "https://api.spotify.com/v1/tracks/$track";
  $header = "Authorization: Bearer ".$_SESSION['token']->access_token;

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
  if (!isset($_SESSION)) {
    session_name ('token');
    session_set_cookie_params(3600);
    session_start();
  }
  if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = getToken();
  }
}
?>
