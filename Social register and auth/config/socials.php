<?php defined('SYSPATH') OR die('No direct access allowed.');

return array(
	'vk' => array(
        'app_id' => '4062693',
        'redirect_uri' => 'http://bloommy/social/vk/register/',
        'app_secret'   => 'zxqWLIuKEkbaecX5BgAf',
        'permissions'  => 'offline',
        'api_version'  => '5.5'
    ),
	'fb' => array(
        'app_id'           => '706111156079101',
        'app_secret'       => '1440f7b0233cfaed31eef18b640139fb',
        'url_callback'     => 'http://www.bloommy/social/facebook/register/',
        'url_oauth'        => 'https://www.facebook.com/dialog/oauth',
        'url_access_token' => 'https://graph.facebook.com/oauth/access_token',
        'url_get_me'       => 'https://graph.facebook.com/me'
    ),
    'tw' => array(
        'consumer_key'      => 'o3YiJ8t3oRTHjuyf1YZSFg',
        'consumer_secret'   => 'lxwK6RQkkdEoGrDPsbDk9fYhA0Kw5Y1EmzHIlvmIvs',
        'url_callback'      => 'http://www.bloommy/social/twitter/register/',
        'url_request_token' => 'https://api.twitter.com/oauth/request_token',
        'url_authorize'     => 'https://api.twitter.com/oauth/authorize',
        'url_access_token'  => 'https://api.twitter.com/oauth/access_token',
        'url_account_data'  => 'https://api.twitter.com/1.1/users/show.json'
    ),
    'mail' =>array(
        'id'            => '714821',
        'private_key'   => '02c62e44d30e351b7d300f2cd4f17b95',
        'secret_key'    => '7e2f71972bbe7c368b200d8853a61e2d',
        'redirect_url'  => 'http://bloommy/social/mail/register/'
    )
);
