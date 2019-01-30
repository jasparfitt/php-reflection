<button id="save<?php echo $playlist["playlistId"] ?>" onclick="onSave(event, <?php echo $playlist["playlistId"] ?>)" name="save" class="playlist-btn save-btn <?php if (isset($playlist["userSaves"])) {echo "disabled";} if (isset($remove)) {echo "remove-btn";}?>">
  <?php
    if (isset($remove)) {
      echo "<i class='fas fa-times'></i>";
    } else {
      if (isset($playlist["userSaves"])) {
        echo "Saved";
      } else {
        echo "Save";
      }
    }
  ?>
</button>
