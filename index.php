<?php
  require __DIR__."/inc/bootstrap.php";

  use \Psr\Http\Message\ServerRequestInterface as Request;
  use \Psr\Http\Message\ResponseInterface as Response;

  $configuration = [
    'settings' => [
      'displayErrorDetails' => true,
    ],
  ];

  $c = new \Slim\Container($configuration);

  $c['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler(__DIR__.'/../logs/app.log');
    $logger->pushHandler($file_handler);
    return $logger;
  };

  $c['view'] = function ($container) {
    $view = new \Slim\Views\PhpRenderer(__DIR__.'/views');
    $view->parserOptions = array(
      'debug' => true
    );
    return $view;
  };

  $c['notFoundHandler'] = function () {
    return function (Request $req, Response $res) {
      include __DIR__."/views/not-found.php";
      die();
    };
  };

  $app = new \Slim\App($c);
  $container = $app->getContainer();

  // ~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for home page //
  // ~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/home', function (Request $req, Response $res) {
    // get userId if logged in
    $userId = getUserId();
    // include connection to db
    include __DIR__."/inc/connection.php";
    if ($userId === false) {
      // if user is not logged in find 10 most liked playlists
      $result = $db->query("
        SELECT playlists.playlistId, COUNT(likes.userId) AS NoL, name, description, username FROM playlists
        LEFT OUTER JOIN likes ON likes.playlistId = playlists.playlistId
        INNER JOIN users ON playlists.userId = users.userId
        WHERE privacy = 'public'
        GROUP BY playlistId
        ORDER BY nol DESC, RAND()
        LIMIT 10;
      ");
    } else {
      // if user is logged in find 10 most liked playlists plus if user likes them
      $result = $db->prepare("
        SELECT
          playlists.playlistId,
          COUNT(likes.userId) AS NoL,
          name,
          description,
          username,
          userLikes.userId AS userLikes,
          userSaves.userId AS userSaves
        FROM playlists
        LEFT OUTER JOIN likes
          ON likes.playlistId = playlists.playlistId
        INNER JOIN users
          ON playlists.userId = users.userId
        LEFT OUTER JOIN (
          SELECT * FROM likes
          WHERE userId = ?
        ) AS userLikes
          ON userLikes.playlistId = playlists.playlistId
        LEFT OUTER JOIN (
          SELECT * FROM saves
          WHERE userId = ?
        ) AS userSaves
          ON playlists.playlistId = userSaves.playlistId
        WHERE privacy = 'public'
        GROUP BY playlistId
        ORDER BY nol DESC, RAND()
        LIMIT 10;
      ");
      $result->bindParam(1, $userId, PDO::PARAM_INT);
      $result->bindParam(2, $userId, PDO::PARAM_INT);
      $result->execute();
    }
    $playlists = $result->fetchAll(PDO::FETCH_ASSOC);
    $res = $this->view->render($res, '/home.php',["playlists"=>$playlists, "title"=>"Popular playlists"]);
    return $res;
  })->setName('home');

  // ~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for login page //
  // ~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/login', function (Request $req, Response $res) {
    if (isset($_COOKIE['redirect'])) {
      $forced = true;
      $redirect = filter_var($_COOKIE['redirect'], FILTER_SANITIZE_STRING);
      $patternKey = filter_var($_COOKIE['patternKey'], FILTER_SANITIZE_STRING);
      $patternValue = filter_var($_COOKIE['patternValue'], FILTER_SANITIZE_STRING);
      destroyCookie("redirect");
      destroyCookie("patternKey");
      destroyCookie("patternValue");
      $res = $this->view->render($res, '/login.php', ["forced"=>$forced, "redirect"=>$redirect, "pattern_key"=>$patternKey, "pattern_value"=>$patternValue]);
      return $res;
    }
    $res = $this->view->render($res, '/login.php');
    return $res;
  })->setName('login');

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // POST route for form submission of login //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->post('/login', function(Request $req, Response $res) use ($app) {
    // get posted data
    $email = $req->getParsedBody()['email'];
    $password = $req->getParsedBody()['password'];
    $redirect = $req->getParsedBody()['redirect'];
    $patternKey = $req->getParsedBody()['pattern-key'];
    $patternValue = $req->getParsedBody()['pattern-value'];
    // filter posted data
    $cleanEmail = strtolower(filter_var($email, FILTER_SANITIZE_EMAIL));
    $cleanPassword = filter_var($password, FILTER_SANITIZE_STRING);
    $cleanRedirect = filter_var($redirect, FILTER_SANITIZE_STRING);
    $cleanPatternKey = filter_var($patternKey, FILTER_SANITIZE_STRING);
    $cleanPatterValue = filter_var($patternValue, FILTER_SANITIZE_STRING);
    // save email to cookie incase of error
    setcookie("email", $cleanEmail, time() + 3600, '/', getenv("COOKIE_DOMAIN"));
    $errorRedirect = $successRedirect = $cleanRedirect;
    $pattern = [$cleanPatternKey => $cleanPatterValue];
    if ($cleanRedirect == 'home') {
      $errorRedirect = 'login';
      $pattern = [];
    }
    // check email and password were both entered
    foreach ($req->getParsedBody() as $key => $value) {
      if (empty($value) && ($key == "password" || $key == "email")) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor($errorRedirect,$pattern))
                   ->withHeader('Set-Cookie', "msg=Please enter a username and password; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
      }
    }
    // open database connection
    include __DIR__."\inc\connection.php";

    // find users data
    try {
      $result = $db->prepare("SELECT * FROM users WHERE email = ?");
      $result->bindParam(1, $cleanEmail, PDO::PARAM_STR);
      $result->execute();
      $user = $result->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      echo "failed query".$e->getMessage();
    }

    // check user was found.
    if (empty($user)) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor($errorRedirect,$pattern))
                 ->withHeader('Set-Cookie', "msg=Password or email invalid; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }

    // check passwords match.
    if (!password_verify($cleanPassword, $user['password'])) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor($errorRedirect,$pattern))
                 ->withHeader('Set-Cookie', "msg=Password or email invalid; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }
    // delete email cookie
    destroyCookie('email');

    $expTime = time() + (60 * 60 * 24);
    $jwt = makeJWT($expTime, $req, $user, $user["userId"]);
    // redirect to home
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor($successRedirect,$pattern))
               ->withHeader('Set-Cookie', "access_token=$jwt; Domain=".getenv("COOKIE_DOMAIN")."; Path=/; Expires=".date(DATE_RSS,$expTime));
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for register page //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/register', function (Request $req, Response $res) {
    $res = $this->view->render($res, '/register.php');
    return $res;
  })->setName('register');

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // POST route for form submission of register //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->post('/register', function(Request $req, Response $res) use ($app) {
    // get posted data
    $email = $req->getParsedBody()['email'];
    $password = $req->getParsedBody()['password'];
    $username = $req->getParsedBody()['username'];
    $passwordConfirm = $req->getParsedBody()['password-confirm'];
    $captcha = $req->getParsedBody()['g-recaptcha-response'];
    // filter posted data
    $cleanEmail = strtolower(filter_var($email, FILTER_SANITIZE_EMAIL));
    $cleanUsername = filter_var($username, FILTER_SANITIZE_STRING);
    $cleanPassword = filter_var($password, FILTER_SANITIZE_STRING);
    $cleanPasswordConfirm = filter_var($passwordConfirm, FILTER_SANITIZE_STRING);
    // save username and email to cookie incase of error
    setcookie("email", $cleanEmail, time() + 3600, '/', getenv("COOKIE_DOMAIN"));
    setcookie("username", $cleanUsername, time() + 3600, '/', getenv("COOKIE_DOMAIN"));

    // check all inputs are given
    foreach ($req->getParsedBody() as $key => $value) {
      if (empty($value)) {
        if ($key == 'g-recaptcha-response') {
          return $res->withStatus(302)
                     ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                     ->withHeader('Set-Cookie', "msg=Please confirm captcha.; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
        }
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                   ->withHeader('Set-Cookie', "msg=All fields are required.; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
      }
    }

    // call captcha api
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $body = [
      'secret' => getenv("GOOGLE_SECRET"),
      'response' => $captcha
    ];

    $postdata = http_build_query($body);
    $opts = array('http' =>
        array(
            'method' => 'POST',
            'content' => $postdata
        )
    );
    $context  = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);
    $data = json_decode($result);
    $passed = $data->success;
    $passed = true;

    // check captcha
    if (!$passed) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Could not handle this request.; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }
    // check passwords are same
    if ($cleanPassword != $cleanPasswordConfirm) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Passwords do not match.; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }
    // check for valid email
    if (!filter_var($cleanEmail,FILTER_VALIDATE_EMAIL)) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Invalid email.; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }
    // check for password length
    if ($cleanPassword > 6) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Password must be six characters or longer.; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }

    include __DIR__."\inc\connection.php";
    // get all users email and username
    try {
      $result = $db->query("SELECT email, username FROM users");
      $userList = $result->fetchAll(PDO::FETCH_ASSOC);
      $emailList = array();
      $usernameList = array();
      foreach ($userList as $user) {
        $emailList[] = $user['email'];
        $usernameList[] = strtolower($user['username']);
      }
    } catch (Exception $e) {
      echo "Query failed: " . $e->getMessage();
      die();
    }
    // check if email already has an account
    if (in_array($cleanEmail, $emailList)) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Email already has an account.; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }
    // check if username is already in use
    if (in_array(strtolower($cleanUsername), $usernameList)) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Username already in use.; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }
    // hash password for database storage
    $hashedPassword = password_hash($cleanPassword,PASSWORD_BCRYPT);
    // save user data to database
    try {
      $result = $db->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
      $result->bindParam(1, $cleanUsername, PDO::PARAM_STR);
      $result->bindParam(2, $hashedPassword, PDO::PARAM_STR);
      $result->bindParam(3, $cleanEmail, PDO::PARAM_STR);
      $added = $result->execute();
      $result = $db->prepare("SELECT userId FROM users WHERE email = ?;");
      $result->bindParam(1, $cleanEmail, PDO::PARAM_STR);
      $result->execute();
      $userId = $result->fetch()["userId"];
    } catch (Exception $e) {
      echo "Query failed: " . $e->getMessage();
      die();
    }

    // remove username and email cookies due to success
    destroyCookie('email');
    destroyCookie('username');

    // redirect back to home page
    $user = [
      'username' => $cleanUsername,
      'roleId' => 2
    ];
    $expTime = time() + 3600;
    $jwt = makeJWT($expTime, $req, $user, $userId);
    // redirect to home
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor('home'))
               ->withHeader('Set-Cookie', "access_token=$jwt; Domain=".getenv("COOKIE_DOMAIN")."; Path=/; Expires=".date(DATE_RSS,$expTime));
  });

  // ~~~~~~~~~~~~~~~~~~~~ //
  // GET route for logout //
  // ~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/logout', function(Request $req, Response $res) use ($app) {
    // remove access token
    unset($_COOKIE["access_token"]);
    setcookie('access_token', '', time() - 3600, '/', getenv("COOKIE_DOMAIN"));
    // redirect to homepage
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor('home'));
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for undefined personal page //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/personal-page', function (Request $req, Response $res) use ($app) {
    // check user is logged
    $userCheck = isAuthenticated();
    // if not logged show log in page
    if (!$userCheck) {
      $redirect = "undefined-personal-page";
      $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect]);
      return $res;
    } else {
      // find username
      $username = strtolower(getUsername());
      if ($username) {
        // if username is found redirect to personal-page/username
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('personal-page', array('username' => $username)));
      } else {
        // if no username is found redirect to personal-page
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('undefined-personal-page'));
      }
    }
  })->setName('undefined-personal-page');

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for named personal page //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/personal-page/{username}', function(Request $req, Response $res, $args) use ($app) {
    // check if user is logged in and on their page
    $username = filter_var($args['username'], FILTER_SANITIZE_STRING);
    $userCheck = isAuthenticated($username);
    $username = getUsername();
    $userId = getUserId();
    // if user is logged in and on their page show page
    if ($userCheck) {
      $res = $this->view->render($res, '/personal-page.php', ['username' => $username, "userId" => $userId]);
      return $res;
    }
    // if user is logged in and on another users page show no-access
    if (isset($_COOKIE['access_token'])) {
      $res = $this->view->render($res, '/no-access.php');
      return $res;
    }
    // if user is not logged in show log in form
    $redirect = "personal-page";
    $pattern_key = 'username';
    $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect, 'pattern_key' => $pattern_key, 'pattern_value' => $username]);
    return $res;
  })->setName('personal-page');

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for new playlist page //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/create-playlist', function(Request $req, Response $res) {
    // make sure user is logged in
    $userCheck = isAuthenticated();
    // if not logged in show log in form
    if (!$userCheck) {
      $redirect = "create-playlist";
      $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect]);
      return $res;
    }
    // if logged in show the new playlist page
    $redirect = "create-playlist";
    $patternKey = '';
    $patternValue = '';
    $res = $this->view->render($res, '/create-playlist.php', ["name"=>"Add a New Playlist", "postTo"=>"/create-playlist", 'redirect' => $redirect, 'pattern_key' => $patternKey, 'pattern_value' => $patternValue]);
    return $res;
  })->setName('create-playlist');

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // POST route for submitting new playlist //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->post('/create-playlist', function(Request $req, Response $res) use ($app) {
    $redirect = "create-playlist";
    $pattern = [];

    // do basic checks for required fields and login checks
    $error = require __DIR__."/inc/submit-playlist-checks.php";
    var_dump($error);
    if (!empty($error)) {
      return $error;
    }
    // open connection to database
    include __DIR__."/inc/connection.php";

    try {
      // get playlist names for user ID
      $result = $db->prepare("SELECT name FROM playlists WHERE userId = ?;");
      $result->bindParam(1, $userId, PDO::PARAM_INT);
      $result->execute();
      $playlists = $result->fetchAll(PDO::FETCH_ASSOC);
      $names = array();
      foreach ($playlists as $name ) {
        $names[]=$name["name"];
      }
      // check new title is not in use
      if (in_array($cleanTitle, $names)) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'))
                   ->withHeader('Set-Cookie', "msg=You already have a playlist with that name; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
      }
    } catch (Exception $e) {
      echo "bad query".$e->getMessage();
      die('died');
    }
    // create array of unique songs from playlist
    $uniqueTracks = findUniqueSongs($cleanTracks);

    try {
      // check songs against tracks already in database
      include __DIR__."/inc/check-upload-tracks.php";

      // make playlist in database
      $result = $db->prepare("INSERT INTO playlists (name, userId, description, privacy) VALUES (?, ?, ?, ?);");
      $result->bindParam(1, $cleanTitle, PDO::PARAM_STR);
      $result->bindParam(2, $userId, PDO::PARAM_INT);
      $result->bindParam(3, $cleanDesc, PDO::PARAM_STR);
      $result->bindParam(4, $cleanPrivacy, PDO::PARAM_STR);
      $result->execute();

      // find playlist ID
      $result = $db->prepare("SELECT playlistId FROM playlists WHERE name = ? && userId = ?");
      $result->bindParam(1, $cleanTitle, PDO::PARAM_STR);
      $result->bindParam(2, $userId, PDO::PARAM_STR);
      $result->execute();
      $playlistId = $result->fetch()["playlistId"];

      // link tracks to playlist
      foreach ($idList as $id) {
        $result = $db->prepare("INSERT INTO playlistsTracks (playlistId, trackId) VALUES (?, ?);");
        $result->bindParam(1, $playlistId, PDO::PARAM_INT);
        $result->bindParam(2, $id["trackId"], PDO::PARAM_INT);
        $result->execute();
      }
    } catch (Exception $e) {
      echo "bad query".$e->getMessage();
      die('died');
    }
    destroyCookie("playlist");
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor('playlist',["id"=>$playlistId]));
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for playlists  //
  // ~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/playlist/{id}', function(Request $req, Response $res, $args) {
    $playlistId = filter_var($args["id"], FILTER_SANITIZE_NUMBER_INT);
    $data = getPlaylistOwner($playlistId);
    if (empty($data)) {
      $res = $this->view->render($res, '/not-found.php');
      return $res;
    }
    $privacy = $data["privacy"];
    $playlistUserId = $data["userId"];
    $userId = getUserId();
    if ($privacy == "private") {
      if (!isAuthenticated()) {
        $redirect = "playlist";
        $pattern_key = "id";
        $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect, 'pattern_key' => $pattern_key, 'pattern_value' => $playlistId]);
        return $res;
      }
      if ($userId === false) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('playlist',["id" => $playlistId]));
      }
      if ($userId != $playlistUserId) {
        $res = $this->view->render($res, '/no-access.php');
        return $res;
      }
    }
    include __DIR__."/inc/connection.php";
    if ($userId === false) {
      $result = $db->prepare("
        SELECT username, name, description, privacy, trackName, artistName, spotifyLink, playlists.playlistId FROM playlists
        INNER JOIN users ON playlists.userId = users.userId
        INNER JOIN playlistsTracks ON playlists.playlistId = playlistsTracks.playlistId
        INNER JOIN tracks ON playlistsTracks.trackId = tracks.trackId
        WHERE playlists.playlistId = ?;
      ");
      $result->bindParam(1, $playlistId, PDO::PARAM_INT);
      $result->execute();
    } else {
      $result = $db->prepare("
        SELECT
          username,
          name,
          description,
          privacy,
          trackName,
          artistName,
          spotifyLink,
          playlists.playlistId,
          userLikes.userId AS userLikes,
          userSaves.userId AS userSaves
        FROM playlists
        INNER JOIN users
          ON playlists.userId = users.userId
        INNER JOIN playlistsTracks
          ON playlists.playlistId = playlistsTracks.playlistId
        INNER JOIN tracks
          ON playlistsTracks.trackId = tracks.trackId
        LEFT OUTER JOIN (
          SELECT * FROM likes
          WHERE userId = ?
        ) AS userLikes
          ON playlists.playlistId = userLikes.playlistId
        LEFT OUTER JOIN (
          SELECT * FROM saves
          WHERE userId = ?
        ) AS userSaves
          ON playlists.playlistId = userSaves.playlistId
        WHERE playlists.playlistId = ?;
      ");
      $result->bindParam(1, $userId, PDO::PARAM_INT);
      $result->bindParam(2, $userId, PDO::PARAM_INT);
      $result->bindParam(3, $playlistId, PDO::PARAM_INT);
      $result->execute();
    }
    $playlist = $result->fetchAll(PDO::FETCH_ASSOC);
    $res = $this->view->render($res, '/playlist.php', ["playlist" => $playlist]);
    return $res;
  })->setName("playlist");

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for updating playlists  //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/update/{id}', function(Request $req, Response $res, $args) use ($app) {
    $playlistId = filter_var($args["id"], FILTER_SANITIZE_NUMBER_INT);
    if (!isset($_COOKIE["playlist"])) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('edit',["id" => $playlistId]));
    }
    session_name('playlist');
    session_id("playlist");
    session_set_cookie_params(3600,'/',getenv("COOKIE_DOMAIN"));
    session_start();
    $title = filter_var($_SESSION['playlist']["title"], FILTER_SANITIZE_STRING);
    session_write_close();
    $redirect = "update";
    $patternKey = 'id';
    $res = $this->view->render($res, '/create-playlist.php', ["name"=>"Update $title", "postTo"=>"/update/$playlistId", "redirect"=>$redirect, "pattern_key"=>$patternKey, "pattern_value"=>$playlistId]);
    return $res;
  })->setName("update");

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // POST route for updating playlists  //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->post('/update/{id}', function(Request $req, Response $res, $args) use ($app) {
    $playlistId = filter_var($args["id"], FILTER_SANITIZE_NUMBER_INT);
    $redirect = "update";
    $pattern = ["id"=>$playlistId];
    // do basic checks for required fields and login checks
    $error = require __DIR__."/inc/submit-playlist-checks.php";
    if (!empty($error)) {
      return $error;
    }
    $playlistOwner = getPlaylistOwner($playlistId);
    // check playlist exists;
    if (empty($playlistOwner)) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor($redirect, $pattern))
                 ->withHeader('Set-Cookie', "msg=An unknown error occured; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }
    $playlistOwner = $playlistOwner["userId"];
    // check user is owner of playlist
    if ($playlistOwner != $userId) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor($redirect, $pattern));
    }
    // include connection to db
    include __DIR__."/inc/connection.php";

    // check playlist name isn't already in use
    try{
      $result = $db->prepare("SELECT name FROM playlists WHERE userId = ? && playlistId != ?;");
      $result->bindParam(1, $userId, PDO::PARAM_INT);
      $result->bindParam(2, $playlistId, PDO::PARAM_INT);
      $result->execute();
      $playlists = $result->fetchAll(PDO::FETCH_ASSOC);
      $nameList = array();
      foreach ($playlists as $name) {
        $nameList[] = $name["name"];
      }
      if (in_array($cleanTitle, $nameList)) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor($redirect, $pattern))
                   ->withHeader('Set-Cookie', "msg=You already have a playlist with this name; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
      }
    } catch (Exception $e) {
      echo "bad query ".$e->getMessage();
      die('died');
    }

    $uniqueTracks = findUniqueSongs($cleanTracks);

    try {
      // update playlist details
      $result = $db->prepare("UPDATE playlists SET description = ?, name = ?, privacy = ? WHERE playlistId = ?;");
      $result->bindParam(1, $cleanDesc, PDO::PARAM_STR);
      $result->bindParam(2, $cleanTitle, PDO::PARAM_STR);
      $result->bindParam(3, $cleanPrivacy, PDO::PARAM_STR);
      $result->bindParam(4, $playlistId, PDO::PARAM_INT);
      $result->execute();

      // delete track listing for playlist
      $result = $db->prepare("DELETE FROM playlistsTracks WHERE playlistId = ?;");
      $result->bindParam(1, $playlistId, PDO::PARAM_INT);
      $result->execute();

      // check songs against tracks in db
      include __DIR__."/inc/check-upload-tracks.php";

      foreach ($idList as $id) {
        $result = $db->prepare("INSERT INTO playlistsTracks (playlistId, trackId) VALUES (?, ?);");
        $result->bindParam(1, $playlistId, PDO::PARAM_INT);
        $result->bindParam(2, $id["trackId"], PDO:: PARAM_INT);
        $result->execute();
      }
    } catch (Exception $e) {
      echo "bad query ".$e->getMessage();
      die("died");
    }
    destroyCookie("playlist");
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor('playlist',["id"=>$playlistId]));
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for editing playlists  //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/edit/{id}', function(Request $req, Response $res, $args) use ($app) {
    $playlistId = filter_var($args["id"], FILTER_SANITIZE_NUMBER_INT);
    $userId = getUserId();
    $playlistOwner = getPlaylistOwner($playlistId);
    // check if playlist exists
    if (empty($playlistOwner)) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('undefined-personal-page'))
                 ->withHeader('Set-Cookie', "msg=An unknown error occured; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
    }
    // check user is logged in
    if ($userId === false) {
      $redirect = "playlist";
      $pattern_key = "id";
      $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect, 'pattern_key' => $pattern_key, 'pattern_value' => $playlistId]);
      return $res;
    }
    // check user owns playlist
    if ($userId != $playlistOwner["userId"]) {
      $res = $this->view->render($res, '/no-access.php');
      return $res;
    }
    include __DIR__."/inc/connection.php";
    $result = $db->prepare("
      SELECT username, name, description, privacy, trackName, artistName, spotifyLink, playlists.playlistId FROM playlists
      INNER JOIN users ON playlists.userId = users.userId
      INNER JOIN playlistsTracks ON playlists.playlistId = playlistsTracks.playlistId
      INNER JOIN tracks ON playlistsTracks.trackId = tracks.trackId
      WHERE playlists.playlistId = ?;
    ");
    $result->bindParam(1, $playlistId, PDO::PARAM_INT);
    $result->execute();
    $playlist = $result->fetchAll(PDO::FETCH_ASSOC);
    $preparedPlaylist = array();
    $tracks = array();
    foreach ($playlist as $track) {
      $tracks[] = array(
        "title"=>$track["trackName"],
        "artist"=>$track["artistName"],
        "link"=>$track["spotifyLink"]
      );
    }
    $preparedPlaylist = [
      "title"=>$playlist[0]["name"],
      "description"=>$playlist[0]["description"],
      "tracks"=>$tracks,
      "privacy"=>$playlist[0]["privacy"]
    ];

    session_name('playlist');
    session_id("playlist");
    session_set_cookie_params(3600,'/',getenv("COOKIE_DOMAIN"));
    session_start();
    $_SESSION['playlist'] = $preparedPlaylist;
    session_write_close();

    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor('update', ["id"=>$playlistId]));
  })->setName("edit");

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // POST route for deleting playlists  //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->post('/delete/{id}', function(Request $req, Response $res, $args) use ($app) {
    $playlistId = filter_var($args["id"], FILTER_SANITIZE_NUMBER_INT);
    $userId = getUserId();
    $playlistOwner = getPlaylistOwner($playlistId);
    // check if playlist exists
    if (empty($playlistOwner)) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('undefined-personal-page'));
    }
    // check user is logged in
    if ($userId === false) {
      $redirect = "playlist";
      $pattern_key = "id";
      $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect, 'pattern_key' => $pattern_key, 'pattern_value' => $playlistId]);
      return $res;
    }
    // check user owns playlist
    if ($userId != $playlistOwner["userId"]) {
      $res = $this->view->render($res, '/no-access.php');
      return $res;
    }
    // include connection to db
    include __DIR__."/inc/connection.php";
    $result = $db->prepare("DELETE FROM playlists WHERE playlistId = ?");
    $result->bindParam(1, $playlistId, PDO::PARAM_STR);
    $result->execute();
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor('undefined-personal-page'));
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // POST route for liking playlists using AJAX  //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->post('/like/{id}', function(Request $req, Response $res, $args) use ($app) {
    // get posted data
    $playlistId = filter_var($args["id"], FILTER_SANITIZE_STRING);
    // create redirect pattern
    $userId = getUserId();
    if ($userId === false) {
      echo json_encode(["goTo"=>"login", "playlistId"=>$playlistId]);
      die();
    }
    include __DIR__."/inc/connection.php";
    try {
      $result = $db->prepare("SELECT * FROM likes WHERE userId = ? && playlistId = ?;");
      $result->bindParam(1, $userId, PDO::PARAM_INT);
      $result->bindParam(2, $playlistId, PDO::PARAM_INT);
      $result->execute();
      $liked = $result->fetch(PDO::FETCH_ASSOC);
      if (!empty($liked)) {
        $result = $db->prepare("DELETE FROM likes WHERE userId = ? && playlistId = ?;");
        $result->bindParam(1, $userId, PDO::PARAM_INT);
        $result->bindParam(2, $playlistId, PDO::PARAM_INT);
        $result->execute();
      } else {
        $result = $db->prepare("INSERT INTO likes (userId, playlistId) VALUES (?, ?);");
        $result->bindParam(1, $userId, PDO::PARAM_INT);
        $result->bindParam(2, $playlistId, PDO::PARAM_INT);
        $result->execute();
      }
    } catch (Exception $e) {
      echo "bad request".$e->getMessage();
      die("died");
    }
    echo json_encode(["msg" =>"like successful"]);
    die();
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // POST route for saving playlists using AJAX  //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->post('/save/{id}', function(Request $req, Response $res, $args) use ($app) {
    // get posted data
    $playlistId = filter_var($args["id"], FILTER_SANITIZE_STRING);

    $userId = getUserId();
    if ($userId === false) {
      $redirect = "playlist";
      $pattern_key = "id";
      echo json_encode(["goTo"=>"login", "playlistId"=>$playlistId]);
      die();
    }
    include __DIR__."/inc/connection.php";
    try {
      $result = $db->prepare("SELECT * FROM saves WHERE userId = ? && playlistId = ?;");
      $result->bindParam(1, $userId, PDO::PARAM_INT);
      $result->bindParam(2, $playlistId, PDO::PARAM_INT);
      $result->execute();
      $liked = $result->fetch(PDO::FETCH_ASSOC);
      if (!empty($liked)) {
        $result = $db->prepare("DELETE FROM saves WHERE userId = ? && playlistId = ?;");
        $result->bindParam(1, $userId, PDO::PARAM_INT);
        $result->bindParam(2, $playlistId, PDO::PARAM_INT);
        $result->execute();
      } else {
        $result = $db->prepare("INSERT INTO saves (userId, playlistId) VALUES (?, ?);");
        $result->bindParam(1, $userId, PDO::PARAM_INT);
        $result->bindParam(2, $playlistId, PDO::PARAM_INT);
        $result->execute();
      }
    } catch (Exception $e) {
      echo "bad request".$e->getMessage();
      die("died");
    }
    echo json_encode(["msg" =>"save successful"]);
    die();
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for searching playlists  //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/{pageNum}', function(Request $req, Response $res, $args) use ($app) {
    $perPage = 10;
    $search = $req->getQueryParams()["search"];
    $pageNum = $args["pageNum"];
    $cleanSearch = filter_var($search, FILTER_SANITIZE_STRING);
    $cleanPageNum = filter_var($pageNum, FILTER_SANITIZE_NUMBER_INT);
    $sqlSearch = "%".strtoupper($cleanSearch)."%";
    $sqlOffset = ($pageNum - 1) * $perPage;
    include __DIR__."/inc/connection.php";
    $result = $db->prepare("SELECT COUNT(playlistId) AS count FROM playlists WHERE (UPPER(name) LIKE ? || UPPER(description) LIKE ?) && privacy = 'public';");
    $result->bindParam(1, $sqlSearch, PDO::PARAM_STR);
    $result->bindParam(2, $sqlSearch, PDO::PARAM_STR);
    $result->execute();
    $numPlaylists = $result->fetch(PDO::FETCH_ASSOC)["count"];
    $numPages = ceil($numPlaylists / $perPage);
    if ($pageNum > $numPages) {
      $pageNum = $numPages;
    }
    $sqlOffset = ($pageNum - 1) * $perPage;
    $userId = getUserId();
    if (!$userId) {
      $result = $db->prepare("
        SELECT playlists.playlistId, name, description, username, COUNT(likes.userId) AS NoL FROM playlists
        INNER JOIN users
          ON users.userId = playlists.userId
        LEFT OUTER JOIN likes
          ON likes.playlistId = playlists.playlistId
        WHERE (UPPER(name) LIKE ? || UPPER(description) LIKE ?) && privacy = 'public'
        GROUP BY playlistId
        LIMIT ?
        OFFSET ?;
      ");
      $result->bindParam(1, $sqlSearch, PDO::PARAM_STR);
      $result->bindParam(2, $sqlSearch, PDO::PARAM_STR);
      $result->bindParam(3, $perPage, PDO::PARAM_INT);
      $result->bindParam(4, $sqlOffset, PDO::PARAM_INT);
      $result->execute();
    } else {
      $result = $db->prepare("
        SELECT
          playlists.playlistId,
          name,
          description,
          username,
          COUNT(likes.userId) AS NoL,
          userLikes.userId AS userLikes,
          userSaves.userId AS userSaves
        FROM playlists
        INNER JOIN users
          ON users.userId = playlists.userId
        LEFT OUTER JOIN likes
          ON likes.playlistId = playlists.playlistId
        LEFT OUTER JOIN (
          SELECT * FROM likes
          WHERE userId = ?
        ) AS userLikes
          ON userLikes.playlistId = playlists.playlistId
        LEFT OUTER JOIN (
          SELECT * FROM saves
          WHERE userId = ?
        ) AS userSaves
          ON playlists.playlistId = userSaves.playlistId
        WHERE (UPPER(name) LIKE ? || UPPER(description) LIKE ?) && privacy = 'public'
        GROUP BY playlistId
        LIMIT ?
        OFFSET ?;
      ");
      $result->bindParam(1, $userId, PDO::PARAM_INT);
      $result->bindParam(2, $userId, PDO::PARAM_INT);
      $result->bindParam(3, $sqlSearch, PDO::PARAM_STR);
      $result->bindParam(4, $sqlSearch, PDO::PARAM_STR);
      $result->bindParam(5, $perPage, PDO::PARAM_INT);
      $result->bindParam(6, $sqlOffset, PDO::PARAM_INT);
      $result->execute();
    }
    $playlists = $result->fetchAll(PDO::FETCH_ASSOC);

    $res = $this->view->render($res, '/home.php',["playlists"=>$playlists, "title"=>"Search for: '$cleanSearch'", "pageNum"=>$pageNum, "numPages"=>$numPages, "searchTerm"=>$cleanSearch]);
    return $res;
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // POST route for adding song to a playlist using AJAX //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->post("/add-track", function (Request $req, Response $res) use ($app) {

    // get title, description, and confirmed tracks from posted
    $tracks = array();
    if (isset($req->getParsedBody()['tracks'])) {
      $tracks = $req->getParsedBody()['tracks'];
      $tracks = json_decode($tracks);
    }
    // if playlist is 50 tracks or longer redirect with message
    if (sizeof($tracks) >= 50) {
      echo json_encode(["error" => "Maximum playlist length reached"]);
    }
    // if spotify URI is submitted
    if ($req->getParsedBody()['method'] == "spotify") {
      // get spotify URI from post
      $URI = $req->getParsedBody()['spotify'];
      // filter URI
      $cleanURI = filter_var($URI, FILTER_SANITIZE_STRING);
      // if URI is empty redirect back to new playlist with message
      if (empty($cleanURI)) {
        echo json_encode(["error" => "Please enter a valid URI"]);
        die();
      }

      $explodedURI = explode(":",$cleanURI);
      // if URI is not a track redirect with message
      if ((!isset($explodedURI[1]) || !isset($explodedURI[2]) || ($explodedURI[1] != "track" && $explodedURI[1] != 'artist' && $explodedURI[1] != 'album')) && (!isset($explodedURI[3]) || !isset($explodedURI[4]) || $explodedURI[3] != "playlist")) {
        echo json_encode(["error" => "Please enter a valid URI of a single song"]);
        die();
      }
      if ($explodedURI[1] == "track") {
        // get track info from spotify api
        $spotifyId = $explodedURI[2];
        $track = getTrack($spotifyId);

        // if no track is returned redirect with message
        if (empty($track)) {
          echo json_encode(["error" => "Could not get track from spotify. Please enter a valid URI"]);
          die();
        }

        // get song title, artist and link from data
        $trackName = $track->name;
        foreach ($track->artists as $artist) {
          $artistList[] = $artist->name;
        }
        $artistName = implode(' & ', $artistList);
        $spotifyLink = $track->external_urls->spotify;

        $cleanTrack = [
          "title"=>$trackName,
          "artist"=>$artistName,
          "link"=>$spotifyLink
        ];
        $cleanTracks = [$cleanTrack];
      } else if (($explodedURI[1] == "album") || ($explodedURI[1] == "artist") || (isset($explodedURI[3]) && $explodedURI[3] == "playlist")) {
        $tracks = array();
        if (isset($explodedURI[3]) && $explodedURI[3] == "playlist") {
          // get track info from spotify api
          $spotifyId = $explodedURI[4];
          $tracks = getPlaylist($spotifyId);
          $tracks = $tracks->items;
        } else if ($explodedURI[1] == "album") {
          // get track info from spotify api
          $spotifyId = $explodedURI[2];
          $tracks = getAlbum($spotifyId);
          $tracks = $tracks->items;
        } else if ($explodedURI[1] == "artist") {
          // get track info from spotify api
          $spotifyId = $explodedURI[2];
          $tracks = getArtist($spotifyId);
          $tracks = $tracks->tracks;
        }
        // if no track is returned redirect with message
        if (empty($tracks)) {
          echo json_encode(["error" => "Could not get track from spotify. Please enter a valid URI"]);
          die();
        }
        // var_dump($playlistTracks);
        foreach ($tracks as $trackItem) {
          if (isset($trackItem->track)) {
            $track = $trackItem->track;
          } else {
            $track = $trackItem;
          }
          $trackName = $track->name;
          $artistList = array();
          foreach ($track->artists as $artist) {
            $artistList[] = $artist->name;
          }
          $artistName = implode(' & ', $artistList);
          $spotifyLink = $track->external_urls->spotify;
          $cleanTrack = [
            "title"=>$trackName,
            "artist"=>$artistName,
            "link"=>$spotifyLink
          ];
          $cleanTracks[] = $cleanTrack;
        }
        // spotify:user:spotify:playlist:37i9dQZF1DXaVgr4Tx5kRF
        // spotify:album:2okCg9scHue9GNELoB8U9g
        // spotify:artist:2cGwlqi3k18jFpUyTrsR84
      }
    }

    // if track is submitted manually
    if ($req->getParsedBody()['method'] == "manual") {
      // get track and artist from post
      $trackName = $req->getParsedBody()['track-name'];
      $artistName = $req->getParsedBody()['artist-name'];
      // filter input
      $cleanTrackName = filter_var($trackName, FILTER_SANITIZE_STRING);
      $cleanArtistName = filter_var($artistName, FILTER_SANITIZE_STRING);

      if (empty($cleanArtistName) || empty($cleanTrackName)) {
        echo json_encode(["error" => "Please enter a song title and artist"]);
        die();
      }
      $trackName = $cleanTrackName;
      $artistName = $cleanArtistName;
      $spotifyLink = '';
      // write new track into playlist array
      $cleanTrack = [
        "title"=>$trackName,
        "artist"=>$artistName,
        "link"=>$spotifyLink
      ];
      $cleanTracks = [$cleanTrack];
    }

    $data = [
      "tracks"=>$cleanTracks,
      "success"=>true
    ];
    // redirect back to create playlist page
    echo json_encode($data);
    die();
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // Redirects and run statement //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->redirect('/',$app->getContainer()->get('router')->pathFor('home'));

  $app->run();
  ?>
