<script>
  let likeRequest;

  let onLike = (e, id) => {
    e.preventDefault();
    likeRequest = new XMLHttpRequest;
    if (!likeRequest) {
      alert("no instance found");
    }
    likeRequest.onreadystatechange = likeResponse;
    likeRequest.open("POST", `http://netdev.firefly.co.uk/like/${id}`);
    likeRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    likeRequest.send();
    let likeButton = document.getElementById(`like${id}`);
    let userPlaylists = document.getElementById("user-playlists");
    let NoL;
    let NoLs;
    if (userPlaylists) {
      NoLs = document.getElementsByClassName(`NoL${id}`)
    } else {
      NoL = document.getElementById(`NoL${id}`);
    }
    let litNoL
    if (NoL) {
      litNoL = parseInt(NoL.innerHTML.substr(7));
    } else if (NoLs) {
      litNoL = parseInt(NoLs[0].innerHTML.substr(7));
    }
    if (likeButton.getAttribute('class').includes("disabled")) {
      likeButton.classList.remove("disabled");
      if (NoL) {
        NoL.innerHTML = `Likes: ${litNoL - 1}`;
      } else if (NoLs) {
        for (NoL of NoLs) {
          NoL.innerHTML = `Likes: ${litNoL - 1}`;
        }
      }
    } else {
      likeButton.classList.add("disabled");
      if (NoL) {
        NoL.innerHTML = `Likes: ${litNoL + 1}`;
      } else if (NoLs) {
        for (NoL of NoLs) {
          NoL.innerHTML = `Likes: ${litNoL + 1}`;
        }
      }
    }
  }

  let likeResponse = () => {
    if (likeRequest.readyState === XMLHttpRequest.DONE) {
      if (likeRequest.status === 200) {
        response = JSON.parse(likeRequest.responseText)
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
