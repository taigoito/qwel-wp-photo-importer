<?php
/*
Plugin Name: QWEL Photo Importer
Plugin URI: 
Description: Photo Importer.
Version: 1.0
Author: taigoito
Author URI: https://qwel.design/
*/

//
// Initialize
//

function QWEL_Import() {
  $users = get_users(['orderby' => 'ID']);
  $keyword = ''; // Set keyword
  foreach ($users as $user) {
    $instagramBusinessId = get_user_meta($user->ID, 'instagrambusinessid', true);
    $accessToken = get_user_meta($user->ID, 'accesstoken', true);
    if ($instagramBusinessId && $accessToken) {
      import($user->ID, $instagramBusinessId, $accessToken, $keyword);
    }
  }
}
register_activation_hook(__FILE__, 'QWEL_Import');

//
// Cron
//

function QWEL_ImportCronStart() {
  wp_schedule_event(time(), 'twicedaily', 'QWEL_ImportCron');
}
register_activation_hook(__FILE__, 'QWEL_ImportCronStart');
add_action('QWEL_ImportCron', 'QWEL_Import');

function QWEL_ImportCronStop() {
  wp_clear_scheduled_hook('QWEL_ImportCron');
}
register_deactivation_hook(__FILE__, 'QWEL_ImportCronStop');

//
// Importer
//

function import($user, $instagramBusinessId, $accessToken, $keyword) {
  $url = 'https://graph.facebook.com/v6.0/' . $instagramBusinessId . '?fields=name,media{caption,like_count,media_url,permalink,timestamp,username}&access_token=' . $accessToken;
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $data = curl_exec($ch);
  curl_close($ch);
  
  $data = json_decode($data, true);
  $data = $data['media']['data'];
  $latestPhotoDate = getLatestPhotoDate($user);
  for ($i = 10; $i > 0; $i--) {
    $dt = $data[$i - 1];
    $includeKeyword = includeKeyword($dt, $keyword);
    if ($includeKeyword && (!$latestPhotoDate || strtotime($dt['timestamp']) > $latestPhotoDate)) {
      $post_ID = insertPost($user, $dt);
      uploadImage($dt, $post_ID);
    }
  }
}

function includeKeyword($dt, $keyword) {
  if ($keyword == '') return true;
  $post_content = esc_html($dt['caption']);
  return strpos($post_content, $keyword) !== false;
}

function getLatestPhotoDate($user) {
  $args = [
    'posts_per_page' => 1,
    'post_type' => 'photo',
    'author' => $user
  ];
  $my_posts = get_posts($args);
  if ($my_posts) {
    global $post;
    foreach ($my_posts as $post) {
      setup_postdata($post);
      $latestPhotoDate = get_the_time('Y/m/d G:i:s');
      $dateObj = new DateTime($latestPhotoDate);
      $result = $dateObj->getTimestamp();
    }
    wp_reset_postdata();
    return $result;
  } else {
    return false;
  }
}

function insertPost($user, $dt) {
  return wp_insert_post([
    'post_author'   => $user,
    'post_content'  => esc_html($dt['caption']),
    'post_date'     => date('Y-m-d H:i:s', strtotime('+9 hour', strtotime($dt['timestamp']))), //'Asia/Tokyo'
    'post_date_gmt' => date('Y-m-d H:i:s', $dt['timestamp']),
    'post_status'   => 'publish',
    'post_title'    => 'Photo',
    'post_type'     => 'photo'
  ], true);
}

function removeHashtags($content) {
  $pattern = '/(^|[^0-9A-Z&\/\?]+)([#＃]+)([0-9A-Z_]*[A-Z_]+[a-z0-9_üÀ-ÖØ-öø-ÿ]*)/iu';
  $cleanContent = trim(preg_replace($pattern, '', $content));
  $content = empty($cleanContent) ? trim(str_replace('#', '', $content)) : $cleanContent;
  return $content;
}

function uploadImage($dt, $post_ID) {
	if (!function_exists('media_handle_upload')) {
		require_once(ABSPATH . "wp-admin" . '/includes/image.php');
		require_once(ABSPATH . "wp-admin" . '/includes/file.php');
		require_once(ABSPATH . "wp-admin" . '/includes/media.php');
	}

	$url = $dt['media_url'];
  $tmp = download_url($url);
  
  preg_match('/\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches);
  $fileArr = [];
	$fileArr['name'] = 'img' . $post_ID . basename($matches[0]);
	$fileArr['tmp_name'] = $tmp;
  
  if (is_wp_error($tmp)) {
		@unlink($fileArr['tmp_name']);
		$fileArr['tmp_name'] = '';
	}
  
	$desc = 'Instagram Photo';
	
	$thumbnail_ID = media_handle_sideload($fileArr, $post_ID, $desc);

	if (is_wp_error($thumbnail_ID)) {
		@unlink($fileArr['tmp_name']);
		return $thumbnail_ID;
	}

	$src = wp_get_attachment_url($thumbnail_ID);
  
  set_post_thumbnail($post_ID, $thumbnail_ID);
}

//
// Create custom post type
//

function register_option_photo() {
  register_post_type('photo', [
    'labels' => [
      'name' => '写真',
      'all_items' => '写真一覧'
    ],
    'menu_icon' => 'dashicons-camera',
    'menu_position' => 9,
    'public' => true,
    'has_archive' => true,
    'query_var' => true, 
    'rewrite' => [
      'slug' => 'photo',
      'with_front' => false,
      'hierarchical' => true
    ],
    'supports' => ['title', 'editor', 'thumbnail'] 
  ]);
}
add_action('init', 'register_option_photo');
