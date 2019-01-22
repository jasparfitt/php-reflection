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
    // filter posted data
    $cleanEmail = strtolower(filter_var($email, FILTER_SANITIZE_EMAIL));
    $cleanPassword = filter_var($password, FILTER_SANITIZE_STRING);
    // save email to cookie incase of error
    setcookie("email", $cleanEmail);

    // check email and password were both entered
    foreach ($req->getParsedBody() as $key => $value) {
      if (empty($value)) {
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('login'))
                   ->withHeader('Set-Cookie', "msg=Please enter a username and password");
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
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('login'))
                 ->withHeader('Set-Cookie', "msg=Password or email invalid");
    }

    // check passwords match.
    if (!password_verify($cleanPassword, $user['password'])) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('login'))
                 ->withHeader('Set-Cookie', "msg=Password or email invalid");
    }
    // delete email cookie
    unset($_COOKIE["email"]);
    setcookie('email', '', time() - 3600);

    $expTime = time() + 3600;

    // redirect to home
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor('home'))
               ->withHeader('Set-Cookie', "msg=Logon succesful");
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
    setcookie("email", $cleanEmail);
    setcookie("username", $cleanUsername);

    // check all inputs are given
    foreach ($req->getParsedBody() as $key => $value) {
      if (empty($value)) {
        if ($key == 'g-recaptcha-response') {
          return $res->withStatus(302)
                     ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                     ->withHeader('Set-Cookie', "msg=Please confirm captcha.");
        }
        return $res->withStatus(302)
                   ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                   ->withHeader('Set-Cookie', "msg=All fields are required.");
      }
    }

    // call captcha api
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $header = "Authorization: Basic NWJhYWYyNTExOTg3NGU0OGExYmM1MzgwMjRlNGI1YTQ6MTU3ZmMzNWI1MTVjNDYzNWJmOWZlZjlhNDkxYjhkMjQ=";
    $body = [
      'secret' => '6LfZd4sUAAAAAMMwRrMs147L6JbKoDMQ_OYF8EIs',
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
                 ->withHeader('Set-Cookie', "msg=Could not handle this request.");
    }
    // check passwords are same
    if ($cleanPassword != $cleanPasswordConfirm) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Passwords do not match.");
    }
    // check for valid email
    if (!filter_var($cleanEmail,FILTER_VALIDATE_EMAIL)) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Invalid email.");
    }
    // check for password length
    if ($cleanPassword > 6) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Password must be six characters or longer.");
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
                 ->withHeader('Set-Cookie', "msg=Email already has an account.");
    }
    // check if username is already in use
    if (in_array(strtolower($username), $usernameList)) {
      return $res->withStatus(302)
                 ->withHeader('Location', $app->getContainer()->get('router')->pathFor('register'))
                 ->withHeader('Set-Cookie', "msg=Username already in use.");
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
    unset($_COOKIE["username"]);
    setcookie('username', '', time() - 3600);
    unset($_COOKIE["email"]);
    setcookie('email', '', time() - 3600);

    // redirect back to home page
    return $res->withStatus(302)
               ->withHeader('Location', $app->getContainer()->get('router')->pathFor('home'))
               ->withHeader('Set-Cookie', "msg=Successfully created account.");
  });

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
