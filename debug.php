<?php

require_once(__DIR__ . '/config.php');
  
$url = 'https://graph.facebook.com/v6.0/' . $instagramBusinessId . '?fields=name,media{caption,like_count,media_url,permalink,timestamp,username}&access_token=' . $accessToken;

/* test */
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

$data = json_decode($result, true);

echo('<pre>');
var_dump($data);
echo('</pre>');



/*if($_SERVER['REQUEST_METHOD'] == 'POST'){
  echo @file_get_contents($url);
  exit;
}*/
