<?php
if (!isset($_COOKIE["playlist"])) {
  $title = '';
  $description = '';
  $tracks = array();
  $privacy = "public";
} else {
  session_name('playlist');
  session_id("playlist");
  session_start();
  $title = $_SESSION['playlist']['title'];
  $description = $_SESSION['playlist']['description'];
  $tracks = $_SESSION['playlist']['tracks'];
  $privacy = $_SESSION['playlist']['privacy'];
  session_write_close();
  destroyCookie("playlist");
}

$URI = '';
$trackName = '';
$artistName = '';
if (isset($_COOKIE['URI'])) {
  $URI = $_COOKIE['URI'];
}
if (isset($_COOKIE["trackName"])) {
  $trackName = $_COOKIE['trackName'];
}
if (isset($_COOKIE["artistName"])) {
  $artistName = $_COOKIE['artistName'];
}

include __DIR__.'/../inc/header.php';
?>
<main>
  <div class='margin-box'>
    <h1>Add a New Playlist</h1>
    <?php if(isset($_COOKIE["msg"])) { ?>
    <p class="error-msg">
      <?php
      echo $_COOKIE["msg"];
      destroyCookie('msg');
      ?>
    </p>
    <?php }?>
      <form method="post" action="/create-playlist">
        <table>
          <tbody id="track-list">
            <tr>
              <td>
                <input type="radio" id="public" name="privacy" value="public" <?php if ($privacy == "public") {echo "checked";}?>/>
                <label for="private">Public</label>
                <input type="radio" id="private" name="privacy" value="private" <?php if ($privacy == "private") {echo "checked";} ?>/>
                <label for="private">Private</label>
              </td>
            </tr>
            <tr>
              <td>
                <input name="title" type="text" placeholder="Title" value="<?php echo $title; ?>"/>
              </td>
            </tr>
            <tr>
              <td>
                <textarea name='description' placeholder="Description"><?php echo $description; ?></textarea>
              </td>
            </tr>
            <?php foreach ($tracks as $key => $track) { ?>
              <tr id="<?php echo $key; ?>">
                <td id="track">
                  <input type="hidden" value="<?php echo implode("~#~", $track); ?>" name="tracks[]"/>
                  <label for "tracks[]"><?php echo $track["title"]." by ".$track["artist"] ?></label>
                  <button id="button" onclick="deleteTrack('<?php echo $key; ?>')">X</button>
                </td>
              </tr>
            <?php } ?>
            <tr>
              <td id="new-track">
                <?php
                if (isset($_COOKIE['form'])) {
                  if ($_COOKIE['form'] == "spotify") {
                    ?>
                    <span id="inputs"><input type="submit" name="add-track" value="+"><input name="spotify" type="text" placeholder="Spotify URI" class="spotify-input" value="<?php echo $URI; ?>"><button onclick="goBack()">X</button></span>
                    <?php
                  } else if ($_COOKIE['form'] == "manual") {
                    ?>
                    <span id="inputs"><input type="submit" name="add-track" value="+"><input name="track-name" type="text" placeholder="Song Title" value="<?php echo $trackName; ?>"><input name="artist-name" type="text" placeholder="Artist" value="<?php echo $artistName; ?>"><button onclick="goBack()">X</button></span>
                    <?php
                  }
                } else {
                  ?>
                  <span id="buttons">+ New track <button onclick="showSpotifyInput()" id="spotify">spotify URI</button> <button onclick="showManualInput()">Manually</button></span>
                  <?php
                }
                destroyCookie("form");
                destroyCookie("URI");
                destroyCookie("trackName");
                destroyCookie("artistName");
                ?>
              </td>
            </tr>
          </tbody>
        </table>
        <input type="submit" value="Save Playlist" name="finish"/>
      </form>
  </div>
</main>
<script>
  let newTrack = document.getElementById('new-track');
  let spotify = document.getElementById('spotify');
  let playlistLength = 0;

  let showSpotifyInput = () => {
    remove(newTrack, 'buttons');
    let uriInput = makeInput('spotify',"Spotify URI");
    uriInput.setAttribute("class", "spotify-input");
    let backButton =makeBackButton();
    let inputHolder = makeHolder("inputs");
    let confirmButton = makeConfirmButton();
    inputHolder.appendChild(confirmButton);
    inputHolder.appendChild(uriInput);
    inputHolder.appendChild(backButton);
    newTrack.appendChild(inputHolder);
  }

  let showManualInput = () => {
    remove(newTrack, 'buttons');
    let form = makeForm();
    let trackInput = makeInput('track-name',"Song Title");
    let artistInput = makeInput('artist-name', "Artist");
    let backButton =makeBackButton();
    let inputHolder = makeHolder("inputs");
    let confirmButton = makeConfirmButton();
    inputHolder.appendChild(confirmButton);
    inputHolder.appendChild(trackInput);
    inputHolder.appendChild(artistInput);
    inputHolder.appendChild(backButton);
    newTrack.appendChild(inputHolder);
  }

  let makeHolder = (name) => {
    let inputs = document.createElement('span');
    inputs.setAttribute("id",name);
    return inputs;
  }

  let makeForm = () => {
    let form = document.createElement('form');
    form.setAttribute("method", "post");
    form.setAttribute("action","/add");
    return form;
  }

  let makeBackButton = () => {
    let backButton = document.createElement('button');
    backButton.innerHTML = "X";
    backButton.setAttribute('onclick',"goBack()");
    return backButton;
  }

  let makeConfirmButton = () => {
    let confirmButton = document.createElement('input');
    confirmButton.setAttribute('type',"submit");
    confirmButton.setAttribute('name',"add-track");
    confirmButton.setAttribute('value',"+");
    return confirmButton;
  }

  let makeInput = (name, placeholder) => {
    let input = document.createElement('input');
    input.setAttribute("name",name);
    input.setAttribute("type","text");
    input.setAttribute("placeholder",placeholder);
    return input;
  }

  let remove = (newTrack, name) => {
    let item = document.getElementById(name);
    newTrack.removeChild(item);
  }

  let goBack = () => {
    remove(newTrack, "inputs");
    let buttons = makeHolder("buttons");
    buttons.innerHTML = "+ New track <button onclick='showSpotifyInput()' id='spotify'>spotify URI</button> <button onclick='showManualInput()'>Manually";
    newTrack.appendChild(buttons);
  }

  let deleteTrack = (id) => {
    console.log("deleting")
    // let track = document.getElementById("track");
    // remove(track,"button");
    let trackList = document.getElementById("track-list");
    remove(trackList, id);
  }

</script>
