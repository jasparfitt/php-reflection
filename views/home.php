  <?php
  include __DIR__.'/../inc/header.php';
  ?>
  <main>
    <div class="margin-box">
      <h1><?php echo $title; ?></h1>
      <ul class="popular-playlists playlists">
        <?php
        if (empty($playlists)) {
          echo "
          <li class='playlist-item'>
            <p class='placeholder'>
              No playlists to show.
            </p>
          </li>
          ";
        } else {
          $playlist_control = true;
          $playlist_privacy = null;
          $playlist_edit = null;
          foreach ($playlists as $playlist) {
            include __DIR__."/../inc/playlist-item.php";
          }
        }
        ?>
      </ul>
      <?php
      if (isset($numPages)) {
        $start = $pageNum - 2;
        $end = $pageNum + 2;
        if ($start < 1) {
          $start = 1;
        }
        if ($end > $numPages) {
          $end = $numPages;
        }
        $pages = range($start, $end);
        if ($start != 1) {
          $pages["first-ellip"] = "...";
          $pages["first-page"] = 1;
        }
        if ($end != $numPages) {
          $pages["last-ellip"] = "...";
          $pages["last-page"] = $numPages;
        }
      ?>
        <nav class="pagination">
          <?php foreach ($pages as $key => $page) { ?>
            <li
              class="pages <?php
                if (!is_int($key)) {
                  echo $key;
                } else {
                  echo "page".($page-$pageNum);
                }
              ?>"
            >
              <span>
                <?php
                if ($page != "..." && $page != $pageNum) {
                  echo "<a href=/$page?search=$searchTerm>$page</a>";
                } else {
                  echo $page;
                }
                ?>
              </span>
            </li>
          <?php } ?>
        </nav>
      <?php } ?>
    </div>
  </main>
  <script> let remove = false;</script>
<?php
  include __DIR__."/../inc/save-code.php";
  include __DIR__."/../inc/like-code.php";
?>
