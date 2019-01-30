<?php
if (!isset($_COOKIE["playlist"])) {
  $title = '';
  $description = '';
  $tracks = array();
  $privacy = "public";
} else {
  session_name('playlist');
  session_id("playlist");
  session_set_cookie_params(3600,'/',getenv("COOKIE_DOMAIN"));
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
include __DIR__.'/../inc/error-message.php';
?>
<main>
  <div class='margin-box'>
    <h1><?php echo $name; ?></h1>
      <form method="post" action="<?php echo $postTo; ?>">
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
                <input class="title-input" name="title" type="text" placeholder="Title" value="<?php echo $title; ?>"/>
              </td>
            </tr>
            <tr>
              <td>
                <textarea class="description-input" name='description' placeholder="Description"><?php echo $description; ?></textarea>
              </td>
            </tr>
            <?php foreach ($tracks as $key => $track) { ?>
              <tr id="<?php echo $key; ?>">
                <td id="track">
                  <input type="hidden" value="<?php echo implode("~#~", $track); ?>" name="tracks[]"/>
                  <label for "tracks[]"><?php echo $track["title"]." by ".$track["artist"] ?></label>
                  <button class="delete-track" id="button" onclick="deleteTrack('<?php echo $key; ?>')"><i class="fas fa-times"></i></button>
                </td>
              </tr>
            <?php } ?>
          </tbody>
          <tfoot>
            <tr>
              <td id="new-track">
                <?php
                if (isset($_COOKIE['form'])) {
                  if ($_COOKIE['form'] == "spotify") {
                    ?>
                    <span id="inputs">
                      <button onclick="getTrack(event)" id="add">
                        <i class="fas fa-plus"></i>
                      </button>
                      <input id="spotify" name="spotify" type="text" placeholder="Spotify URI" class="spotify-input" value="<?php echo $URI; ?>">
                      <button class="back-btn" onclick="goBack()">
                        <i class="fas fa-times"></i>
                      </button>
                    </span>
                    <?php
                  } else if ($_COOKIE['form'] == "manual") {
                    ?>
                    <span id="inputs">
                      <button onclick="getTrack(event)" id="add">
                        <i class="fas fa-plus"></i>
                      </button>
                      <input id="track-name" name="track-name" type="text" placeholder="Song Title" value="<?php echo $trackName; ?>">
                      <input id="artist-name" name="artist-name" type="text" placeholder="Artist" value="<?php echo $artistName; ?>">
                      <button class="back-btn" onclick="goBack()">
                        <i class="fas fa-times"></i>
                      </button>
                    </span>
                    <?php
                  }
                } else {
                  ?>
                  <span id="buttons">
                    <i class="fas fa-plus"></i> New track
                    <button class="input-btn" onclick="showSpotifyInput()" id="spotify">spotify URI</button>
                    <button class="input-btn" onclick="showManualInput()">Manually</button>
                  </span>
                  <?php
                }
                destroyCookie("form");
                destroyCookie("URI");
                destroyCookie("trackName");
                destroyCookie("artistName");
                ?>
              </td>
            </tr>
          </tfoot>
        </table>
        <input id="finish-btn" <?php if (empty($tracks)) {echo 'disabled';} ?> class="submit-btn" type="submit" onclick="overlay()" value="Save Playlist" name="finish"/>
        <input type="hidden" name="redirect" value="<?php echo $redirect; ?>">
        <input type="hidden" name="pattern-key" value="<?php echo $pattern_key; ?>">
        <input type="hidden" name="pattern-value" value="<?php echo $pattern_value; ?>">
      </form>
  </div>
</main>
<?php include __DIR__."/../inc/error-message-code.php"; ?>
<script>
  let newTrack = document.getElementById('new-track');
  let spotify = document.getElementById('spotify');
  let playlistLength = 0;
  let newId = 1;

  let overlay = () => {
    let body = document.getElementById("body");
    let overlay = document.createElement("div");
    let message = "";
    overlay.innerHTML = "<div class='overlay-info'>Saving Playlist </div><div></div>";
    let dots = 0;
    overlay.setAttribute("class","overlay");
    body.appendChild(overlay);
    window.setInterval(() => {
      if (dots < 3) {
        message += ".";
        dots ++;
      } else {
        message = "";
        dots = 0;
      }
      overlay.innerHTML = "<div class='overlay-info'>Saving Playlist </div><div class='overlay-left'>"+message+"</div>";
    }, 1000)
  }

  let showSpotifyInput = () => {
    remove(newTrack, 'buttons');
    let uriInput = makeInput('spotify',"Spotify URI");
    uriInput.setAttribute("class", "spotify-input");
    let backButton = makeBackButton();
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
    backButton.innerHTML = "<i class='fas fa-times'></i>";
    backButton.setAttribute('onclick',"goBack()");
    backButton.setAttribute('class',"back-btn");
    return backButton;
  }

  let makeConfirmButton = () => {
    let confirmButton = document.createElement('button');
    confirmButton.setAttribute('name',"add-track");
    confirmButton.setAttribute('onclick',"getTrack(event)");
    confirmButton.innerHTML = "<i class='fas fa-plus'></i>";
    confirmButton.setAttribute('class',"confirm-btn");
    return confirmButton;
  }

  let makeInput = (name, placeholder) => {
    let input = document.createElement('input');
    input.setAttribute("id",name);
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
    buttons.innerHTML = `
    <i class='fas fa-plus'></i> New track
    <button class="input-btn" onclick='showSpotifyInput()' id='spotify'>spotify URI</button>
    <button class="input-btn" onclick='showManualInput()'>Manually</button>
    `;
    newTrack.appendChild(buttons);
  }

  let deleteTrack = (id) => {
    let trackList = document.getElementById("track-list");
    remove(trackList, id);
  }

  let httpRequest;

  let getTrack = (e) => {
    e.preventDefault();
    let tracks = [];
    let spotify;
    let trackName;
    let artistName;
    let method;
    let form = e.path.find((element) => {
      return element.tagName == "FORM";
    })
    if (form) {
      for (let input of form) {
        if (input.name == "tracks[]") {
          tracks.push(input.value);
        }
        if (input.name == "spotify") {
          spotify = input.value;
          method = "spotify";
        }
        if (input.name == "track-name") {
          trackName = input.value;
          method = "manual";
        }
        if (input.name == "artist-name") {
          artistName = input.value;
          method = "manual";
        }
      }
    }
    tracks=JSON.stringify(tracks);
    tracks = tracks.replace(/&/g, "%26");
    httpRequest = new XMLHttpRequest;
    if (!httpRequest) {
      alert("no instance found");
    }
    httpRequest.onreadystatechange = alertContents;
    httpRequest.open("POST", "http://netdev.firefly.co.uk/add-track");
    httpRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    httpRequest.send("method="+method+"&spotify="+spotify+"&tracks="+tracks+"&track-name="+trackName+"&artist-name="+artistName);
  }

  let alertContents = () => {
    if (httpRequest.readyState === XMLHttpRequest.DONE) {
      if (httpRequest.status === 200) {
        console.log("response: "+httpRequest.responseText);
        request = JSON.parse(httpRequest.responseText);
        if (request.error) {
          alert(request.error);
        }
        if (request.success) {
          let finish = document.getElementById("finish-btn");
          finish.removeAttribute("disabled");
          for (track of request.tracks) {
            let title = track.title;
            let artist = track.artist;
            let link = track.link;
            let uri = document.getElementById("spotify");
            let trackName = document.getElementById("track-name");
            let artistName = document.getElementById("artist-name");
            if (uri) {
              uri.value = '';
            }
            if (trackName) {
              trackName.value = "";
              artistName.value = "";
            }
            let playlist = document.getElementById("track-list");
            let tr = document.createElement('tr');
            tr.setAttribute("id", `new${newId}`);
            let td = document.createElement('td');
            td.setAttribute("id", "track");
            let input = document.createElement('input');
            input.setAttribute("type", "hidden");
            input.setAttribute("value", [title, artist, link].join("~#~"));
            input.setAttribute("name", "tracks[]");
            let button = document.createElement('button');
            button.setAttribute("id",`delete`);
            button.setAttribute("class","delete-track");
            button.setAttribute("onclick", `deleteTrack('new${newId}')`)
            button.innerHTML = "<i class='fas fa-times'></i>";
            let label = document.createElement('label');
            label.setAttribute("for", "tracks[]");
            label.innerHTML = `${title} by ${artist}`;
            newId ++
            td.appendChild(input);
            td.appendChild(label);
            td.appendChild(button);
            tr.appendChild(td);
            playlist.appendChild(tr);
          }
        }
      } else {
        alert('There was a problem with the request.');
      }
    }
  }
</script>
