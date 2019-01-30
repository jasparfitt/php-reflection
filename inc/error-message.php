<?php if(isset($_COOKIE["msg"])) { ?>
  <div class="error-msg" id="error">
      <?php
      echo $_COOKIE["msg"];
      destroyCookie('msg');
      ?>
      <button class="error-btn" onclick="removeMessage()"><i class='fas fa-times'></i></button>
  </div>
<?php }?>
