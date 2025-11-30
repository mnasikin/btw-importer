<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class btw_importer_Importer {
    private $downloaded_images = []; // cache

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_btw_prepare_import', [$this, 'ajax_prepare_import']);
        add_action('wp_ajax_btw_import_single_post', [$this, 'ajax_import_single_post']);
    }

    public function add_menu() {
        add_menu_page(
            'BtW Importer', 'BtW Importer', 'manage_options',
            'btw-importer', [$this, 'import_page'], 'dashicons-upload'
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_btw-importer') return;
        wp_enqueue_script('btw-importer', plugin_dir_url(__FILE__).'btw-importer.js', ['jquery'], '2.1.3', true);
        wp_enqueue_style('btw-importer-style', plugin_dir_url(__FILE__).'btw-importer-style.css', [], '2.1.3');
        wp_localize_script('btw-importer', 'btw_importer', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('btw_importer_nonce')
        ]);
    }

    public function import_page() {
        echo '<div class="wrap btw_importer_wrap">
            <div class="btw_importer_header">
                <h1>BtW Importer</h1>
                <p class="btw_importer_subtitle">A powerful yet simple migration tool, BtW Importer helps you seamlessly transfer posts, images, and formatting from Blogger (Blogspot) to WordPress. Don&apos;t forget to share this plugin if you found it&apos;s usefull</p>
            </div>
            
            <div id="importNotice" class="btw_importer_notice">
                <div class="btw_importer_notice_header">
                    <span class="dashicons dashicons-warning"></span>
                    <h2>Please Read Before Importing</h2>
                </div>
                <ul class="btw_importer_notice_list">
                    <li><span class="dashicons dashicons-no"></span> This plugin doesn&apos;t overwrite existing posts with the same name. If you&apos;ve previously used an importer, it&apos;s recommended to manually delete the previously imported content.</li>
                    <li><span class="dashicons dashicons-no"></span> 301 redirects only work if you previously used a custom domain on Blogspot and you&apos;re moving that domain to WordPress.</li>
                    <li><span class="dashicons dashicons-no"></span> Make sure not to leave this page while the process is underway, or the import will stop, and you&apos;ll need to start from the beginning.</li>
                    <li><span class="dashicons dashicons-no"></span> 301 redirects work if this plugin is active and you have already run the importer.</li>
                    <li><span class="dashicons dashicons-no"></span> Only image from Google/Blogspot will be downloaded.</li>
                    <li><span class="dashicons dashicons-no"></span> Be sure to manually check your content after the import process is complete.</li>
                </ul>
                <div class="btw_importer_checkbox_wrapper">
                    <input type="checkbox" id="agreeNotice" class="btw_importer_checkbox">
                    <label for="agreeNotice">
                        I&apos;ve read all of them and I want to start the importer.
                    </label>
                </div>
            </div>
            
            <div class="btw_importer_upload_section">
                <div class="btw_importer_upload_box">
                    <span class="dashicons dashicons-media-document"></span>
                    <input type="file" id="atomFile" accept=".xml,.atom" class="btw_importer_file_input" />
                    <label for="atomFile" class="btw_importer_file_label">Choose your Blogger export file (.xml or .atom)</label>
                    <p class="btw_importer_file_hint">Accepted File: .xml, .atom</p>
                </div>
                <button id="startImport" class="button button-primary btw_importer_start_btn" disabled>
                    <span class="dashicons dashicons-controls-play"></span> Start Import
                </button>
            </div>
            
            <div id="importOverlay" class="btw_importer_overlay">
                <div class="btw_importer_overlay_content">
                    <div class="btw_importer_spinner"></div>
                    <p>Import in progress...</p>
                    <p class="btw_importer_overlay_warning">Please don&apos;t close, reload, or navigate away.</p>
                </div>
            </div>
            
            <div id="progress" class="btw_importer_progress"></div>
        </div>';
    }

    public function ajax_prepare_import() {
        check_ajax_referer('btw_importer_nonce', 'nonce');

        $atom_content = filter_input(INPUT_POST, 'atom_content', FILTER_UNSAFE_RAW);
        $atom_content = null === $atom_content ? '' : wp_unslash($atom_content);

        // Remove BOM and control characters
        $atom_content = preg_replace('/^\x{FEFF}/u', '', $atom_content);
        $atom_content = preg_replace('/[^\P{C}\n\r\t]+/u', '', $atom_content);

        if (!$atom_content) wp_send_json_error('No data received.');

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($atom_content);
        if (!$xml) {
            $errors = libxml_get_errors();
            $messages = array_map(function($e){ return trim($e->message); }, $errors);
            libxml_clear_errors();
            wp_send_json_error('XML parse errors: ' . implode('; ', $messages));
        }

        $namespaces = $xml->getNamespaces(true);
        $entries = $xml->entry;
        if (empty($entries) && isset($namespaces['atom'])) {
            $xml->registerXPathNamespace('a', $namespaces['atom']);
            $entries = $xml->xpath('//a:entry');
        }

        $posts = [];
        foreach ($entries as $entry) {
            $bloggerType = strtolower((string)$entry->children('blogger', true)->type);
            $post_type = $bloggerType;
            if ($post_type == 'page' || $post_type == 'post') {
                $title = sanitize_text_field((string)$entry->title);
                $content = (string)$entry->content;
                $author = isset($entry->author->name) ? sanitize_text_field((string)$entry->author->name) : '';

                $published_raw = (string)$entry->published;
                $date_gmt = gmdate('Y-m-d H:i:s', strtotime($published_raw));
                $date_local = get_date_from_gmt($date_gmt, 'Y-m-d H:i:s');

                $categories = [];
                foreach ($entry->category as $cat) {
                    $term = (string)$cat['term'];
                    if ($term && strpos($term, '#') !== 0) {
                        $categories[] = sanitize_text_field($term);
                    }
                }

                $filename = (string)$entry->children('blogger', true)->filename;
                $filename = trim($filename);

                $status_raw = strtolower((string)$entry->children('blogger', true)->status);
                $status = 'publish';
                if ($status_raw === 'draft') $status = 'draft';
                elseif ($status_raw === 'deleted') $status = 'trash';

                $posts[] = [
                    'title'      => $title,
                    'content'    => $content,
                    'author'     => $author,
                    'post_type'  => $post_type,
                    'date'       => $date_local,
                    'date_gmt'   => $date_gmt,
                    'categories' => $categories,
                    'filename'   => $filename,
                    'status'     => $status
                ];
            }
        }

        wp_send_json_success(['posts' => $posts]);
    }

    public function ajax_import_single_post() {
        check_ajax_referer('btw_importer_nonce', 'nonce');
        $raw_post = filter_input(INPUT_POST, 'post', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $raw_post = is_array($raw_post) ? array_map('wp_unslash', $raw_post) : [];
        if (!$raw_post) wp_send_json_error('Missing post data.');

        $title = sanitize_text_field($raw_post['title'] ?? '');
        $author = sanitize_text_field($raw_post['author'] ?? '');
        $post_type = in_array($raw_post['post_type'], ['post','page']) ? $raw_post['post_type'] : 'post';
        $date = sanitize_text_field($raw_post['date'] ?? '');
        $date_gmt = sanitize_text_field($raw_post['date_gmt'] ?? '');
        $categories = $raw_post['categories'] ?? [];
        $filename = sanitize_text_field($raw_post['filename'] ?? '');
        // Allow HTML Format
        $allowed_tags = wp_kses_allowed_html('post');
        $allowed_tags['iframe'] = [
            'src' => true,
            'width' => true,
            'height' => true,
            'frameborder' => true,
            'allowfullscreen' => true,
            'class' => true,
            'youtube-src-id' => true
        ];
        if ($post_type === 'page') {
            // Allow HTML for pages
            $content = wp_kses($raw_post['content'] ?? '', $allowed_tags);
        } else {
            // Allow HTML for posts
            $content = wp_kses($raw_post['content'] ?? '', $allowed_tags);
        }
        $post_status = in_array($raw_post['status'], ['publish','draft','trash']) ? $raw_post['status'] : 'publish';
        $msgs = [];

        $author_id = 1;
        if ($author) {
            $user = get_user_by('login', sanitize_user($author, true));
            if ($user) $author_id = $user->ID;
        }

        require_once ABSPATH.'wp-admin/includes/image.php';
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';

        $post_id = wp_insert_post([
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => $post_status,
            'post_date'     => $date,
            'post_date_gmt' => $date_gmt,
            'post_author'   => $author_id,
            'post_type'     => $post_type
        ]);

        if (is_wp_error($post_id)) wp_send_json_error('❌ Failed to insert: '.$title);

        // add redirect meta & log redirect creation
        if ($filename) {
            if ($filename[0] !== '/') $filename = '/' . $filename;
            add_post_meta($post_id, '_old_permalink', $filename, true);
            $new_url = get_permalink($post_id);
            $msgs[] = '✅ Finished create 301 redirect: '.$filename.' → '.$new_url;
        }

        // create categories
        if (!empty($categories) && $post_type === 'post') {
            $cat_ids = [];
            foreach ($categories as $cat_name) {
                $term = term_exists($cat_name, 'category');
                if (!$term) {
                    $new_term = wp_create_category($cat_name);
                    if (!is_wp_error($new_term)) {
                        $cat_ids[] = $new_term;
                        $msgs[] = '✅ Created category: '.$cat_name;
                    }
                } else {
                    $cat_ids[] = $term['term_id'];
                    $msgs[] = '✅ Using category: '.$cat_name;
                }
            }
            if (!empty($cat_ids)) wp_set_post_categories($post_id, $cat_ids);
        }

        // find unique blogger/googleusercontent images by basename (after /sXXX/)
        preg_match_all('/https?:\/\/[^\s"\'<>]+?\.(jpg|jpeg|png|gif|webp|bmp|svg)(\?[^\s"\'<>]*)?/i', $content, $matches);
        $image_by_basename = [];
        foreach (array_unique($matches[0]) as $img_url) {
            if (!preg_match('/(blogspot|googleusercontent)/i', $img_url)) continue;

            if (preg_match('#/(s\d+(?:-h)?|w\d+-h\d+)/([^/]+)$#i', $img_url, $m)) {
                $basename = $m[2];
            } else {
                $basename = basename(wp_parse_url($img_url, PHP_URL_PATH));
            }

            if (!isset($image_by_basename[$basename])) {
                $image_by_basename[$basename] = $img_url;
            } else {
                // prefer bigger /sXXX/ number
            if (preg_match('#/s(\d+)/#', $img_url, $m1) && preg_match('#/s(\d+)/#', $image_by_basename[$basename], $m2)) {
                if ((int)$m1[1] > (int)$m2[1]) {
                    $image_by_basename[$basename] = $img_url;
                }
            }
            }
        }

        $first_media_id = null;
        foreach ($image_by_basename as $img_url) {
            if (isset($this->downloaded_images[$img_url])) {
                $new_url = $this->downloaded_images[$img_url];
                $content = str_replace($img_url, $new_url, $content);
                $msgs[]='✅ Used cached: '.$new_url;
                continue;
            }

            $msgs[]='⏳ Downloading: '.$img_url;
            $tmp = download_url($img_url);
            if (is_wp_error($tmp)) { $msgs[]='⚠ Failed to download'; continue; }

            $file = ['name'=>basename(wp_parse_url($img_url, PHP_URL_PATH)),'tmp_name'=>$tmp];
            $media_id = media_handle_sideload($file,$post_id);
            if (is_wp_error($media_id)) { wp_delete_file($tmp); $msgs[]='⚠ Failed to attach'; continue; }

            $new_url = wp_get_attachment_url($media_id);
            if ($new_url) {
                $this->downloaded_images[$img_url] = $new_url;
                $content = str_replace($img_url, $new_url, $content);
                $msgs[]='✅ Replaced: '.$img_url.' → '.$new_url;
                if (!$first_media_id) $first_media_id = $media_id;
            }
        }

        wp_update_post(['ID'=>$post_id,'post_content'=>$content]);
        if ($first_media_id) {
            set_post_thumbnail($post_id, $first_media_id);
            $msgs[]='⭐ Successfully Set featured image';
        }

        $msgs[] = '📌 Post status: ' . esc_html($post_status);
        $msgs[]='✅ Finished '.$post_type.': '.$title;
        wp_send_json_success($msgs);
    }
}

new btw_importer_Importer();
