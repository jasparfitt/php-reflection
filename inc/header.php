<head>
  <title>ShareList</title>
  <link href="https://fonts.googleapis.com/css?family=Permanent+Marker|Sarabun" rel="stylesheet">
  <link rel="stylesheet" href="..\css\reset.css" />
  <link rel="stylesheet" href="..\css\main.css" />
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">
  <script src='https://www.google.com/recaptcha/api.js'></script>
</head>
<body id="body">
  <header>
    <nav class="nav-bar">
      <li class="nav-item">
        <div class='logo'>
          <div class="logo-wrapper">
            <a href="/home">
              <h1 class='logo-heading'>ShareList</h1>
              <h4 class='logo-subheading'>Create and discover <br /> new playlists</h4>
            </a>
          </div>
        </div>
      </li>
      <li class="nav-item search">
        <form class="search-form" method="get" action="/1">
          <input class="search-bar" type="text" name="search" placeholder="search"/>
          <input class="search-button" type="submit" value="Go" />
        </form>
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
