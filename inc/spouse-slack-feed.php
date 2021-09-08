<?php
/**
 * Description: Fetch messages from slack channel.
 */

add_action( 'init', 'spouse_add_slack_shortcodes');

function spouse_add_slack_shortcodes() {
  add_shortcode('slack-chat', 'spouse_init_slack_view');
}

/**
 * Shortcode function.
 *
 * $attributes['timezone'] string - for example 'Finland/Helsinki'. Defaults to 'Finland/Helsinki'.
 * $attributes['channel'] string - Name of the slack channel to show. Defaults to 'general'.
 * $attributes['messagecount'] int - maximum number of messages to show. Defaults to 25.
 * $attributes['workspaceid'] string - workspace id
 * @param array $attributes
 *
 */
function spouse_init_slack_view($attributes) {
  if(!isset($attributes['workspace'])) {
    return;
  }
  if (isset($attributes['channel'])) {
    $slackChannelName = $attributes['channel'];
  } else {
    $slackChannelName = 'general';
  }
  if (isset($attributes['messagecount'])) {
    $slackHistoryCount = $attributes['messagecount'];
  } else {
    $slackHistoryCount = '25';
  }
  if(isset($attributes['title'])){
    $title = $attributes['title'];
  } else {
    $title = '';
  }

  $channel = get_channel_by_name($slackChannelName, $attributes);
  $channelHistory = get_channel_history($channel['id'], $slackHistoryCount, $attributes);
  $userList = get_all_users($attributes);

  echo '<div class="slack-feed">';
  if($title){
    echo '<h3>'.sanitize_text_field($title).'</h3>';
  }
  foreach (array_reverse($channelHistory) as $message) {
    echo render_message($message, $userList);
  }

  echo '</div>';
}


/**
 * Send request to slack api.
 *
 * @param string $apiPath
 * @param array $postFields
 * @param array $attributes
 * @return bool|mixed|string
 */
function slack_api_request ($apiPath, $postFields, $attributes) {
  $slackApiToken = $attributes['workspace'];
  $postFields['token'] = $slackApiToken;

  $ch = curl_init('https://slack.com/api/' . $apiPath);
  $data = http_build_query($postFields);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  $result = curl_exec($ch);
  curl_close($ch);

  $result = json_decode($result, true);

  if ($result['ok'] == '1') {
    return $result;
  }

}

/**
 * Read chat from json file.
 *
 * @param string $name
 * @return mixed|null
 */
function cache_read($name) {
  $content = get_transient($name);
  if(!$content){
    return false;
  }
  return json_decode($content, true);
}

/**
 * Write chat to json file.
 *
 * @param string $name
 * @param any $content
 *
 */
function cache_write($name, $content) {
  if(!$content){
    return false;
  }
  $json = json_encode($content);
  set_transient($name, $json, 60);
}


/**
 * Get slack channel's chat history
 *
 * @param string $channelId
 * @param int $history_count
 * @return array|mixed|null
 */
function get_channel_history($channelId, $max_history, $attributes)
{
  $messageCacheExpire = 60;
  if ($channel_history = cache_read($attributes['channel'])) {
    return $channel_history;
  }

  $has_more = true;
  $channel_history = [];
  $fetch_from_ts = time();

  while ($has_more && count($channel_history) < $max_history) {
    $h = slack_api_request('conversations.history', [
      'channel' => $channelId,
      'latest' => $fetch_from_ts,
    ],
      $attributes);
    $channel_history = array_merge($channel_history, $h['messages']);
    $has_more = $h['has_more'];
    $fetch_from_ts = array_slice($h['messages'], -1)[0]['ts'];
  }

  cache_write($attributes['channel'], $channel_history);

  return $channel_history;
}

/**
 * Get users from slack channel.
 *
 * @return array|bool|mixed|string|null
 */
