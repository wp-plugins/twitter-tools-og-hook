<?php
/*
Plugin Name: Twitter Tools - Open Graph Hook
Plugin URI:  
Description: Allow for blog posts published to be sent to a Facebook Open Graph Node (e.g. a facebook page) This plugin relies on Twitter Tools, configure it on the Twitter Tools settings page.
Version: 0.1
Author: Shu Kit Chan
Author URI: http://www.shukitchan.com
*/

// ini_set('display_errors', '1'); ini_set('error_reporting', E_ALL);

if (!defined('PLUGINDIR')) {
	define('PLUGINDIR','wp-content/plugins');
}

load_plugin_textdomain('twitter-tools-og-hook');

function aktt_og_post_options() {
	global $self, $post;

	$self == 'post-new.php' ? $notify = get_option('aktt_og_default', 'yes'): $notify = get_post_meta($post->ID, '_aktt_notify_fb', true); 
	$ogurl = get_option('aktt_og_url', '');
	$facebook_app_id = get_option('aktt_og_app_id', '');
	$facebook_secret = get_option('aktt_og_secret', '');

	if($ogurl == '' || $facebook_app_id == '' || $facebook_secret == '')
	{
		return;
	}

	echo '<p>'.__('Send post to Facebook?', 'twitter-tools-og-hook').'
                &nbsp;
                <input type="radio" name="_aktt_notify_fb" id="_aktt_notify_fb_yes" value="yes" '.checked('yes', $notify, false).' /> <label for="aktt_notify_fb_yes">'.__('Yes', 'twitter-tools-og-hook').'</label> &nbsp;&nbsp;
                <input type="radio" name="_aktt_notify_fb" id="_aktt_notify_fb_no" value="no" '.checked('no', $notify, false).' /> <label for="aktt_notify_fb_no">'.__('No', 'twitter-tools-og-hook').'</label>
</p>';
}
add_action('aktt_post_options', 'aktt_og_post_options');

