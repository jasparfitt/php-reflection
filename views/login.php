  <?php
  if (!isset($forced)) {
    $forced = false;
    $redirect = 'home';
    $pattern_key = '';
    $pattern_value = '';
  }
  include __DIR__.'/../inc/header.php';
  include __DIR__.'/../inc/error-message.php';
  ?>
  <?php if(isset($_COOKIE["msg"])) { ?>
    <div class="error-msg">
        <?php
        echo $_COOKIE["msg"];
        destroyCookie('msg');
        ?>
    </div>
  <?php }?>
  <main>
    <div class="margin-box login-page">
      <?php if ($forced) { ?>
        <h1>You need to be logged in to view this content</h1>
      <?php } else { ?>
        <h1>Login</h1>
      <?php } ?>
      <form action="/login" method="post">
        <table>
          <tr>
            <td class="label">
              <label for="email">Email: </label>
            </td>
            <td class="input">
              <input name="email" type="email" />
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
            <td></td>
            <td>
              <input class="submit-btn" type="submit" name="login" value="Login" />
            </td>
          </tr>
        </table>
        <input type="hidden" name="redirect" value="<?php echo $redirect; ?>">
        <input type="hidden" name="pattern-key" value="<?php echo $pattern_key; ?>">
        <input type="hidden" name="pattern-value" value="<?php echo $pattern_value; ?>">
      </form>
    </div>
  </main>
<?php include __DIR__."/../inc/error-message-code.php"; ?>
