<head>
  <title>PHP Reflection</title>
  <link href="https://fonts.googleapis.com/css?family=Permanent+Marker|Sarabun" rel="stylesheet">
  <link rel="stylesheet" href="..\css\reset.css" />
  <link rel="stylesheet" href="..\css\main.css" />
  <script src='https://www.google.com/recaptcha/api.js'></script>
</head>
<body>
  <header>
    <nav class="nav-bar">
      <li class="nav-item">
        <a href="/home" class='logo'>
          <h1 class='logo-heading'>ShareList</h1>
          <h4 class='logo-subheading'>Create and discover new playlists</h4>
        </a>
      </li>
      <?php if (isset($_COOKIE['access_token'])) { ?>
        <li class="nav-item my-page">
          <a href="/personal-page">My Page</a>
        </li>
        <li class="nav-item logout">
          <a href="/logout">Logout</a>
        </li>
      <?php } else { ?>
        <li class="nav-item login">
          <a href="/login">Login</a>
        </li>
        <li class="nav-item register">
          <a href="/register">Register</a>
        </li>
      <?php } ?>
    </nav>
  </header>
