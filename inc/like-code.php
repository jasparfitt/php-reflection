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
    let NoL = document.getElementById(`NoL${id}`);
    let litNoL
    if (NoL) {
      litNoL = parseInt(NoL.innerHTML.substr(7));
    }
    if (likeButton.getAttribute('class') == "disabled") {
      likeButton.innerHTML = "Like";
      likeButton.classList.remove("disabled");
      if (NoL) {
        NoL.innerHTML = `Likes: ${litNoL - 1}`;
      }
    } else {
      likeButton.innerHTML = "Liked";
      likeButton.classList.add("disabled");
      if (NoL) {
        NoL.innerHTML = `Likes: ${litNoL + 1}`;
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
