<?php
include __DIR__.'/../inc/connection.php';
try {
  $result = $db->prepare("
    SELECT name, description, privacy, playlists.playlistId, COUNT(likes.userId) AS NoL FROM playlists
    LEFT OUTER JOIN likes on likes.playlistId = playlists.playlistId
    WHERE playlists.userId = ?
    GROUP BY playlists.playlistId;");
  $result->bindParam(1, $userId, PDO::PARAM_STR);
  $result->execute();
  $userPlaylists = $result->fetchAll(PDO::FETCH_ASSOC);
  $result = $db->prepare("
    SELECT name, description, saves.playlistId, owners.username, userLikes.userId AS userLikes, COUNT(likes.userId) AS NoL FROM playlists
    INNER JOIN (
      SELECT * FROM saves WHERE userId = ?
    ) AS saves ON playlists.playlistId = saves.playlistId
    INNER JOIN users AS owners ON playlists.userId = owners.userId
    LEFT OUTER JOIN likes ON likes.playlistId = playlists.playlistId
    LEFT OUTER JOIN (
      SELECT * FROM likes WHERE userId = ?
    ) AS userLikes ON userLikes.playlistId = playlists.playlistId
    WHERE (privacy = 'public'  || playlists.userId = ?)
    GROUP BY playlists.playlistId;
  ");
  $result->bindParam(1, $userId, PDO::PARAM_STR);
  $result->bindParam(2, $userId, PDO::PARAM_STR);
  $result->bindParam(3, $userId, PDO::PARAM_STR);
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
    <ul id="user-playlists" class="user-playlists playlists">
      <?php
      if (empty($userPlaylists)) {
        echo "
        <li class='playlist-item'>
          <p class='placeholder'>
            You don't have any playlists to show
          </p>
        </li>
        ";
      } else {
        $playlist_control = null;
        $playlist_privacy = true;
        $playlist_edit = true;
        foreach ($userPlaylists as $playlist) {
          include __DIR__."/../inc/playlist-item.php";
        }
      }
      ?>
      <li class="playlist-item new-item">
        <a href="/create-playlist"><h3 class="playlist-title new-btn"> + Add New Playlist</h3></a>
      </li>
    </ul>

    <h2>Saved Playlists</h2>
    <ul id="saved-playlists" class="saved-playlists playlists" id="saved-playlists">
      <?php
      if (empty($savedPlaylists)) {
        echo "
        <li>
          <p class='placeholder'>
            You haven't got any saved playlists

          </p>
        </li>
        ";
      } else {
        $playlist_control = true;
        $playlist_privacy = null;
        $playlist_edit = null;
        $remove = true;
        foreach ($savedPlaylists as $playlist) {
          include __DIR__."/../inc/playlist-item.php";
        }
      }
      ?>
    </ul>
  </div>
</main>
<script> let remove = true;</script>
<?php
  include __DIR__."/../inc/save-code.php";
  include __DIR__."/../inc/like-code.php";
?>
