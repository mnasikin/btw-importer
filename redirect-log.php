<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class btw_importer_Redirect_Log {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'btw_importer_add_redirect_log_menu' ] );
        add_action( 'admin_init', [ $this, 'btw_importer_handle_clear_log' ] );
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

    public function btw_importer_handle_clear_log() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if (
            isset( $_POST['btw_clear_log_nonce'] ) &&
            wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['btw_clear_log_nonce'] ) ),
                'btw_clear_log'
            )
        ) {
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    '_old_permalink'
                )
            );

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

    public function btw_importer_render_redirect_log_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'btw-importer' ) );
        }

        global $wpdb;

        // Sanitize input
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Safe: cached and read-only query
        $paged  = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

        $allowed_orderby = [ 'p.post_date', 'p.post_type' ];
        $orderby         = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'p.post_date'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Safe: cached and read-only query
        $orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'p.post_date';

        $order_raw = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Safe: cached and read-only query
        $order     = in_array( $order_raw, [ 'ASC', 'DESC' ], true ) ? $order_raw : 'DESC';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe: cached and read-only query
        if ( isset( $_GET['btw_redirect_log_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['btw_redirect_log_nonce'] ) ), 'btw_redirect_log_nonce' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'btw-importer' ) );
        }

        $per_page = 25;
        $offset   = ( $paged - 1 ) * $per_page;

        $wheres = [ 'pm.meta_key = %s' ];
        $params = [ '_old_permalink' ];

        if ( $search ) {
            $wheres[] = 'pm.meta_value LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $where_sql     = implode( ' AND ', $wheres );
        $where_clause  = "WHERE $where_sql";
        $prepared_where = call_user_func_array(
            [ $wpdb, 'prepare' ],
            array_merge( [ $where_clause ], $params )
        );

        $orderby_sql = $orderby;
        $order_sql   = $order;

        $base_query = "
            SELECT SQL_CALC_FOUND_ROWS p.ID, p.post_type, p.post_date, pm.meta_value as old_slug
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        ";

        $query  = $base_query . ' ' . $prepared_where . " ORDER BY $orderby_sql $order_sql";
        $query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );

        $cache_key        = 'btw_redirect_log_' . md5( $query );
        $total_cache_key  = 'btw_redirect_log_total_' . md5( $query );

        $results = wp_cache_get( $cache_key, 'btw_importer' );

        if ( false === $results ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Safe: cached and read-only query
            $results = $wpdb->get_results( $query );
            wp_cache_set( $cache_key, $results, 'btw_importer', HOUR_IN_SECONDS );
        }

        $total_items = wp_cache_get( $total_cache_key, 'btw_importer' );

        if ( false === $total_items ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Safe: used only to count results, cached
            $total_items = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
            wp_cache_set( $total_cache_key, $total_items, 'btw_importer', HOUR_IN_SECONDS );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Redirect Log', 'btw-importer' ) . '</h1>';
        echo '<p>' . esc_html__( 'This table shows old Blogger slugs and the new WordPress URLs that have been created as redirects.', 'btw-importer' ) . '</p>';

        $clear_nonce  = wp_create_nonce( 'btw_clear_log' );
        $search_nonce = wp_create_nonce( 'btw_redirect_log_nonce' );

        echo '<form method="get" style="margin-bottom:10px;display:inline-block;margin-right:10px;">';
        echo '<input type="hidden" name="page" value="btw-redirect-log" />';
        echo '<input type="search" name="s" placeholder="' . esc_attr__( 'Search slug...', 'btw-importer' ) . '" value="' . esc_attr( $search ) . '" />';
        echo '<input type="hidden" name="btw_redirect_log_nonce" value="' . esc_attr( $search_nonce ) . '" />';
        echo '<input type="submit" class="button" value="' . esc_attr__( 'Search', 'btw-importer' ) . '" />';
        echo '</form>';

        echo '<form method="post" style="display:inline-block;" onsubmit="return confirm(\'' . esc_js( __( 'Are you sure you want to clear the entire redirect log?', 'btw-importer' ) ) . '\');">';
        echo '<input type="hidden" name="btw_clear_log_nonce" value="' . esc_attr( $clear_nonce ) . '" />';
        echo '<input type="submit" class="button button-danger" value="' . esc_attr__( 'Clear Log', 'btw-importer' ) . '" />';
        echo '</form>';

        if ( empty( $results ) ) {
            echo '<p>' . esc_html__( 'No redirects found.', 'btw-importer' ) . '</p>';
            echo '</div>';
            return;
        }

        $base_url = admin_url( 'admin.php?page=btw-redirect-log' );
        if ( $search ) {
            $base_url = add_query_arg( 's', urlencode( $search ), $base_url );
        }

        $columns = [
            'p.post_date' => __( 'Date', 'btw-importer' ),
            'p.post_type' => __( 'Post Type', 'btw-importer' ),
        ];

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
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
            $arrow = ( $orderby === $col ) ? ( 'ASC' === $order ? '↑' : '↓' ) : '';
            echo '<th><a href="' . esc_url( $link ) . '">' . esc_html( $label . ' ' . $arrow ) . '</a></th>';
        }

        echo '</tr></thead><tbody>';

        foreach ( $results as $row ) {
            $old_url = home_url( $row->old_slug );
            $new_url = get_permalink( $row->ID );
            echo '<tr>';
            echo '<td><a href="' . esc_url( $old_url ) . '" target="_blank">' . esc_html( $old_url ) . '</a></td>';
            echo '<td><a href="' . esc_url( $new_url ) . '" target="_blank">' . esc_html( $new_url ) . '</a></td>';
            echo '<td>' . esc_html( gmdate( 'Y-m-d', strtotime( $row->post_date ) ) ) . '</td>';
            echo '<td>' . esc_html( $row->post_type ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        $total_pages = ceil( $total_items / $per_page );
        if ( $total_pages > 1 ) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
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

        echo '</div>';
    }
}

new btw_importer_Redirect_Log();
