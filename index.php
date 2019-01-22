<?php
  require __DIR__."/inc/bootstrap.php";

  use \Psr\Http\Message\ServerRequestInterface as Request;
  use \Psr\Http\Message\ResponseInterface as Response;
  $app = new \Slim\App();
  $container = $app->getContainer();
  $container['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler('../logs/app.log');
    $logger->pushHandler($file_handler);
    return $logger;
  };
  $container['view'] = function ($container) {
    $view = new \Slim\Views\PhpRenderer('./views/');
    $view->parserOptions = array(
      'debug' => true
    );
    return $view;
  };

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
    setcookie("email", $cleanEmail, '/', getenv("COOKIE_DOMAIN"));
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

    $expTime = time() + 3600;
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
    setcookie("email", $cleanEmail, '/', getenv("COOKIE_DOMAIN"));
    setcookie("username", $cleanUsername, '/', getenv("COOKIE_DOMAIN"));

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
    $userCheck = isAuthenticated();
    if (!$userCheck) {
      $redirect = "undefined-personal-page";
      $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect]);
      return $res;
    } else {
      $username = strtolower(getUsername());
      if ($username) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('personal-page', array('username' => $username)));
      } else {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('undefined-personal-page'));
      }
    }
  })->setName('undefined-personal-page');

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // GET route for named personal page //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->get('/personal-page/{username}', function(Request $req, Response $res, $args) use ($app) {
    $username = $args['username'];
    $userCheck = isAuthenticated($username);
    $username = getUsername();
    if ($userCheck) {
      $res = $this->view->render($res, '/personal-page.php', ['username' => $username]);
      return $res;
    }
    if (isset($_COOKIE['access_token'])) {
      $res = $this->view->render($res, '/no-access.php');
      return $res;
    }
    $redirect = "personal-page";
    $pattern_key = 'username';
    $res = $this->view->render($res, '/login.php', ['forced' => true, 'redirect' => $redirect, 'pattern_key' => $pattern_key, 'pattern_value' => $username]);
    return $res;
  })->setName('personal-page');

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  // Redirects and run statement //
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
  $app->redirect('/',$app->getContainer()->get('router')->pathFor('home'));

  $app->run();
  ?>

  <footer>

  </footer>
</body>

<!-- curl -X "POST" -H "Authorization: Basic ZjM4ZjAw...WY0MzE=" -d grant_type=client_credentials https://accounts.spotify.com/api/token -->
<!-- id 5baaf25119874e48a1bc538024e4b5a4 - NWJhYWYyNTExOTg3NGU0OGExYmM1MzgwMjRlNGI1YTQ= -->
<!-- sc 157fc35b515c4635bf9fef9a491b8d24 - MTU3ZmMzNWI1MTVjNDYzNWJmOWZlZjlhNDkxYjhkMjQ= -->
