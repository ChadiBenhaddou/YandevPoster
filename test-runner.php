<?php
define('ABSPATH', true);
// Mock WordPress functions
function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
{
}
function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback)
{
}
function register_setting($option_group, $option_name, $args = array())
{
}
function settings_fields($option_group)
{
}
function do_settings_sections($page)
{
}
function esc_html($text)
{
    return $text;
}
function get_admin_page_title()
{
    return 'AI Social Poster';
}
function submit_button($text = 'Save Changes')
{
}
function add_meta_box($id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null)
{
}
function wp_nonce_field($action = -1, $name = "_wpnonce", $referer = true, $echo = true)
{
}
function get_post_meta($post_id, $key = '', $single = false)
{
    if ($key === '_asp_social_platforms')
        return array('twitter');
    if ($key === '_asp_posted')
        return false;
    return '';
}
function esc_attr($text)
{
    return $text;
}
function wp_verify_nonce($nonce, $action = -1)
{
    return true;
}
function current_user_can($capability, $object_id = null)
{
    return true;
}
function sanitize_text_field($str)
{
    return trim($str);
}
function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
{
    echo "Updated meta: $meta_key = " . json_encode($meta_value) . "\n";
    return true;
}
function wp_is_post_revision($post_id)
{
    return false;
}

function get_permalink($id)
{
    return "http://example.com/post/$id";
}

function get_option($option, $default = false)
{
    if ($option === 'asp_openai_key')
        return 'mock_openai_key';
    if ($option === 'asp_webhook_url')
        return 'https://webhook.example.com/hook';
    return $default;
}
function wp_strip_all_tags($text)
{
    return strip_tags($text);
}
function is_wp_error($thing)
{
    return false;
}
function wp_remote_post($url, $args = array())
{
    echo "Remote POST to: $url\n";
    if (strpos($url, 'openai') !== false) {
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                'choices' => array(
                    array('message' => array('content' => 'This is a mock summary.'))
                )
            ))
        );
    }
    if (strpos($url, 'webhook.example.com') !== false) {
        echo "Webhook Payload: " . $args['body'] . "\n";
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('status' => 'success'))
        );
    }
    return array('response' => array('code' => 500));
}
function wp_remote_retrieve_response_code($response)
{
    return $response['response']['code'];
}
function wp_remote_retrieve_body($response)
{
    return $response['body'];
}
function has_post_thumbnail($post_id)
{
    return true;
}
function get_the_post_thumbnail_url($post_id, $size = 'post-thumbnail')
{
    return 'http://example.com/featured.jpg';
}
function current_time($type, $gmt = 0)
{
    return date('Y-m-d H:i:s');
}

// Mock Post Object
$post = new stdClass();
$post->ID = 123;
$post->post_title = "Test Post Title";
$post->post_content = "This is a test post content. <img src='http://example.com/image1.jpg'>";

// Include the plugin file
require_once 'c:/wamp67/www/YandevPoster/ai-social-poster.php';

// Instantiate and Test
$plugin = new AI_Social_Poster();

echo "Testing handle_publish_post with Webhook...\n";
$plugin->handle_publish_post(123, $post);

echo "Test complete.\n";