function get_all_users($attributes)
{
  $cacheName = 'slack-users';
  $userCacheTimeout = 60;
  $userlist = cache_read($cacheName, $userCacheTimeout, $attributes);
  if ($userlist) {
    return $userlist;
  }

  $userlist = slack_api_request('users.list', [
    'limit' => 800,
    'presence' => false
    ],
    $attributes);

  $userlistIndexed = [];
  foreach ($userlist['members'] as $user) {
    $userlistIndexed[$user['id']] = $user;
  }

  cache_write($cacheName, $userlistIndexed, 60*5);
  return $userlistIndexed;
}

/**
 * Get slack channel by name.
 *
 * @param string $channel_name
 * @return mixed|null
 */
function get_channel_by_name($channel_name, $attributes) {
  $all_channels = slack_api_request('conversations.list', [
    'limit' => 500,
    'exclude_archived' => true,
    'exclude_members' => true
  ], $attributes);

  foreach ($all_channels['channels'] as $channel) {
    if ($channel['name'] == $channel_name) {
      return $channel;
    }
  }

  return null;
}


/**
 * Manipulate emojis.
 *
 * @param $coloncode
 * @return string
 */
function coloncode_to_emoji($coloncode) {
  global $all_emojis;

  $emoji = $all_emojis[$coloncode];
  if ($emoji) {
    if (substr($emoji, 0, 8) == 'https://') {
      return '<img class="emoji" src="' . $emoji . '" title="' . $coloncode . '">';
    }

    if (substr($emoji, 0, 6) == 'alias:') {
      return coloncode_to_emoji(substr($coloncode, 6));
    }

    return $emoji;

  }

  return ':' . $coloncode . ':';
}

/**
 * Manipulate messages.
 *
 * @param string $text
 * @return string|string[]|null
 */
function replace_slack_tags($text) {
  $text = preg_replace_callback(
    '/<@([a-zA-Z0-9]+)>/',
    function ($matches) {
      #return user_id_to_name($matches[1]);
    },
    $text
  );

  $text = preg_replace_callback(
    '/:([a-zA-Z0-9_\-]+)(::[a-zA-Z0-9_\-])?:/',
    function ($matches) {
      return coloncode_to_emoji($matches[1]);
    },
    $text
  );

  $text = preg_replace_callback(
    '/<(https?:\/\/.+?)\\|([^>]+?)>/',
    function ($matches) {
      return ' <a target="_top" href="' . $matches['1'] . '" target="_blank">' . $matches[2] . '</a> ';
    },
    $text
  );

  $text = preg_replace_callback(
    '/<(https?:\/\/.+?)>/',
    function ($matches) {
      return ' <a target="_top" href="' . $matches['1'] . '" target="_blank">' . $matches[1] . '</a> ';
    },
    $text
  );

  $text = preg_replace(
    '/<#[a-zA-Z0-9]+\|([a-zA-Z0-9æøåÅÆØäöÄÖ\-_]+)>/',
    '#$1',
    $text
  );

  // 3+ are replaced with just two
  $text = preg_replace("/\n{3,}/", "\n\n", $text);

  return $text;
}


/**
 * Render message reactions.
 *
 * @param array $reactions
 * @return string
 */
function render_reactions($reactions) {
  $html = '';
  foreach ($reactions as $r) {
    $emoji = $r['name'];
    $skin_modifier_pos = stripos($emoji, '::');
    if ($skin_modifier_pos) {
      $emoji = substr($emoji, 0, $skin_modifier_pos);
    }

    $html .= '<span class="reaction"><i title="' . $emoji . '">' . coloncode_to_emoji($emoji) . '</i> <small>' . $r['count'] . '</small>' . '</span>';
  }

  return $html;
}

/**
 * Render user avatar.
 *
 * @param array $user
 * @return string
 */
function render_avatar($user) {
  #return '<img class="avatar" src="' . $user['profile']['image_48'] . '" aria-hidden="true" title="">';
}

