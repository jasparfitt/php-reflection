<li class="playlist-item" id="<?php if (isset($remove)) {echo $playlist["playlistId"];} ?>">
  <div class="playlist-header">
    <a href="/playlist/<?php echo $playlist["playlistId"]; ?>"><h3 class="playlist-title"><?php echo $playlist["name"]; ?></h3></a>
    <?php if (isset($playlist["username"])) { ?>
      <h3 class="playlist-author"> by <?php echo $playlist["username"] ?></h3>
    <?php } ?>
  </div>
  <div class="playlist-likes">
    <span id="NoL<?php echo $playlist["playlistId"] ?>" class="NoL<?php echo $playlist["playlistId"] ?> playlist-info playlist-nol">Likes: <?php echo $playlist["NoL"]; ?></span>
  </div>
  <?php if (isset($playlist_privacy)) { ?>
    <div class="playlist-privacy">
      <span class="playlist-info playlist-privacy"><?php echo $playlist["privacy"]; ?></span>
    </div>
  <?php } ?>
  <?php if (($playlist_control)) { ?>
    <div class="playlist-like">
      <?php
        include __DIR__."/like-button.php";
      ?>
    </div>
    <div class="playlist-save">
      <?php
        include __DIR__."/save-button.php";
      ?>
    </div>
  <?php } ?>
  <?php if (isset($playlist_edit)) { ?>
    <div class="playlist-edit">
      <?php
        include __DIR__."/edit-button.php";
        include __DIR__."/delete-button.php";
      ?>
    </div>
  <?php } ?>
  <div class="playlist-description">
    <p>
      <?php echo $playlist["description"]; ?>
    </p>
  </div>
</li>
