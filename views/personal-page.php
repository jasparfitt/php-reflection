<?php
include __DIR__.'/../inc/connection.php';
try {
  $result = $db->prepare("SELECT name, description, privacy, playlistId FROM playlists WHERE userId = ?;");
  $result->bindParam(1, $userId, PDO::PARAM_STR);
  $result->execute();
  $userPlaylists = $result->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  echo "bad query".$e->getMessage();
  die('died');
}

include __DIR__.'/../inc/header.php';
?>
<main>
  <div class="margin-box">
    <h1>Personal Page of <?php echo $username ?></h1>
    <h2>Your Playlists</h2>
    <ul class="user-playlists">
      <?php
      if (empty($userPlaylists)) {
        echo "
        <li class='playlist-item'>
          <p class='placeholder'>
            You haven't made any playlists yet
          </p>
        </li>
        ";
      } else {
        foreach ($userPlaylists as $playlist) {
          ?>
          <li class="playlist-item">
            <div class="playlist-info">
              <div class="playlist-header">
                <a href="/playlist/<?php echo $playlist["playlistId"]; ?>"><h3 class="playlist-title"><?php echo $playlist["name"]; ?></h3></a>
                <span class="playlist-privacy"> <?php echo $playlist["privacy"]; ?></span>
              </div>
              <div class="playlist-description">
                <p>
                  <?php echo $playlist["description"]; ?>
                </p>
              </div>
            </div>
            <div class="playlist-control">
              <form method="post" action="/delete/<?php echo $playlist["playlistId"]; ?>">
                <button name="delete">Delete</button>
              </form>
              <form method="get" action="/edit/<?php echo $playlist["playlistId"]; ?>">
                <button name="edit">Edit</button>
              </form>
            </div>
          </li>
          <?php
        }
      }
      ?>
      <li class="playlist-item new-item">
        <a href="/create-playlist"><h3 class="playlist-title new-btn"> + Add New Playlist</h3></a>
      </li>
    </ul>

    <h2>Saved Playlists</h2>
    <ul class="saved-playlists">
      <?php
      if (empty($savedPlaylists)) {
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
