<script>
  let saveRequest;

  let onSave = (e, id) => {
    e.preventDefault();
    saveRequest = new XMLHttpRequest;
    if (!saveRequest) {
      alert("no instance found");
    }
    saveRequest.onreadystatechange = saveResponse;
    saveRequest.open("POST", `http://netdev.firefly.co.uk/save/${id}`);
    saveRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    saveRequest.send();
    let saveButton = document.getElementById(`save${id}`);
    if (remove) {
      let savedPlaylists = document.getElementById("saved-playlists");
      let playlist = document.getElementById(id);
      savedPlaylists.removeChild(playlist);
      console.log(savedPlaylists.children);
      if (savedPlaylists.children.length == 0) {
        let placeholderItem = document.createElement("li");
        placeholderItem.innerHTML = "<p class='placeholder'>You don't have any saved playlists</p>";
        savedPlaylists.appendChild(placeholderItem);
      }
    } else {
      if (saveButton.getAttribute('class') == "disabled") {
        saveButton.innerHTML = "Save";
        saveButton.classList.remove("disabled");
      } else {
        saveButton.innerHTML = "Saved";
        saveButton.classList.add("disabled");
      }
    }
  }

  let saveResponse = () => {
    if (saveRequest.readyState === XMLHttpRequest.DONE) {
      if (saveRequest.status === 200) {
        response = JSON.parse(saveRequest.responseText)
        if (response.goTo == "login") {
          document.cookie = "redirect=playlist; domain=<?php echo getenv("COOKIE_DOMAIN");?>; path=/";
          document.cookie = "patternKey=id; domain=<?php echo getenv("COOKIE_DOMAIN");?>; path=/";
          document.cookie = `patternValue=${response.playlistId}; domain=<?php echo getenv("COOKIE_DOMAIN");?>; path=/`;
          window.location = "/login";
        };
      } else {
        alert('There was a problem with the request.');
      }
    }
  }
</script>
