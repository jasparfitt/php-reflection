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
        <?php
      }
    ?>
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
<script> let remove = false;</script>
<?php
  include __DIR__."/../inc/save-code.php";
  include __DIR__."/../inc/like-code.php";
?>
