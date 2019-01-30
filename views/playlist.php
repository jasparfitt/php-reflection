<?php include __DIR__.'/../inc/header.php'; ?>
<main>
  <div class="margin-box playlist-page">
    <div class="playlist-top">
      <div class="playlist-header">
        <h1 class="playlist-title"><?php echo $playlist[0]["name"]; ?></h1>
        <span class="playlist-author">by <?php echo $playlist[0]["username"]; ?>, <?php echo $playlist[0]["privacy"]; ?></span>
      </div>
      <div class="playlist-controls">
        <?php
        $tempPlaylist = $playlist;
        $playlist = $tempPlaylist[0];
          if ($playlist["username"] == getUsername()) {
            include __DIR__."/../inc/edit-button.php";
            include __DIR__."/../inc/delete-button.php";
          }
          include __DIR__."/../inc/save-button.php";
          include __DIR__."/../inc/like-button.php";
          $playlist = $tempPlaylist
        ?>
      </div>
    </div>
    <div class="playlist-description">
      <p>
        <?php echo $playlist[0]["description"]; ?>
      </p>
    </div>
    <table class="playlist-tracks">
      <thead>
        <tr>
          <th>
            Title
          </th>
          <th>
            Artist
          </th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($playlist as $track ) { ?>
          <tr>
            <td>
              <?php if (isset($track["spotifyLink"])) { ?>
                <a href="<?php echo $track["spotifyLink"]; ?>" target="_blank">
              <?php } ?>
              <span><?php echo $track["trackName"]; ?> </span>
              <?php if (isset($track["spotifyLink"])) { ?>
              </a>
              <?php } ?>
            </td>
            <td>
              <span><?php echo $track["artistName"]; ?> </span>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</main>
<script> let remove = false;</script>
<?php
  include __DIR__."/../inc/save-code.php";
  include __DIR__."/../inc/like-code.php";
?>
