<button id="like<?php echo $playlist["playlistId"] ?>" onclick="onLike(event, <?php echo $playlist["playlistId"] ?>)" name="like" class="playlist-btn like-btn <?php if (isset($playlist["userLikes"])) {echo "disabled";}?>">
  <i class="fas fa-thumbs-up"></i>
</button>
