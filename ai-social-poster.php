<?php
/**
 * Plugin Name: AI Social Poster
 * Description: Automatically summarizes posts with OpenAI and publishes them to social media.
 * Version: 1.0.0
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Social_Poster
{

    private $openai_api_url = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        // Admin Settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Meta Box
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box_data'));

        // Publish Hook
        add_action('publish_post', array($this, 'handle_publish_post'), 10, 2);
    }

    /**
     * 1. Admin Settings Page
     */
    public function add_admin_menu()
    {
        add_options_page(
            'AI Social Poster Settings',
            'AI Social Poster',
            'manage_options',
            'ai-social-poster',
            array($this, 'settings_page_html')
        );
    }

    public function register_settings()
    {
        register_setting('asp_settings_group', 'asp_openai_key', 'sanitize_text_field');
        register_setting('asp_settings_group', 'asp_webhook_url', 'esc_url_raw');
    }

    public function settings_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('asp_settings_group');
                do_settings_sections('asp_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="password" name="asp_openai_key"
                                value="<?php echo esc_attr(get_option('asp_openai_key')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Social Media Webhook URL</th>
                        <td>
                            <input type="url" name="asp_webhook_url"
                                value="<?php echo esc_attr(get_option('asp_webhook_url')); ?>" class="regular-text" />
                            <p class="description">Enter the Webhook URL from n8n, Make, or Zapier.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * 2. Meta Box
     */
    public function add_meta_box()
    {
        add_meta_box(
            'asp_social_meta_box',
            'Social Media Auto-Post',
            array($this, 'render_meta_box'),
            'post',
            'side',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('asp_save_meta_box_data', 'asp_meta_box_nonce');

        $platforms = get_post_meta($post->ID, '_asp_social_platforms', true);

        // Default to ON if metadata doesn't exist (new post)
        if (empty($platforms) && !get_post_meta($post->ID, '_asp_posted', true)) {
            $platforms = array('facebook', 'linkedin', 'instagram', 'twitter');
        } elseif (empty($platforms)) {
            $platforms = array();
        }

        $options = array(
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'instagram' => 'Instagram',
            'twitter' => 'X (Twitter)',
        );

        echo '<p>Select platforms to post to upon publish:</p>';

        foreach ($options as $key => $label) {
            $checked = in_array($key, $platforms) ? 'checked' : '';
            echo '<label><input type="checkbox" name="asp_platforms[]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
        }

        $posted = get_post_meta($post->ID, '_asp_posted', true);
        if ($posted) {
            echo '<p><strong>Status:</strong> Posted on ' . esc_html($posted) . '</p>';
        }
    }

    public function save_meta_box_data($post_id)
    {
        if (!isset($_POST['asp_meta_box_nonce']) || !wp_verify_nonce($_POST['asp_meta_box_nonce'], 'asp_save_meta_box_data')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['asp_platforms'])) {
            // Sanitize array values
            $platforms = array_map('sanitize_text_field', $_POST['asp_platforms']);
            update_post_meta($post_id, '_asp_social_platforms', $platforms);
        } else {
            update_post_meta($post_id, '_asp_social_platforms', array());
        }
    }

    /**
     * 3. Core Logic: Summary & Publish
     */
    public function handle_publish_post($ID, $post)
    {
        // Basic checks
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;
        if (wp_is_post_revision($ID))
            return;

        // Avoid duplicate posting
        if (get_post_meta($ID, '_asp_posted', true)) {
            return;
        }

        // Get selected platforms
        $platforms = get_post_meta($ID, '_asp_social_platforms', true);
        if (empty($platforms) || !is_array($platforms)) {
            return;
        }

        // Get API Keys
        $openai_key = get_option('asp_openai_key');
        $webhook_url = get_option('asp_webhook_url');

        if (empty($openai_key) || empty($webhook_url)) {
            error_log('AI Social Poster: Missing API Key or Webhook URL.');
            return;
        }

        // 1. Generate Summary
        $summary = $this->generate_summary($post->post_content, $openai_key);
        if (!$summary) {
            error_log('AI Social Poster: Summarization failed for post ID ' . $ID);
            return;
        }

        // 2. Extract Images
        $image_urls = $this->extract_images($ID, $post->post_content);

        // 3. Post to Social Media (Webhook)
        $result = $this->post_to_webhook($summary, $image_urls, $platforms, $webhook_url, $post);

        if ($result) {
            update_post_meta($ID, '_asp_posted', current_time('mysql'));
        }
    }

    private function generate_summary($content, $api_key)
    {
        // Strip tags to send cleaner text
        $text_content = wp_strip_all_tags($content);
        // Truncate if too long to save tokens (e.g. first 5000 chars)
        $text_content = substr($text_content, 0, 5000);

        $body = array(
            'model' => 'gpt-4o',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a social media manager. Summarize the following article into 2-3 engaging, neutral sentences suitable for Facebook, LinkedIn, Twitter, and Instagram. Do not use hashtags unless necessary.'
                ),
                array(
                    'role' => 'user',
                    'content' => $text_content
                )
            ),
            'max_tokens' => 150
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 30, // Give OpenAI some time
        );

        $response = wp_remote_post($this->openai_api_url, $args);

        if (is_wp_error($response)) {
            error_log('AI Social Poster OpenAI Error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('AI Social Poster OpenAI Error Code: ' . $code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }

        return false;
    }

    private function extract_images($post_id, $content)
    {
        $images = array();

        // 1. Featured Image
        if (has_post_thumbnail($post_id)) {
            $thumb_url = get_the_post_thumbnail_url($post_id, 'full');
            if ($thumb_url) {
                $images[] = $thumb_url;
            }
        }

        // 2. Inline Images
        if (class_exists('DOMDocument')) {
            $dom = new DOMDocument();
            // Suppress warnings for malformed HTML
            @$dom->loadHTML($content);
            $tags = $dom->getElementsByTagName('img');
            foreach ($tags as $tag) {
                if ($tag instanceof DOMElement) {
                    $src = $tag->getAttribute('src');
                    if ($src) {
                        $images[] = $src;
                    }
                }
            }
        } else {
            // Fallback Regex
            preg_match_all('/<img[^>]+src="([^">]+)"/', $content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $src) {
                    $images[] = $src;
                }
            }
        }

        // Unique and limit (e.g. max 4 for Twitter)
        return array_slice(array_unique($images), 0, 4);
    }

    private function post_to_webhook($summary, $images, $platforms, $webhook_url, $post)
    {
        $body = array(
            'summary' => $summary,
            'platforms' => $platforms,
            'images' => $images,
            'post_title' => $post->post_title,
            'post_url' => get_permalink($post->ID),
            'post_id' => $post->ID
        );

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 30,
        );

        $response = wp_remote_post($webhook_url, $args);

        if (is_wp_error($response)) {
            error_log('AI Social Poster Webhook Error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        // 200 OK
        if ($code >= 200 && $code < 300) {
            return true;
        } else {
            error_log('AI Social Poster Webhook Error Code: ' . $code . ' Body: ' . wp_remote_retrieve_body($response));
            return false;
        }
    }
}

new AI_Social_Poster();
