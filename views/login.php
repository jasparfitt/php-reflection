  <?php
  include 'inc/header.php';
  ?>
  <main>
    <div class="margin-box login-page">
      <h1>Login</h1>
      <form action="/login" method="post">
        <?php if(isset($_COOKIE["msg"])) { ?>
        <p class="error-msg">
          <?php
          echo $_COOKIE["msg"];
          unset($_COOKIE["msg"]);
          setcookie('msg', '', time() - 3600);
          ?>
        </p>
        <?php }?>
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
              <input type="submit" name="login" value="Login" />
            </td>
          </tr>
        </table>
      </form>
    </div>
  </main>
</body>
