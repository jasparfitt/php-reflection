<?php
// create temp table to store playlist songs
$db->query("
CREATE TEMPORARY TABLE MyTempTable
(
  trackName text,
  artistName text,
  link text
)
");
// add songs to temp table
foreach ($cleanTracks as $track ) {
  if(empty($track['link'])) {
    $link = null;
  } else {
    $link = $track['link'];
  }
  $result = $db->prepare("
  INSERT INTO MyTempTable (trackName, artistName, link) VALUES (?, ?, ?);
  ");
  $result->bindParam(1, $track['title'], PDO::PARAM_STR);
  $result->bindParam(2, $track['artist'], PDO::PARAM_STR);
  $result->bindParam(3, $link, PDO::PARAM_STR);
  $result->execute();
}
// find songs alredy in tracks table
$result = $db->query("
  SELECT MyTempTable.trackName AS track, MyTempTable.artistName AS artist, tracks.trackId AS trackId, tracks.spotifyLink AS link FROM MyTempTable
  INNER JOIN tracks AS tracks1 ON MyTempTable.trackName = tracks1.trackName
  INNER JOIN tracks ON MyTempTable.artistName = tracks.artistName
  WHERE tracks1.trackId = tracks.trackId;
");
$presentSongs = $result->fetchAll(PDO::FETCH_ASSOC);

$songsToAdd = array();
$songsToUpdate = array();
$songsAlreadyIn = array();
// seperate songs in playlist into songs to add to database, songs to be updated, and songs already in database
foreach ($uniqueTracks as $track) {
  $songsToAdd[] = $track;
  foreach($presentSongs as $noAdd) {
    if ($track['title'] == $noAdd['track'] && $track['artist'] == $noAdd['artist']) {
      array_pop($songsToAdd);
      if (!empty($track['link']) && empty($noAdd['link'])) {
        $noAdd['link'] = $track["link"];
        $songsToUpdate[] = $noAdd;
      } else {
        $songsAlreadyIn[] = $noAdd;
      }
      break;
    }
  }
}
// add new songs to database
foreach ($songsToAdd as $song) {
  if(empty($song['link'])) {
    $link = null;
  } else {
    $link = $song['link'];
  }
  $result = $db->prepare("
  INSERT INTO Tracks (trackName, artistName, spotifyLink) VALUES (?, ?, ?);
  ");
  $result->bindParam(1, $song['title'], PDO::PARAM_STR);
  $result->bindParam(2, $song['artist'], PDO::PARAM_STR);
  $result->bindParam(3, $link, PDO::PARAM_STR);
  $result->execute();
}

// update existing songs in database
foreach ($songsToUpdate as $song) {
  $result = $db->prepare("UPDATE tracks SET spotifyLink = ? WHERE trackId = ?;");
  $result->bindParam(1, $song['link'], PDO::PARAM_STR);
  $result->bindParam(2, $song['trackId'], PDO::PARAM_INT);
  $result->execute();
}

// find list of track ids
$result = $db->query("
  SELECT tracks.trackId AS trackId FROM MyTempTable
  INNER JOIN tracks AS tracks1 ON MyTempTable.trackName = tracks1.trackName
  INNER JOIN tracks ON MyTempTable.artistName = tracks.artistName
  WHERE tracks1.trackId = tracks.trackId;
");
$idList = $result->fetchAll(PDO::FETCH_ASSOC);
?>
