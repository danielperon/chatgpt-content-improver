<?php
/**
 * Plugin Name: ChatGPT Content Improver
 * Description: Integrates ChatGPT to improve WordPress post content.
 * Version: 1.0
 * Author: Your Name
 * License: GPL2
 */

// Hook to add a menu item in the WordPress admin
add_action('admin_menu', 'chatgpt_content_improver_menu');

function chatgpt_content_improver_menu() {
    add_menu_page(
        'ChatGPT Content Improver', // Page title
        'ChatGPT Content', // Menu title
        'manage_options', // Capability
        'chatgpt-content-improver', // Menu slug
        'chatgpt_content_improver_settings_page', // Callback function
        'dashicons-editor-justify', // Icon
        100 // Position
    );
}

// Callback function to render the plugin settings page
function chatgpt_content_improver_settings_page() {
    ?>
    <div class="wrap">
        <h2>ChatGPT Content Improver Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('chatgpt_content_improver_options_group');
            do_settings_sections('chatgpt-content-improver');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">OpenAI API Key</th>
                    <td>
                        <input type="text" name="chatgpt_api_key" value="<?php echo esc_attr(get_option('chatgpt_api_key')); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings for storing the API key
add_action('admin_init', 'chatgpt_content_improver_register_settings');

function chatgpt_content_improver_register_settings() {
    register_setting('chatgpt_content_improver_options_group', 'chatgpt_api_key');
}

// Hook to add a meta box with the ChatGPT improvement button in the post editor
add_action('add_meta_boxes', 'chatgpt_content_improver_meta_box');

function chatgpt_content_improver_meta_box() {
    add_meta_box(
        'chatgpt_content_improver_meta_box', // ID
        'ChatGPT Content Improver', // Title
        'chatgpt_content_improver_meta_box_callback', // Callback function to render content
        'post', // Screen (in this case, posts)
        'side', // Context (position of the meta box)
        'high' // Priority
    );
}

function chatgpt_content_improver_meta_box_callback($post) {
    ?>
    <button type="button" id="chatgpt-improve-btn" class="button button-primary">Improve Content with ChatGPT</button>
    <div id="chatgpt-improved-content" style="margin-top: 10px;"></div>
    <?php
}

// Enqueue the JavaScript to handle button clicks and AJAX requests
add_action('admin_footer', 'chatgpt_content_improver_ajax');

function chatgpt_content_improver_ajax() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#chatgpt-improve-btn').on('click', function() {
                var postContent = ''; // Initialize an empty string for the content

                // Check if we are in the Classic Editor
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                    postContent = tinymce.activeEditor.getContent();  // For Classic Editor (TinyMCE)
                } else if (typeof wp.data !== 'undefined') {
                    // Get content from Gutenberg editor
                    postContent = wp.data.select('core/editor').getEditedPostContent();
                }

                // If no content found, show an error
                if (!postContent) {
                    alert("No content found in the editor.");
                    return;
                }

                var apiKey = '<?php echo esc_js(get_option("chatgpt_api_key")); ?>';

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'chatgpt_improve_content',
                        post_content: postContent,
                        api_key: apiKey
                    },
                    success: function(response) {
                        if (response.success) {
                            // Get the improved content
                            var improvedContent = response.data.data.data;

                            // Create the improved content block for Gutenberg
                            var contentBlock = '<p style="color: #008080; font-style: italic; border-top: 2px solid #ddd; padding-top: 10px;"><strong>Improved Content:</strong><br>' + improvedContent + '</p>';

                            // Insert improved content below the original content
                            wp.data.dispatch('core/editor').insertBlocks(
                                wp.blocks.createBlock('core/paragraph', {
                                    content: contentBlock
                                })
                            );

                            // Optionally, you could also add the improved content below the original content in the classic editor.
                            if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                                tinymce.activeEditor.setContent(postContent + "<br><div style='color: #008080; font-style: italic; border-top: 2px solid #ddd; padding-top: 10px;'><strong>Improved Content:</strong><br>" + improvedContent + "</div>");
                            }
                        } else {
                            $('#chatgpt-improved-content').html('<strong>Error:</strong><br>' + response.data.message);
                        }
                    },
                    error: function() {
                        $('#chatgpt-improved-content').html('<strong>Error:</strong><br>Something went wrong.');
                    }
                });
            });
        });
    </script>

    <?php
}

// Handle the AJAX request to improve content using ChatGPT
add_action('wp_ajax_chatgpt_improve_content', 'chatgpt_improve_content_callback');

function chatgpt_improve_content_callback() {
    // Check if the 'post_content' is being passed in the request
    if (isset($_POST['post_content'])) {
        $content = sanitize_textarea_field($_POST['post_content']);
    } else {
        wp_send_json_error(array('message' => 'Post content is missing.'));
    }

    $apiKey = sanitize_text_field($_POST['api_key']);

    // Ensure the API key is available
    if (empty($apiKey)) {
        wp_send_json_error(array('message' => 'API key is missing.'));
    }

    // Send the content to ChatGPT for improvement
    $response = chatgpt_api_request($apiKey, $content);

    // If response is empty or contains no improvement, handle accordingly
    if (empty($response)) {
        wp_send_json_error(array('message' => 'No improvement from ChatGPT.'));
    }

    wp_send_json_success(array('data' => array('data' => $response)));
}


// Function to send the request to OpenAI's API
function chatgpt_api_request($apiKey, $content) {
    $url = 'https://api.openai.com/v1/chat/completions';  // Correct endpoint for chat models

    // Request body format for chat models
    $data = json_encode([
        'model' => 'gpt-3.5-turbo',  // You can adjust this if necessary
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => "Improve this content: $content"],  // The user's prompt
        ],
        'max_tokens' => 500,  // Adjust token limit if necessary
        'temperature' => 0.7,  // Adjust temperature (0.7 is usually good for creative content)
    ]);

    $args = [
        'body'        => $data,
        'headers'     => [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ],
        'timeout'     => 15,
    ];

    $response = wp_remote_post($url, $args);

    // Debug: Log the response
    if (is_wp_error($response)) {
        return 'Error contacting API';
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body);

    // Log the entire response for debugging
    error_log(print_r($result, true));  // This logs the response to your debug.log file

    return $result->choices[0]->message->content ?? 'No improvement';  // Return the improved content or a fallback message
}


?>
