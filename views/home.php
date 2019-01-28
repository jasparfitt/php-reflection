  <?php
  include __DIR__.'/../inc/header.php';
  ?>
  <main>
    <div class="margin-box">
      <h1>Popular Playlists</h1>
      <ul class="popular-playlists playlists">
        <?php
        if (empty($playlists)) {
          echo "
          <li class='playlist-item'>
            <p class='placeholder'>
              No playlists to show.
            </p>
          </li>
          ";
        } else {
          foreach ($playlists as $playlist) {
            ?>
            <li class="playlist-item">
              <div class="playlist-info">
                <div class="playlist-header">
                  <a href="/playlist/<?php echo $playlist["playlistId"]; ?>"><h3 class="playlist-title"><?php echo $playlist["name"]; ?></h3></a>
                  <h3 class="playlist-author"> by <?php echo $playlist["username"] ?></h3>
                  <span class="playlist-privacy">Likes: <?php echo $playlist["NoL"]; ?></span>
                </div>
                <div class="playlist-description">
                  <p>
                    <?php echo $playlist["description"]; ?>
                  </p>
                </div>
              </div>
              <div class="playlist-control">
                <button id="like<?php echo $playlist["playlistId"] ?>" onclick="onLike(event, <?php echo $playlist["playlistId"] ?>)" name="like" class="<?php if (isset($playlist["userLikes"])) {echo "disabled";} ?>">
                  <?php
                    if (isset($playlist["userLikes"])) {
                      echo "Liked";
                    } else {
                      echo "Like";
                    }
                  ?>
                </button>
                <button id="save<?php echo $playlist["playlistId"] ?>" onclick="onSave(event, <?php echo $playlist["playlistId"] ?>)" name="save" class="<?php if (isset($playlist["userSaves"])) {echo "disabled";} ?>">
                  <?php
                    if (isset($playlist["userSaves"])) {
                      echo "Saved";
                    } else {
                      echo "Save";
                    }
                  ?>
                </button>
              </div>
            </li>
            <?php
          }
        }
        ?>
      </ul>
    </div>
  </main>
  <script> let remove = false;</script>
<?php
  include __DIR__."/../inc/save-code.php";
  include __DIR__."/../inc/like-code.php";
?>
