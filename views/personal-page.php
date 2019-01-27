<?php
include __DIR__.'/../inc/connection.php';
try {
  $result = $db->prepare("SELECT name, description, privacy, playlistId FROM playlists WHERE userId = ?;");
  $result->bindParam(1, $userId, PDO::PARAM_STR);
  $result->execute();
  $userPlaylists = $result->fetchAll(PDO::FETCH_ASSOC);
  $result = $db->prepare("
    SELECT name, description, saves.playlistId, username, userLikes.userId AS userLikes FROM saves
    INNER JOIN playlists ON playlists.playlistId = saves.playlistId
    INNER JOIN users ON saves.userId = users.userId
    LEFT OUTER JOIN (
      SELECT * FROM likes WHERE userId = ?
    ) AS userLikes ON userLikes.playlistId = saves.playlistId
    WHERE (saves.userId = ? && privacy = 'public' && playlists.userId != ?) || (saves.userId = ? && playlists.userId = ?);
  ");
  $result->bindParam(1, $userId, PDO::PARAM_STR);
  $result->bindParam(2, $userId, PDO::PARAM_STR);
  $result->bindParam(3, $userId, PDO::PARAM_STR);
  $result->bindParam(4, $userId, PDO::PARAM_STR);
  $result->bindParam(5, $userId, PDO::PARAM_STR);
  $result->execute();
  $savedPlaylists = $result->fetchAll(PDO::FETCH_ASSOC);
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
    <ul class="user-playlists playlists">
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
    <ul class="saved-playlists playlists">
      <?php
      if (empty($savedPlaylists)) {
        echo "
        <li>
          <p class='placeholder'>
            You haven't saved any playlists yet
          </p>
        </li>
        ";
      } else {
        foreach ($savedPlaylists as $playlist) {
          ?>
          <li class="playlist-item">
            <div class="playlist-info">
              <div class="playlist-header">
                <a href="/playlist/<?php echo $playlist["playlistId"]; ?>"><h3 class="playlist-title"><?php echo $playlist["name"]; ?></h3></a>
              </div>
              <div class="playlist-description">
                <p>
                  <?php echo $playlist["description"]; ?>
                </p>
              </div>
            </div>
            <div class="playlist-control">
              <form method="post" action="/like/<?php echo $playlist["playlistId"]; ?>">
                <button name="delete" class="<?php if (isset($playlist["userLikes"])) {echo "disabled";} ?>">
                  <?php
                    if (isset($playlist["userLikes"])) {
                      echo "Liked";
                    } else {
                      echo "Like";
                    }
                  ?>
                </button>
                <input type="hidden" name="redirect" value="undefined-personal-page">
                <input type="hidden" name="pattern-key" value="">
                <input type="hidden" name="pattern-value" value="">
              </form>
              <form method="post" action="/save/<?php echo $playlist["playlistId"]; ?>">
                <button name="edit" class="<?php if (isset($playlist["userSaves"])) {echo "disabled";} ?>">
                  X
                </button>
                <input type="hidden" name="redirect" value="undefined-personal-page">
                <input type="hidden" name="pattern-key" value="">
                <input type="hidden" name="pattern-value" value="">
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
