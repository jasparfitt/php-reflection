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
    $file_handler = new \Monolog\Handler\StreamHandler('../logs/app.log');
    $logger->pushHandler($file_handler);
    return $logger;
  };

  $c['view'] = function ($container) {
    $view = new \Slim\Views\PhpRenderer('./views/');
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
    $res = $this->view->render($res, '/home.php');
    return $res;
  })->setName('home');

  // ~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for login page //
  // ~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/login', function (Request $req, Response $res) {
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
      if (empty($value)) {
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
    $jwt = makeJWT($expTime, $req, $user);
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
    $jwt = makeJWT($expTime, $req, $user);
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
    $username = $args['username'];
    $userCheck = isAuthenticated($username);
    $username = getUsername();
    // if user is logged in and on their page show page
    if ($userCheck) {
      $res = $this->view->render($res, '/personal-page.php', ['username' => $username]);
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
    $res = $this->view->render($res, '/create-playlist.php');
    return $res;
  })->setName('create-playlist');

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // POST route for new playlist //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->post('/create-playlist', function(Request $req, Response $res) use ($app) {
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

    $_SESSION['playlist'] = $playlist;

    if (isset($req->getParsedBody()["add-track"])) {
      // if playlist is 50 tracks or longer redirect with message
      if (sizeof($cleanTracks) >= 50) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'))
                   ->withHeader('Set-Cookie', "msg=Maximum playlist length reached; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
      }
      // if spotify URI is submitted
      if (isset($req->getParsedBody()['spotify'])) {
        // get spotify URI from post
        $URI = $req->getParsedBody()['spotify'];
        // filter URI
        $cleanURI = filter_var($URI, FILTER_SANITIZE_STRING);
        // save cookie incase of redirect
        setcookie("form", "spotify", time() + 3600, '/', getenv("COOKIE_DOMAIN"));
        setcookie("URI", $cleanURI, time() + 3600, '/', getenv("COOKIE_DOMAIN"));

        // if URI is empty redirect back to new playlist with message
        if (empty($cleanURI)) {
          return $res->withStatus(302)
                     ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'))
                     ->withHeader('Set-Cookie', "msg=Please enter a valid URI; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
        }

        $explodedURI = explode(":",$cleanURI);
        // if URI is not a track redirect with message
        if ($explodedURI[1] != "track") {
          return $res->withStatus(302)
                     ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'))
                     ->withHeader('Set-Cookie', "msg=Please enter a valid URI of a single song; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
        }

        // get track info from spotify api
        $spotifyId = $explodedURI[2];
        $track = getTrack($spotifyId);

        // if no track is returned redirect with message
        if (empty($track)) {
          return $res->withStatus(302)
                     ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'))
                     ->withHeader('Set-Cookie', "msg=Could not get track from spotify. Please enter a valid URI of a single song; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
        }

        // get song title, artist and link from data
        $trackName = $track->name;
        foreach ($track->artists as $artist) {
          $artistList[] = $artist->name;
        }
        $artistName = implode(' & ', $artistList);
        $spotifyLink = $track->external_urls->spotify;
      }
      // if track is submitted manually
      if (isset($req->getParsedBody()['track-name'])) {
        // get track and artist from post
        $trackName = $req->getParsedBody()['track-name'];
        $artistName = $req->getParsedBody()['artist-name'];
        // filter input
        $cleanTrackName = filter_var($trackName, FILTER_SANITIZE_STRING);
        $cleanArtistName = filter_var($artistName, FILTER_SANITIZE_STRING);
        // save cookies incase of Redirects
        setcookie("form", "manual", time() + 3600, '/', getenv("COOKIE_DOMAIN"));
        setcookie("trackName", $cleanTrackName, time() + 3600, '/', getenv("COOKIE_DOMAIN"));
        setcookie("artistName", $cleanArtistName, time() + 3600, '/', getenv("COOKIE_DOMAIN"));

        if (empty($cleanArtistName) || empty($cleanTrackName)) {
          return $res->withStatus(302)
                     ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'))
                     ->withHeader('Set-Cookie', "msg=Please enter a song title and artist; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
        }
        $trackName = $cleanTrackName;
        $artistName = $cleanArtistName;
        $spotifyLink = '';
      }

      // write new track into playlist array
      $cleanTracks[] = [
        "title"=>$trackName,
        "artist"=>$artistName,
        "link"=>$spotifyLink
      ];
      $playlist = [
        "title"=>$cleanTitle,
        "description"=>$cleanDesc,
        "tracks"=>$cleanTracks,
        "privacy"=>$cleanPrivacy
      ];

      // save playlist array to cookie
      session_name('playlist');
      session_set_cookie_params(3600,'/',getenv("COOKIE_DOMAIN"));
      session_start();
      $_SESSION['playlist'] = $playlist;
      session_write_close();
      destroyCookie("URI");
      destroyCookie("trackName");
      destroyCookie("artistName");

      // redirect back to create playlist page
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'));
    }

    if (isset($req->getParsedBody()["finish"])) {
      if (empty($cleanTitle)) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'))
                   ->withHeader('Set-Cookie', "msg=Playlists must have a title; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
      }
      if (empty($privacy) || ($privacy != "public" && $privacy != "private")) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'))
                   ->withHeader('Set-Cookie', "msg=Please select either public or private status; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
      }
      if (sizeof($cleanTracks) < 1) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('create-playlist'))
                   ->withHeader('Set-Cookie', "msg=Playlists must have at least one song in them; Domain=".getenv("COOKIE_DOMAIN")."; Path=/");
      }

      include __DIR__."/inc/connection.php";
      try {
        $db->query("CREATE TABLE MyTempTable (
          trackName text,
          artistName text
        )");
        foreach ($cleanTracks as $track ) {
          $result = $db->prepare("INSERT INTO MyTempTable (trackName, artistName) VALUES (?, ?)");
          $result->bindParam(1, $track['title'], PDO::PARAM_STR);
          $result->bindParam(2, $track['artist'], PDO::PARAM_STR);
          $result->execute();
        }
        $result = $db->query("SELECT * FROM MyTempTable");
        var_dump($result->fetchAll(PDO::FETCH_ASSOC));
        die("died");
      } catch (Exception $e) {
        echo "bad query".$e->getMessage();
        die();
      }
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('undefined-personal-page'));
    }
  });

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // Redirects and run statement //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->redirect('/',$app->getContainer()->get('router')->pathFor('home'));

  $app->run();
  ?>
