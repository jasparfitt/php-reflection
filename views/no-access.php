<?php include __DIR__.'/../inc/header.php'; ?>
<main>
  <div class="margin-box">
    <h1>Error 403</h1>
    <h3>You dont have access to this page.</h3>
    <p>
      Why not
      <?php if (isset($_COOKIE['access_token'])) { ?>
       manage your playlists on your <a href="/personal-page">personal page</a> or
      <?php }?>
       check out some new playlists on the <a href="/home">homepage</a> instead?
    </p>
  </div>
</main>
