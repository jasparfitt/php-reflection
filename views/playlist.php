<?php include __DIR__.'/../inc/header.php'; ?>
<main>
  <div class="margin-box">
    <h1><?php echo $playlist[0]["name"]; ?></h1>
    <?php
      if ($playlist[0]["username"] == getUsername()) {
        ?>
        <form method="post" action="/delete/<?php echo $playlist[0]["playlistId"]; ?>">
          <input name="delete" type="submit" value="Delete">
        </form>
        <form method="get" action="/edit/<?php echo $playlist[0]["playlistId"]; ?>">
          <input name="edit" value="Edit" type="submit">
        </form>
        <form method="post" action="/like/<?php echo $playlist[0]["playlistId"]; ?>">
          <input name="like" value="<?php
            if (isset($playlist[0]["userLikes"])) {
              echo "Liked";
            } else {
              echo "Like";
            }
          ?>" type="submit" class="<?php if (isset($playlist[0]["userLikes"])) {echo "disabled";} ?>">
          <input type="hidden" name="redirect" value="playlist">
          <input type="hidden" name="pattern-key" value="id">
          <input type="hidden" name="pattern-value" value="<?php echo $playlist[0]["playlistId"]; ?>">
        </form>
        <form method="post" action="/save/<?php echo $playlist[0]["playlistId"]; ?>">
          <input name="save" value="<?php
            if (isset($playlist[0]["userSaves"])) {
              echo "Saved";
            } else {
              echo "Save";
            }
          ?>" type="submit" class="<?php if (isset($playlist[0]["userSaves"])) {echo "disabled";} ?>">
          <input type="hidden" name="redirect" value="playlist">
          <input type="hidden" name="pattern-key" value="id">
          <input type="hidden" name="pattern-value" value="<?php echo $playlist[0]["playlistId"]; ?>">
        </form>
        <?php
      }
    ?>
    <span>By <?php echo $playlist[0]["username"]; ?>,</span>
    <span><?php echo $playlist[0]["privacy"]; ?></span>
    <p>
      <?php echo $playlist[0]["description"]; ?>
    </p>
    <table>
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
