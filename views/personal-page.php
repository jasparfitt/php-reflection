<?php include __DIR__.'/../inc/header.php'; ?>
<main>
  <div class="margin-box">
    <h1>Personal Page of <?php echo $username ?></h1>
    <h3>Your Playlists</h3>
    <ul class="user-playlists">
      <?php
      if (empty($userPlaylists)) {
        echo "
        <li>
          <p class='placeholder'>
            You haven't made any playlists yet
          </p>
        </li>
        ";
      }
      ?>
      <a href="/create-playlist"><li>
        Add new playlist
      </li></a>
    </ul>

    <h3>Saved Playlists</h3>
    <ul class="saved-playlists">
      <?php
      if (empty($userPlaylists)) {
        echo "
        <li>
          <p class='placeholder'>
            You haven't saved any playlists yet
          </p>
        </li>
        ";
      }
      ?>
    </ul>

  </div>
</main>
