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
          <input name="like" value="Like" type="submit" class="<?php if (isset($playlist["userLikes"])) {echo "disabled";} ?>">
        </form>
        <form method="post" action="/save/<?php echo $playlist[0]["playlistId"]; ?>">
          <input name="save" value="Save" type="submit">
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
