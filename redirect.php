<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('template_redirect', 'btw_importer_handle_old_permalink_redirect');

function btw_importer_handle_old_permalink_redirect() {
    global $wp;

    // Get requested path with leading slash
    $current_path = '/' . trailingslashit($wp->request);

    // Safely get original request URI
    $original_request_uri = '';
    if (isset($_SERVER['REQUEST_URI'])) {
        $original_request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
    }

    // Remove trailing slash if original request ends with .html
    if (substr($current_path, -1) === '/' && str_ends_with($original_request_uri, '.html')) {
        $current_path = rtrim($current_path, '/');
    }

    // Match Blogger old permalink: /YYYY/MM/slug.html and /p/slug.html
    if (preg_match('#(/\\d{4}/\\d{2}/.+\\.html$|/p/.+\\.html$)#', $current_path)) {
        $query = new WP_Query([
            'post_type'  => ['post', 'page'],
            'meta_query' => [
                [
                    'key'   => '_old_permalink',
                    'value' => $current_path
                ]
            ],
            'posts_per_page' => 1
        ]);

        if ($query->have_posts()) {
            $post = $query->posts[0];
            $new_url = get_permalink($post->ID);
            if ($new_url) {
                wp_safe_redirect($new_url, 301);
                exit;
            }
        }
    }
}
