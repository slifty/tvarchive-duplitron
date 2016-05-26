<?php

  if(sizeof($argv) < 2) {
    echo("Please pass a path to a json file as a command line parameter");
    die();
  }

  $path = $argv[1];
  $json_content = file_get_contents($path);
  $data = json_decode($json_content);

  $columns = array(
    "subject_media",
    "stored_media",
    "subject_start",
    "stored_start",
    "duration",
    "consecutive_hashes",
    "common_hashes",
    "match_url"
  );

  $csv_file = fopen("output.csv", "w");

  fputcsv($csv_file, $columns);
  $subject_media = $data->media_id;
  foreach($data->result->data->matches->corpus as $match) {
    $row = array(
      $subject_media,
      $match->destination_media->external_id,
      $match->start,
      $match->target_start,
      $match->duration,
      $match->consecutive_hashes,
      $match->common_hashes,
      "https://archive.org/details/".$match->destination_media->external_id."#start/".$match->target_start."/end/".($match->target_start + $match->duration)
    );
    fputcsv($csv_file, $row);
  }

  fclose($csv_file);

?>
