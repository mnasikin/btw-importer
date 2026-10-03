<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class btw_importer_Redirect_Log {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'btw_importer_add_redirect_log_menu' ] );
        add_action( 'admin_init', [ $this, 'btw_importer_handle_clear_log' ] );
        add_action( 'admin_init', [ $this, 'btw_importer_handle_delete_selected' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'btw_importer_enqueue_scripts' ] );
        add_action( 'admin_notices', [ $this, 'btw_importer_render_log_notices' ] );
    }

    public function btw_importer_add_redirect_log_menu() {
        add_submenu_page(
            'btw-importer',
            __( 'Redirect Log', 'btw-importer' ),
            __( 'Redirect Log', 'btw-importer' ),
            'manage_options',
            'btw-redirect-log',
            [ $this, 'btw_importer_render_redirect_log_page' ]
        );
    }

    public function btw_importer_enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_btw-importer' && $hook !== 'btw-importer_page_btw-redirect-log' ) {
            return;
        }
        
        wp_enqueue_style( 'btw-importer-style', plugin_dir_url( __FILE__ ) . 'btw-importer-style.css', [], '4.3.3' );
        
        if ( $hook === 'toplevel_page_btw-importer' ) {
            wp_enqueue_script('btw-importer', plugin_dir_url( __FILE__ ) . 'btw-importer.js', [ 'jquery' ], '4.3.3', true );
            wp_localize_script( 'btw-importer', 'btw_importer', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'btw_importer_nonce' )
            ]);
        }
    }

    public function btw_importer_handle_clear_log() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified below
        if (
            isset( $_POST['btw_importer_clear_log_nonce'] ) &&
            wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['btw_importer_clear_log_nonce'] ) ),
                'btw_importer_clear_log'
            )
        ) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for deleting redirect log meta
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    '_old_permalink'
                )
            );
            $this->btw_importer_flush_log_cache();

            add_action(
                'admin_notices',
                function () {
                    echo '<div class="notice notice-success is-dismissible"><p>'
                        . esc_html__( 'Redirect log cleared successfully.', 'btw-importer' )
                        . '</p></div>';
                }
            );
        }
    }

    public function btw_importer_handle_delete_selected() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified below
        if ( ! isset( $_POST['btw_importer_delete_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['btw_importer_delete_nonce'] ) ), 'btw_importer_delete_records' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'btw-importer' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $raw        = isset( $_POST['btw_importer_selected_records'] ) ? (array) wp_unslash( $_POST['btw_importer_selected_records'] ) : [];
        $meta_ids   = array_values( array_unique( array_filter( array_map( 'intval', $raw ) ) ) );

        if ( empty( $meta_ids ) ) {
            $this->btw_importer_redirect_log_redirect( 'none_selected' );
        }

        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $meta_ids ), '%d' ) );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %d placeholders, all values are passed to prepare()
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for deleting single redirect log records
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_id IN ($placeholders)",
                array_merge( [ '_old_permalink' ], $meta_ids )
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $this->btw_importer_flush_log_cache();
        $this->btw_importer_redirect_log_redirect( 'deleted', count( $meta_ids ) );
    }

    private function btw_importer_flush_log_cache() {
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'btw_importer' );
        }
    }

    private function btw_importer_redirect_log_redirect( $notice, $deleted = 0 ) {
        $args = [
            'page'       => 'btw-redirect-log',
            'btw_notice' => $notice,
        ];

        if ( $deleted > 0 ) {
            $args['btw_deleted'] = $deleted;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only values, used only to restore the current view
        if ( isset( $_GET['s'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only value, used only to restore the current view
            $search = sanitize_text_field( wp_unslash( $_GET['s'] ) );
            if ( '' !== $search ) {
                $args['s'] = $search;
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only value, used only to restore the current view
        if ( isset( $_GET['paged'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only value, used only to restore the current view
            $paged = max( 1, intval( wp_unslash( $_GET['paged'] ) ) );
            if ( $paged > 1 ) {
                $args['paged'] = $paged;
            }
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    public function btw_importer_render_log_notices() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only value, used only to detect the current screen
        if ( ! isset( $_GET['page'] ) || 'btw-redirect-log' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only value, only used for a display notice
        $notice = isset( $_GET['btw_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['btw_notice'] ) ) : '';

        if ( 'deleted' === $notice ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only value, only used for a display notice
            $deleted = isset( $_GET['btw_deleted'] ) ? max( 0, intval( wp_unslash( $_GET['btw_deleted'] ) ) ) : 0;
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(
                    sprintf(
                        _n( '%d redirect record deleted.', '%d redirect records deleted.', $deleted, 'btw-importer' ),
                        $deleted
                    )
                )
            );
        } elseif ( 'none_selected' === $notice ) {
            echo '<div class="notice notice-warning is-dismissible"><p>'
                . esc_html__( 'Please select at least one record to delete.', 'btw-importer' )
                . '</p></div>';
        }
    }

    public function btw_importer_render_redirect_log_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'btw-importer' ) );
        }

        global $wpdb;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation, nonce checked if provided
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation
        $paged  = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

        $allowed_orderby = [ 'p.post_date', 'p.post_type' ];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation
        $orderby         = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'p.post_date';
        $orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'p.post_date';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation
        $order_raw = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : '';
        $order     = in_array( $order_raw, [ 'ASC', 'DESC' ], true ) ? $order_raw : 'DESC';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below
        if ( isset( $_GET['btw_importer_redirect_log_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['btw_importer_redirect_log_nonce'] ) ), 'btw_importer_redirect_log_nonce' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'btw-importer' ) );
        }

        $per_page = 25;
        $offset   = ( $paged - 1 ) * $per_page;

        // Validate orderby and order against whitelist to prevent SQL injection
        $orderby_sql = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'p.post_date';
        $order_sql   = in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'DESC';

        // Build query - ORDER BY cannot use placeholders, but values are validated against whitelist
        if ( $search ) {
            $where_query = $wpdb->prepare(
                "WHERE pm.meta_key = %s AND pm.meta_value LIKE %s",
                '_old_permalink',
                '%' . $wpdb->esc_like( $search ) . '%'
            );
        } else {
            $where_query = $wpdb->prepare(
                "WHERE pm.meta_key = %s",
                '_old_permalink'
            );
        }

        $limit_query = $wpdb->prepare( 'LIMIT %d OFFSET %d', $per_page, $offset );

        // Combine query parts - ORDER BY uses validated whitelist values
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_query and $limit_query are prepared above, $orderby_sql and $order_sql are validated against whitelist
        $query = sprintf(
            "SELECT SQL_CALC_FOUND_ROWS p.ID, pm.meta_id, p.post_type, p.post_date, pm.meta_value as old_slug
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            %s
            ORDER BY %s %s
            %s",
            $where_query,
            $orderby_sql,
            $order_sql,
            $limit_query
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $cache_key       = 'btw_importer_redirect_log_' . md5( $query );
        $total_cache_key = 'btw_importer_redirect_log_total_' . md5( $query );

        $results = wp_cache_get( $cache_key, 'btw_importer' );

        if ( false === $results ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query parts are properly prepared above, ORDER BY uses validated whitelist
            $results = $wpdb->get_results( $query );
            wp_cache_set( $cache_key, $results, 'btw_importer', HOUR_IN_SECONDS );
        }

        $total_items = wp_cache_get( $total_cache_key, 'btw_importer' );

        if ( false === $total_items ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to get total count after SQL_CALC_FOUND_ROWS
            $total_items = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
            wp_cache_set( $total_cache_key, $total_items, 'btw_importer', HOUR_IN_SECONDS );
        }

        echo '<div class="wrap btw_importer_wrap">';
        
        echo '<div class="btw_importer_header">';
        echo '<h1><span class="dashicons dashicons-admin-links"></span> ' . esc_html__( 'Redirect Log', 'btw-importer' ) . '</h1>';
        echo '<p class="btw_importer_subtitle">' . esc_html__( 'This table shows old Blogger slugs and the new WordPress URLs that have been created as redirects.', 'btw-importer' ) . '</p>';
        echo '</div>';

        $clear_nonce   = wp_create_nonce( 'btw_importer_clear_log' );
        $search_nonce  = wp_create_nonce( 'btw_importer_redirect_log_nonce' );
        $delete_nonce  = wp_create_nonce( 'btw_importer_delete_records' );

        echo '<div class="btw_importer_upload_section">';
        echo '<div class="btw_importer_search_actions">';
        
        echo '<form method="get" class="btw_importer_search_form">';
        echo '<input type="hidden" name="page" value="btw-redirect-log" />';
        echo '<input type="search" name="s" class="btw_importer_search_input" placeholder="' . esc_attr__( 'Search slug...', 'btw-importer' ) . '" value="' . esc_attr( $search ) . '" />';
        echo '<input type="hidden" name="btw_importer_redirect_log_nonce" value="' . esc_attr( $search_nonce ) . '" />';
        echo '<button type="submit" class="button button-primary btw_importer_search_btn"><span class="dashicons dashicons-search"></span> ' . esc_attr__( 'Search', 'btw-importer' ) . '</button>';
        echo '</form>';

        echo '<form method="post" class="btw_importer_clear_form" onsubmit="return confirm(\'' . esc_js( __( 'Are you sure you want to clear the entire redirect log?', 'btw-importer' ) ) . '\');">';
        echo '<input type="hidden" name="btw_importer_clear_log_nonce" value="' . esc_attr( $clear_nonce ) . '" />';
        echo '<button type="submit" class="button btw_importer_clear_btn"><span class="dashicons dashicons-trash"></span> ' . esc_attr__( 'Clear Log', 'btw-importer' ) . '</button>';
        echo '</form>';
        
        echo '</div>';
        echo '</div>';

        if ( empty( $results ) ) {
            echo '<div class="btw_importer_notice btw_importer_empty_state">';
            echo '<span class="dashicons dashicons-info btw_importer_empty_icon"></span>';
            echo '<p>' . esc_html__( 'No redirects found.', 'btw-importer' ) . '</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        echo '<form method="post" class="btw_importer_delete_form" id="btw_importer_delete_form">';
        echo '<input type="hidden" name="btw_importer_delete_nonce" value="' . esc_attr( $delete_nonce ) . '" />';
        echo '<div class="btw_importer_delete_toolbar">';
        echo '<button type="submit" class="button btw_importer_delete_btn"><span class="dashicons dashicons-trash"></span> ' . esc_html__( 'Delete Selected', 'btw-importer' ) . '</button>';
        echo '<span class="btw_importer_delete_hint">' . esc_html__( 'Tick the checkboxes of the records you want to remove, then press Delete Selected.', 'btw-importer' ) . '</span>';
        echo '</div>';

        $base_url = admin_url( 'admin.php?page=btw-redirect-log' );
        if ( $search ) {
            $base_url = add_query_arg( 's', urlencode( $search ), $base_url );
        }

        $columns = [
            'p.post_date' => __( 'Date', 'btw-importer' ),
            'p.post_type' => __( 'Post Type', 'btw-importer' ),
        ];

        echo '<div class="btw_importer_table_wrapper">';
        echo '<table class="widefat striped btw_importer_table">';
        echo '<thead class="btw_importer_table_header"><tr>';
        echo '<th class="btw_importer_check_column"><input type="checkbox" id="btw_importer_select_all" class="btw_importer_select_all" aria-label="' . esc_attr__( 'Select all records', 'btw-importer' ) . '" /></th>';
        echo '<th>' . esc_html__( 'Old URL', 'btw-importer' ) . '</th>';
        echo '<th>' . esc_html__( 'New URL', 'btw-importer' ) . '</th>';

        foreach ( $columns as $col => $label ) {
            $new_order = ( $orderby === $col && $order === 'ASC' ) ? 'DESC' : 'ASC';
            $link      = add_query_arg(
                [
                    'orderby' => $col,
                    'order'   => $new_order,
                    'paged'   => 1,
                ],
                $base_url
            );
            $arrow = ( $orderby === $col ) ? ( 'ASC' === $order ? ' ↑' : ' ↓' ) : '';
            echo '<th><a href="' . esc_url( $link ) . '" class="btw_importer_sortable">' . esc_html( $label . $arrow ) . '</a></th>';
        }

        echo '</tr></thead><tbody>';

        foreach ( $results as $row ) {
            $old_url = home_url( $row->old_slug );
            $new_url = get_permalink( $row->ID );
            echo '<tr>';
            echo '<td class="btw_importer_check_column"><input type="checkbox" name="btw_importer_selected_records[]" value="' . esc_attr( (int) $row->meta_id ) . '" class="btw_importer_record_check" /></td>';
            echo '<td><a href="' . esc_url( $old_url ) . '" target="_blank" class="btw_importer_old_url">' . esc_html( $old_url ) . '</a></td>';
            echo '<td><a href="' . esc_url( $new_url ) . '" target="_blank" class="btw_importer_new_url">' . esc_html( $new_url ) . '</a></td>';
            echo '<td>' . esc_html( gmdate( 'Y-m-d', strtotime( $row->post_date ) ) ) . '</td>';
            echo '<td><span class="btw_importer_post_type_badge">' . esc_html( $row->post_type ) . '</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</form>';

        $total_pages = ceil( $total_items / $per_page );
        if ( $total_pages > 1 ) {
            echo '<div class="tablenav btw_importer_pagination"><div class="tablenav-pages">';
            echo wp_kses_post(
                paginate_links(
                    [
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $total_pages,
                        'add_args'  => [
                            's'       => $search,
                            'orderby' => $orderby,
                            'order'   => $order,
                        ],
                        'prev_text' => __( '« Prev', 'btw-importer' ),
                        'next_text' => __( 'Next »', 'btw-importer' ),
                    ]
                )
            );
            echo '</div></div>';
        }

        $none_selected  = wp_json_encode( __( 'Please select at least one record to delete.', 'btw-importer' ) );
        $confirm_delete = wp_json_encode( __( 'Delete the selected redirect records?', 'btw-importer' ) );
        echo '<script>
		( function () {
			var selectAll = document.getElementById( "btw_importer_select_all" );
			if ( selectAll ) {
				selectAll.addEventListener( "change", function () {
					var boxes = document.querySelectorAll( ".btw_importer_record_check" );
					for ( var i = 0; i < boxes.length; i++ ) {
						boxes[ i ].checked = selectAll.checked;
					}
				} );
			}

			var form = document.getElementById( "btw_importer_delete_form" );
			if ( form ) {
				form.addEventListener( "submit", function ( event ) {
					var boxes = document.querySelectorAll( ".btw_importer_record_check:checked" );
					if ( ! boxes.length ) {
						event.preventDefault();
						window.alert( ' . $none_selected . ' );
						return;
					}
					if ( ! window.confirm( ' . $confirm_delete . ' ) ) {
						event.preventDefault();
					}
				} );
			}
		} )();
		</script>';

        echo '</div>';
    }
}

new btw_importer_Redirect_Log();
