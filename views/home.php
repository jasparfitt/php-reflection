  <?php
  include __DIR__.'/../inc/header.php';
  ?>
  <main>
    <div class="margin-box">
      <h1>Popular Playlists</h1>
      <ul class="user-playlists">
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
                <form method="post" action="like/<?php echo $playlist["playlistId"] ?>">
                  <button name="delete" class="<?php if (isset($playlist["userLikes"])) {echo "disabled";} ?>">Like</button>
                  <input type="hidden" name="redirect" value="home">
                  <input type="hidden" name="pattern-key" value="">
                  <input type="hidden" name="pattern-value" value="">
                </form>
                <form method="get" action="save/<?php echo $playlist["playlistId"] ?>">
                  <button name="edit">Save</button>
                </form>
              </div>
            </li>
            <?php
          }
        }
        ?>
      </ul>
    </div>
  </main>
