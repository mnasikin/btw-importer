<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class btw_importer_Importer {
    private $downloaded_images = [];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        add_action('wp_ajax_btw_importer_upload_file', [$this, 'ajax_upload_file']);
        add_action('wp_ajax_btw_importer_extract_data', [$this, 'ajax_extract_data']);
        add_action('wp_ajax_btw_importer_import_batch', [$this, 'ajax_import_batch']);
        add_action('wp_ajax_btw_importer_pause_import', [$this, 'ajax_pause_import']);
        add_action('wp_ajax_btw_importer_resume_import', [$this, 'ajax_resume_import']);
        add_action('wp_ajax_btw_importer_cancel_import', [$this, 'ajax_cancel_import']);
    }

    public function add_menu() {
        add_menu_page(
            'BtW Importer', 'BtW Importer', 'manage_options',
            'btw-importer', [$this, 'import_page'], 'dashicons-upload'
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_btw-importer') return;
        wp_enqueue_script('btw-importer', plugin_dir_url(__FILE__).'btw-importer.js', ['jquery'], '4.0.0', true);
        wp_enqueue_style('btw-importer-style', plugin_dir_url(__FILE__).'btw-importer-style.css', [], '4.0.0');
        wp_localize_script('btw-importer', 'btw_importer', ['ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('btw_importer_nonce')]);
    }

    public function import_page() {
        echo '<div class="wrap btw_importer_wrap">
            <div class="btw_importer_header">
                <h1>BtW Importer</h1>
                <p class="btw_importer_subtitle">A powerful yet simple migration tool, BtW Importer helps you seamlessly transfer posts, images, and formatting from Blogger (Blogspot) to WordPress.</p>
            </div>
            
            <div id="btw_importer_notice" class="btw_importer_notice">
                <div class="btw_importer_notice_header">
                    <span class="dashicons dashicons-warning"></span>
                    <h2>Please Read Before Importing</h2>
                </div>
                <ul class="btw_importer_notice_list">
                    <li><span class="dashicons dashicons-no"></span> This plugin doesn&apos;t overwrite existing posts with the same name. If you&apos;ve previously used an importer, it&apos;s recommended to manually delete the previously imported content.</li>
                    <li><span class="dashicons dashicons-no"></span> 301 redirects only work if you previously used a custom domain on Blogspot and you&apos;re moving that domain to WordPress.</li>
                    <li><span class="dashicons dashicons-no"></span> You can pause and resume the import process at any time. However, if you leave the page, the progress will be cancelled or discarded.</li>
                    <li><span class="dashicons dashicons-no"></span> Only images from Google/Blogspot will be downloaded.</li>
                    <li><span class="dashicons dashicons-no"></span> Be sure to manually check your content after import.</li>
                </ul>
                <div class="btw_importer_checkbox_wrapper">
                    <input type="checkbox" id="btw_importer_agree_notice" class="btw_importer_checkbox">
                    <label for="btw_importer_agree_notice">I&apos;ve read and understood the information above</label>
                </div>
            </div>
            
            <div id="btw_importer_steps" class="btw_importer_steps" style="display:none;">
                <div class="btw_importer_step_item" data-step="1">
                    <div class="btw_importer_step_number">
                        <span class="btw_importer_step_num">1</span>
                        <span class="dashicons dashicons-yes-alt btw_importer_step_check"></span>
                    </div>
                    <div class="btw_importer_step_label">Upload File</div>
                    <div class="btw_importer_step_connector"></div>
                </div>
                <div class="btw_importer_step_item" data-step="2">
                    <div class="btw_importer_step_number">
                        <span class="btw_importer_step_num">2</span>
                        <span class="dashicons dashicons-yes-alt btw_importer_step_check"></span>
                    </div>
                    <div class="btw_importer_step_label">Extract Data</div>
                    <div class="btw_importer_step_connector"></div>
                </div>
                <div class="btw_importer_step_item" data-step="3">
                    <div class="btw_importer_step_number">
                        <span class="btw_importer_step_num">3</span>
                        <span class="dashicons dashicons-yes-alt btw_importer_step_check"></span>
                    </div>
                    <div class="btw_importer_step_label">Import Content</div>
                </div>
            </div>
            
            <div id="btw_importer_step_upload" class="btw_importer_step" style="display:none;">
                <div class="btw_importer_step_header">
                    <h2><span class="dashicons dashicons-upload"></span> Step 1: Upload Blogger Export File</h2>
                </div>
                <div class="btw_importer_upload_box">
                    <input type="file" id="btw_importer_file_input" accept=".xml,.atom" class="btw_importer_file_input" />
                    <label for="btw_importer_file_input" class="btw_importer_file_label">
                        <span class="dashicons dashicons-media-document"></span>
                        Choose your Blogger export file (.xml or .atom)
                    </label>
                </div>
                <button id="btw_importer_upload_btn" class="button button-primary btw_importer_btn" disabled>
                    <span class="dashicons dashicons-upload"></span> Upload File
                </button>
                <div id="btw_importer_upload_status" class="btw_importer_status"></div>
            </div>
            
            <div id="btw_importer_step_extract" class="btw_importer_step" style="display:none;">
                <div class="btw_importer_step_header">
                    <h2><span class="dashicons dashicons-admin-page"></span> Step 2: Extract Data</h2>
                </div>
                <p>File uploaded successfully. Click below to extract posts and pages.</p>
                <button id="btw_importer_extract_btn" class="button button-primary btw_importer_btn">
                    <span class="dashicons dashicons-admin-page"></span> Extract Data
                </button>
                <div id="btw_importer_extract_status" class="btw_importer_status"></div>
            </div>
            
            <div id="btw_importer_step_import" class="btw_importer_step" style="display:none;">
                <div class="btw_importer_step_header">
                    <h2><span class="dashicons dashicons-download"></span> Step 3: Import Content</h2>
                </div>
                <div id="btw_import_info" class="btw_importer_info_box"></div>
                <div class="btw_importer_controls">
                    <button id="btw_importer_start_import_btn" class="button btw_importer_start_btn">
                        <span class="dashicons dashicons-controls-play"></span> Start
                    </button>
                    <button id="btw_importer_pause_btn" class="button btw_importer_pause_btn" disabled>
                        <span class="dashicons dashicons-controls-pause"></span> Pause
                    </button>
                    <button id="btw_importer_resume_btn" class="button btw_importer_resume_btn" disabled>
                        <span class="dashicons dashicons-controls-play"></span> Resume
                    </button>
                    <button id="btw_importer_cancel_btn" class="button btw_importer_cancel_btn" disabled>
                        <span class="dashicons dashicons-no"></span> Cancel
                    </button>
                </div>
                <div id="btw_importer_progress_container" class="btw_importer_progress_container" style="display:none;">
                    <div class="btw_importer_progress_bar">
                        <div id="btw_importer_progress_fill" class="btw_importer_progress_fill"></div>
                        <span id="btw_importer_progress_text" class="btw_importer_progress_text">0%</span>
                    </div>
                    <p id="btw_importer_timer" class="btw_importer_timer"><span class="dashicons dashicons-clock"></span> Elapsed time: 0s</p>
                </div>
                <div id="btw_importer_import_log" class="btw_importer_log"></div>
            </div>
        </div>';
    }

    public function ajax_upload_file() {
        check_ajax_referer('btw_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
            wp_send_json_error('No file uploaded');
        }
        
        // Validate file extension
        $allowed_extensions = ['xml', 'atom'];
        $file_extension = strtolower(pathinfo(sanitize_file_name($_FILES['file']['name']), PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_extensions, true)) {
            wp_send_json_error('Invalid file type. Only .xml and .atom files are allowed.');
        }
        
        // Clean old temp files first
        $upload_dir = wp_upload_dir();
        $btw_importer_temp_dir = $upload_dir['basedir'] . '/btw-importer-temp/';
        if (file_exists($btw_importer_temp_dir)) {
            $this->clean_temp_folder($btw_importer_temp_dir);
        }
        
        // Custom upload handler for temp directory - add filters temporarily
        add_filter('upload_dir', [$this, 'btw_importer_upload_dir']);
        add_filter('upload_mimes', [$this, 'btw_importer_allowed_mimes']);
        add_filter('wp_check_filetype_and_ext', [$this, 'btw_importer_check_filetype'], 10, 5);
        
        $upload_overrides = [
            'test_form' => false,
            'mimes' => [
                'xml' => 'application/xml',
                'atom' => 'application/atom+xml',
            ],
            'unique_filename_callback' => function($dir, $name, $ext) {
                return 'import_' . time() . '_' . sanitize_file_name($name);
            }
        ];
        
        $uploaded_file = wp_handle_upload($_FILES['file'], $upload_overrides);
        
        // Remove filters immediately after upload
        remove_filter('wp_check_filetype_and_ext', [$this, 'btw_importer_check_filetype'], 10);
        remove_filter('upload_mimes', [$this, 'btw_importer_allowed_mimes']);
        remove_filter('upload_dir', [$this, 'btw_importer_upload_dir']);
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error($uploaded_file['error']);
        }
        
        $filepath = $uploaded_file['file'];
        $filename = basename($filepath);
        
        set_transient('btw_importer_current_file', $filepath, DAY_IN_SECONDS);
        
        wp_send_json_success([
            'filepath' => $filepath,
            'filename' => $filename,
            'size' => size_format(filesize($filepath))
        ]);
    }
    
    /**
     * Custom upload directory for importer temp files
     */
    public function btw_importer_upload_dir($uploads) {
        $uploads['subdir'] = '/btw-importer-temp';
        $uploads['path'] = $uploads['basedir'] . '/btw-importer-temp';
        $uploads['url'] = $uploads['baseurl'] . '/btw-importer-temp';
        return $uploads;
    }
    
    /**
     * Allow XML and Atom file uploads for importer
     */
    public function btw_importer_allowed_mimes($mimes) {
        $mimes['xml'] = 'application/xml';
        $mimes['atom'] = 'application/atom+xml';
        return $mimes;
    }
    
    /**
     * Override file type check for XML and Atom files
     */
    public function btw_importer_check_filetype($data, $file, $filename, $mimes, $real_mime = null) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if ($ext === 'xml') {
            $data['ext'] = 'xml';
            $data['type'] = 'application/xml';
            $data['proper_filename'] = $filename;
        } elseif ($ext === 'atom') {
            $data['ext'] = 'atom';
            $data['type'] = 'application/atom+xml';
            $data['proper_filename'] = $filename;
        }
        
        return $data;
    }

    public function ajax_extract_data() {
        check_ajax_referer('btw_importer_nonce', 'nonce');
        
        $filepath = get_transient('btw_importer_current_file');
        if (!$filepath || !file_exists($filepath)) {
            wp_send_json_error('File not found. Please upload again.');
        }
        
        $content = file_get_contents($filepath);
        $content = preg_replace('/^\x{FEFF}/u', '', $content);
        $content = preg_replace('/[^\P{C}\n\r\t]+/u', '', $content);
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        
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
            if ($bloggerType == 'page' || $bloggerType == 'post') {
                $published_raw = (string)$entry->published;
                $date_gmt = gmdate('Y-m-d H:i:s', strtotime($published_raw));
                
                $posts[] = [
                    'title' => sanitize_text_field((string)$entry->title),
                    'content' => (string)$entry->content,
                    'author' => isset($entry->author->name) ? sanitize_text_field((string)$entry->author->name) : '',
                    'post_type' => $bloggerType,
                    'date' => get_date_from_gmt($date_gmt, 'Y-m-d H:i:s'),
                    'date_gmt' => $date_gmt,
                    'categories' => $this->extract_categories($entry),
                    'filename' => trim((string)$entry->children('blogger', true)->filename),
                    'status' => $this->get_post_status($entry)
                ];
            }
        }
        
        // Reset image cache untuk import baru
        delete_transient('btw_importer_image_cache');
        
        update_option('btw_importer_data', $posts, false);
        update_option('btw_importer_status', [
            'total' => count($posts),
            'processed' => 0,
            'status' => 'ready',
            'start_time' => null
        ], false);
        
        $post_count = count(array_filter($posts, function($p) { return $p['post_type'] === 'post'; }));
        $page_count = count(array_filter($posts, function($p) { return $p['post_type'] === 'page'; }));
        
        wp_send_json_success([
            'total' => count($posts),
            'posts' => $post_count,
            'pages' => $page_count
        ]);
    }

    public function ajax_import_batch() {
        check_ajax_referer('btw_importer_nonce', 'nonce');
        
        $batch_size = 1;
        $status = get_option('btw_importer_status');
        
        if ($status['status'] === 'paused') {
            wp_send_json_error('Import is paused');
        }
        
        if (!$status['start_time']) {
            $status['start_time'] = time();
            update_option('btw_importer_status', $status, false);
        }
        
        $all_posts = get_option('btw_importer_data', []);
        $start_index = $status['processed'];
        $batch = array_slice($all_posts, $start_index, $batch_size);
        
        $results = [];
        foreach ($batch as $post_data) {
            $result = $this->import_single_post_internal($post_data);
            $results[] = $result;
            $status['processed']++;
        }
        
        $status['status'] = $status['processed'] >= $status['total'] ? 'completed' : 'running';
        update_option('btw_importer_status', $status, false);
        
        // Cleanup jika selesai
        if ($status['status'] === 'completed') {
            delete_transient('btw_importer_image_cache');
        }
        
        wp_send_json_success([
            'processed' => $status['processed'],
            'total' => $status['total'],
            'status' => $status['status'],
            'results' => $results
        ]);
    }

    public function ajax_pause_import() {
        check_ajax_referer('btw_importer_nonce', 'nonce');
        $status = get_option('btw_importer_status');
        $status['status'] = 'paused';
        update_option('btw_importer_status', $status, false);
        wp_send_json_success(['status' => 'paused']);
    }

    public function ajax_resume_import() {
        check_ajax_referer('btw_importer_nonce', 'nonce');
        $status = get_option('btw_importer_status');
        $status['status'] = 'running';
        update_option('btw_importer_status', $status, false);
        wp_send_json_success(['status' => 'running']);
    }

    public function ajax_cancel_import() {
        check_ajax_referer('btw_importer_nonce', 'nonce');
        delete_option('btw_importer_data');
        delete_option('btw_importer_status');
        delete_transient('btw_importer_current_file');
        delete_transient('btw_importer_image_cache');
        wp_send_json_success(['status' => 'cancelled']);
    }

    private function import_single_post_internal($raw_post) {
        $title = sanitize_text_field($raw_post['title'] ?? '');
        $author = sanitize_text_field($raw_post['author'] ?? '');
        $post_type = in_array($raw_post['post_type'], ['post','page']) ? $raw_post['post_type'] : 'post';
        $date = sanitize_text_field($raw_post['date'] ?? '');
        $date_gmt = sanitize_text_field($raw_post['date_gmt'] ?? '');
        $categories = $raw_post['categories'] ?? [];
        $filename = sanitize_text_field($raw_post['filename'] ?? '');
        
        $allowed_tags = wp_kses_allowed_html('post');
        $allowed_tags['iframe'] = [
            'src' => true, 'width' => true, 'height' => true,
            'frameborder' => true, 'allowfullscreen' => true,
            'class' => true, 'youtube-src-id' => true
        ];
        $content = wp_kses($raw_post['content'] ?? '', $allowed_tags);
        $post_status = in_array($raw_post['status'], ['publish','draft','trash']) ? $raw_post['status'] : 'publish';
        
        $msgs = [];
        
        // Load image cache dari transient
        $this->downloaded_images = get_transient('btw_importer_image_cache');
        if (!$this->downloaded_images) {
            $this->downloaded_images = [];
        }
        
        $author_id = 1;
        if ($author) {
            $user = get_user_by('login', sanitize_user($author, true));
            if ($user) $author_id = $user->ID;
        }
        
        require_once ABSPATH.'wp-admin/includes/image.php';
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $post_status,
            'post_date' => $date,
            'post_date_gmt' => $date_gmt,
            'post_author' => $author_id,
            'post_type' => $post_type
        ]);
        
        if (is_wp_error($post_id)) {
            return [
                'success' => false, 
                'title' => $title, 
                'messages' => ['❌ Failed to insert post']
            ];
        }
        
        // Redirect meta
        if ($filename) {
            if ($filename[0] !== '/') $filename = '/' . $filename;
            add_post_meta($post_id, '_old_permalink', $filename, true);
            $new_url = get_permalink($post_id);
            $msgs[] = '🔁 Created 301 redirect: ' . $filename . ' → ' . $new_url;
        }
        
        // Categories
        if (!empty($categories) && $post_type === 'post') {
            $cat_ids = [];
            foreach ($categories as $cat_name) {
                $term = term_exists($cat_name, 'category');
                if (!$term) {
                    $new_term = wp_create_category($cat_name);
                    if (!is_wp_error($new_term)) {
                        $cat_ids[] = $new_term;
                        $msgs[] = '🏷 Created category: ' . $cat_name;
                    }
                } else {
                    $cat_ids[] = $term['term_id'];
                    $msgs[] = '🏷 Using category: ' . $cat_name;
                }
            }
            if (!empty($cat_ids)) wp_set_post_categories($post_id, $cat_ids);
        }
        
        // Images
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
                $content = str_replace($img_url, $this->downloaded_images[$img_url], $content);
                $msgs[] = '✅ Used cached image: ' . $img_url;
                continue;
            }
            
            $msgs[] = '⏳ Downloading: ' . $img_url;
            $tmp = download_url($img_url);
            
            if (is_wp_error($tmp)) {
                $msgs[] = '⚠ Failed to download: ' . $img_url;
                continue;
            }
            
            $file = ['name' => basename(wp_parse_url($img_url, PHP_URL_PATH)), 'tmp_name' => $tmp];
            $media_id = media_handle_sideload($file, $post_id);
            
            if (is_wp_error($media_id)) {
                wp_delete_file($tmp);
                $msgs[] = '⚠ Failed to attach: ' . $img_url;
                continue;
            }
            
            $new_url = wp_get_attachment_url($media_id);
            if ($new_url) {
                $this->downloaded_images[$img_url] = $new_url;
                // SIMPAN KE TRANSIENT
                set_transient('btw_importer_image_cache', $this->downloaded_images, DAY_IN_SECONDS);
                $content = str_replace($img_url, $new_url, $content);
                $msgs[] = '✅ Replaced: ' . $img_url . ' → ' . $new_url;
                if (!$first_media_id) $first_media_id = $media_id;
            }
        }
        
        wp_update_post(['ID' => $post_id, 'post_content' => $content]);
        
        if ($first_media_id) {
            set_post_thumbnail($post_id, $first_media_id);
            $msgs[] = '⭐ Set featured image';
        }
        
        $msgs[] = '📌 Post status: ' . $post_status;
        
        return [
            'success' => true, 
            'title' => $title, 
            'type' => $post_type,
            'messages' => $msgs
        ];
    }

    private function extract_categories($entry) {
        $categories = [];
        foreach ($entry->category as $cat) {
            $term = (string)$cat['term'];
            if ($term && strpos($term, '#') !== 0) {
                $categories[] = sanitize_text_field($term);
            }
        }
        return $categories;
    }

    private function get_post_status($entry) {
        $status_raw = strtolower((string)$entry->children('blogger', true)->status);
        if ($status_raw === 'draft') return 'draft';
        if ($status_raw === 'deleted') return 'trash';
        return 'publish';
    }

    private function clean_temp_folder($dir) {
        $files = glob($dir . 'import_*');
        if (!$files) {
            return;
        }
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) >= DAY_IN_SECONDS) {
                wp_delete_file($file);
            }
        }
    }
}

new btw_importer_Importer();