/**
 * Render user information
 *
 * @param array $message
 * @param array $user
 * @return string
 */
function render_userinfo($message, $user) {
  if(!$user){
    $username = 'Unknown';
  } else {
    $username = $user['real_name'] ? $user['real_name'] : $user['name'];
  }

  $html = '<strong class="username">' .sanitize_text_field($username) . '</strong> ';

  $html .= '<small class="timestamp">' . date('l, F jS \a\t g:i a', $message['ts']) . '</small>';

  return $html;
}

/**
 * Render the user message.
 *
 * @param $message
 * @param $user
 * @return string
 */
function render_user_message($message, $user) {
  $html = '<div class="slack-message">';

  if (isset($message['parent_user_id'])) {
    return '';
  }

  $username = $user['real_name'] ? $user['real_name'] : $user['name'];
  $userInfo = render_userinfo($message, $user);
  $messageString = replace_slack_tags(sanitize_text_field($message['text']));
  $date = date('l, F jS \a\t g:i a', $message['ts']);
  $ariaMessage = "Message from $username on ".$date.": $messageString";

  $html .= render_avatar($user);

  $html .= '<div aria-label="'.$ariaMessage.'" class="content">';

  $html .= $userInfo;

  $html .= '<div class="message">' . $messageString . '</div>';

  if (isset($message['reactions'])) {
    #$html .= render_reactions($message['reactions']);
  }

  $html .= '</div>'; // .content
  $html .= '</div>'; // .slack-message
  return $html;
}


/**
 * Render bot message.
 *
 * @param array $message
 * @param string $username
 * @return string
 */
function render_bot_message($message, $username) {
  $html = '<div class="slack-message">';
  if (isset($message['parent_user_id'])) {
    return '';
  }
  $html .= '<img class="avatar" src="' . $message['icons']['image_64'] . '" aria-hidden="true" title="">';
  $html .= '<div class="content">';
  $html .= '<strong class="username">' . $username . '</strong> ';
  $html .= '<small class="timestamp">' . date('l, F jS \a\t g:i a', $message['ts']) . '</small>';
  $html .= '<div class="message">' . replace_slack_tags($message['text']) . '</div>';

  if (isset($message['reactions'])) {
    $html .= render_reactions($message['reactions']);
  }
  $html .= '</div>'; // .content
  $html .= '</div>'; // .slack-message
  return $html;
}


/**
 * Render files
 *
 * @param array $message
 * @param array $user
 * @return string
 */
function render_file_message($message, $user) {
  $file = $message['file'];
  $html = '<div class="slack-message">';

  $html .= render_avatar($user);

  $html .= '<div class="content file">';

  if ($file['pretty_type'] === 'Post') {
    $html .= render_userinfo($message, $user);
    $html .= '<div class="document">';
    $html .= '<h2>' . $file['title'] . '</h2>';
    $html .= '<hr>';
    $html .= $file['preview'];
    $html .= '<a class="readmore" target="_top" href="' . $file['permalink_public'] . '">Click here to read more</a>';
    $html .= '</div>';
  }
  else {
    $html .= '<div class="message">' . replace_slack_tags(sanitize_text_field($message['text'])) . '</div>';
  }

  $html .= render_reactions($file['reactions']);

  $html .= '</div>'; // .content
  $html .= '</div>'; // .slack-message
  return $html;
}

/**
 * Render message.
 *
 * @param array $message
 * @param array $userList
 * @return string|void
 */
function render_message($message, $userList) {
  $html = '';
  switch ($message['type']) {
    case 'message':
      if (empty($message['subtype'])) {
        return render_user_message($message, $userList[$message['user']]);
      }
      switch($message['subtype']) {
        case 'file_share':
          return render_file_message($message, $userList[$message['user']]);
        case 'bot_message':
          return render_bot_message($message, $message['username']);
        case 'channel_join':
        default:
          return;
      }
    default:
      return;
  }
}