function aktt_og_do_blog_post_og($post_id = 0) {

        global $aktt;

	$oged = get_post_meta($post_id, 'aktt_oged', true);
	$notify = get_post_meta($post_id, '_aktt_notify_fb', true);
	$ogurl = get_option('aktt_og_url', '');
	$facebook_app_id = get_option('aktt_og_app_id', '');
	$facebook_secret = get_option('aktt_og_secret', '');

	if($ogurl == '' || $facebook_app_id == '' || $facebook_secret == '' || $post_id == 0 || $notify != 'yes' || $oged == '1')
	{
		return;
	}

	$post = get_post($post_id);
	// check for an edited post before TT was installed
	if ($post->post_date <= $aktt->install_date) {
		return;
	}
	// check for private posts
	if ($post->post_status == 'private') {
		return;
	}

	$url = apply_filters('tweet_blog_post_url', get_permalink($post_id));
	$og_text = sprintf(__($aktt->tweet_format, 'twitter-tools'), @html_entity_decode($post->post_title, ENT_COMPAT, 'UTF-8'), $url);

	$mymessage = $og_text;

	$access_token_url = "https://graph.facebook.com/oauth/access_token"; 
	$parameters = "grant_type=client_credentials&client_id=" . $facebook_app_id .
		"&client_secret=" . $facebook_secret;
	//$access_token = file_get_contents($access_token_url ."?".$parameters);
        $ch1 = curl_init();
        $timeout = 5; // set to zero for no timeout
        curl_setopt ($ch1, CURLOPT_URL, $access_token_url."?".$parameters);
        curl_setopt ($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch1, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt ($ch1, CURLOPT_SSL_VERIFYPEER, FALSE);
        $access_token = curl_exec($ch1);
        curl_close($ch1);

	$apprequest_url = "https://graph.facebook.com/feed";
	$parameters = "?" . $access_token . "&message=" .
		urlencode($mymessage) . "&id=" . $ogurl . "&method=post";
	$myurl = $apprequest_url . $parameters;
        //$result = file_get_contents($myurl);
        $ch2 = curl_init();
        $timeout = 5; // set to zero for no timeout
        curl_setopt ($ch2, CURLOPT_URL, $myurl);
        curl_setopt ($ch2, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch2, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt ($ch2, CURLOPT_SSL_VERIFYPEER, FALSE);
        $result = curl_exec($ch2);
        curl_close($ch2);

	add_post_meta($post_id, 'aktt_oged', '1', true); 
}
add_action('publish_post', 'aktt_og_do_blog_post_og', 99);

function aktt_og_save_post($post_id, $post) {
	if (current_user_can('edit_post', $post_id)) {
		update_post_meta($post_id, '_aktt_notify_fb', $_POST['_aktt_notify_fb']);
	}
}
add_action('save_post', 'aktt_og_save_post', 10, 2);

function aktt_og_request_handler() {
	if (!empty($_POST['cf_action'])) {
		switch ($_POST['cf_action']) {
			case 'aktt_og_update_settings':
				if (!wp_verify_nonce($_POST['_wpnonce'], 'aktt_og_update_settings')) {
					wp_die('Oops, please try again.');
				}
				aktt_og_save_settings();
				wp_redirect(admin_url('options-general.php?page=twitter-tools.php&updated=true'));
				die();
				break;
		}
	}
}
add_action('init', 'aktt_og_request_handler');

$aktt_og_settings = array(
	'aktt_og_default' => array(
		'type' => 'select',
		'label' => __('Set this on by default?', 'twitter-tools-og-hook'),
		'default' => 'yes',
		'options' => array(
			'yes' => 'Yes',
			'no' => 'No',
		),
		'help' => __('Set this on by default?', 'twitter-tools-og-hook'),
	),
        'aktt_og_url' => array(
                'type' => 'string',
                'label' => __('OG URL', 'twitter-tools-og-hook'),
                'default' => '',
                'help' => __('The URL of the facebook OG', 'twitter-tools-og-hook'),
        ),
        'aktt_og_app_id' => array(
                'type' => 'string',
                'label' => __('OG App ID', 'twitter-tools-og-hook'),
                'default' => '',
                'help' => __('The App ID of the facebook OG', 'twitter-tools-og-hook'),
        ),
        'aktt_og_secret' => array(
                'type' => 'string',
                'label' => __('OG App Secret', 'twitter-tools-og-hook'),
                'default' => '',
                'help' => __('The secret of the facebook OG', 'twitter-tools-og-hook'),
        ),


);

function aktt_og_setting($option) {
	$value = get_option($option);
	if (empty($value)) {
		global $aktt_og_settings;
		$value = $aktt_og_settings[$option]['default'];
	}
	return $value;
}

if (!function_exists('cf_settings_field')) {
	function cf_settings_field($key, $config) {
		$option = get_option($key);
		if (empty($option) && !empty($config['default'])) {
			$option = $config['default'];
		}
		$label = '<label for="'.$key.'">'.$config['label'].'</label>';
		$help = '<span class="help">'.$config['help'].'</span>';
		switch ($config['type']) {
			case 'select':
				$output = $label.'<select name="'.$key.'" id="'.$key.'">';
				foreach ($config['options'] as $val => $display) {
					$option == $val ? $sel = ' selected="selected"' : $sel = '';
					$output .= '<option value="'.$val.'"'.$sel.'>'.htmlspecialchars($display).'</option>';
				}
				$output .= '</select>'.$help;
				break;
			case 'textarea':
				$output = $label.'<textarea name="'.$key.'" id="'.$key.'">'.htmlspecialchars($option).'</textarea>'.$help;
				break;
			case 'string':
			case 'int':
			default:
				$output = $label.'<input name="'.$key.'" id="'.$key.'" value="'.htmlspecialchars($option).'" />'.$help;
				break;
		}
		return '<div class="option">'.$output.'<div class="clear"></div></div>';
	}
}

function aktt_og_settings_form() {
	global $aktt_og_settings;

	print('
<div class="wrap">
	<h2>'.__('OG Hook for Twitter Tools', 'twitter-tools-og-hook').'</h2>
	<form id="aktt_og_settings_form" name="aktt_og_settings_form" class="aktt" action="'.admin_url('options-general.php').'" method="post">
		<input type="hidden" name="cf_action" value="aktt_og_update_settings" />
		<fieldset class="options">
	');
	foreach ($aktt_og_settings as $key => $config) {
		echo cf_settings_field($key, $config);
	}
	print('
		</fieldset>
		<p class="submit">
			<input type="submit" name="submit" class="button-primary" value="'.__('Save Settings', 'twitter-tools-og-hook').'" />
		</p>
		'.wp_nonce_field('aktt_og_update_settings', '_wpnonce', true, false).wp_referer_field(false).'
	</form>
</div>
	');
}
add_action('aktt_options_form', 'aktt_og_settings_form');

function aktt_og_save_settings() {
	if (!current_user_can('manage_options')) {
		return;
	}
	global $aktt_og_settings;
	foreach ($aktt_og_settings as $key => $option) {
		$value = '';
		switch ($option['type']) {
			case 'int':
				$value = intval($_POST[$key]);
				break;
			case 'select':
				$test = stripslashes($_POST[$key]);
				if (isset($option['options'][$test])) {
					$value = $test;
				}
				break;
			case 'string':
			case 'textarea':
			default:
				$value = stripslashes($_POST[$key]);
				break;
		}
		update_option($key, $value);
	}
}

?>
