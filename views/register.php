  <?php
  $email = '';
  $username = '';
  if (isset($_COOKIE['email'])) {
    $email = $_COOKIE['email'];
    unset($_COOKIE["email"]);
    setcookie('email', '', time() - 3600);
  }
  if (isset($_COOKIE['username'])) {
    $username = $_COOKIE['username'];
    unset($_COOKIE["username"]);
    setcookie('username', '', time() - 3600);
  }

  include __DIR__.'/../inc/header.php';
  include __DIR__.'/../inc/error-message.php';
  ?>
  <main>
    <div class="margin-box register-page">
      <h1>Register</h1>
      <form action="/register" method="post">
        <table>
          <tr>
            <td class="label">
              <label for="Username">Username: </label>
            </td>
            <td class="input">
              <input name="username" type="text" value="<?php echo $username; ?>" />
            </td>
          </tr>
          <tr>
            <td class="label">
              <label for="email">Email: </label>
            </td>
            <td class="input">
              <input name="email" type="email" value="<?php echo $email; ?>" />
            </td>
          </tr>
          <tr>
            <td class="label">
              <label for="password">Password: </label>
            </td>
            <td class="input">
              <input name="password" type="password" />
            </td>
          </tr>
          <tr>
            <td class="label">
              <label for="password-confirm">Re-Enter Password: </label>
            </td>
            <td class="input">
              <input name="password-confirm" type="password"/>
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <div class="g-recaptcha" data-sitekey="6LfZd4sUAAAAAD4pC1qWncGv1Hlr6eGecXYyjZU8"></div>
            </td>
          </tr>
          <tr>
            <td colspan="2" class="submit">
              <input class="submit-btn" type="submit" name="register" value="Register" />
            </td>
          </tr>
        </table>
      </form>
    </div>
  </main>
<?php include __DIR__."/../inc/error-message-code.php"; ?>
