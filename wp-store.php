<?php
/**
 * Plugin Name: WP-Store
 * Plugin URI: https://ilterra.com/wp-store
 * Description: WP Plugin Store from iLTerra. Alternative plugin manager.
 * Version: 0.1.0
 * Author: iLTerra
 * Author URI: https://ilterra.com
 * Text Domain: wp-store
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Load available plugin prefixes from configuration file.
 *
 * @return array
 */
function wp_store_available_plugins() {
    $file = __DIR__ . '/config/plugins.php';
    if ( file_exists( $file ) ) {
        $plugins = include $file;
        if ( is_array( $plugins ) ) {
            return $plugins;
        }
    }
    return [];
}

// Register admin menu.
add_action( 'admin_menu', 'wp_store_register_menu' );
function wp_store_register_menu() {
    add_menu_page(
        'WP Plugin Store from iLTerra',
        'WP-Store',
        'manage_options',
        'wp-store',
        '__return_null',
        'dashicons-store',
        65
    );

    add_submenu_page(
        'wp-store',
        'WP Plugin Store from iLTerra',
        'Manager',
        'manage_options',
        'wp-store-manager',
        'wp_store_manager_page'
    );

    // Remove duplicate submenu linking to the top-level menu.
    remove_submenu_page( 'wp-store', 'wp-store' );
}

// Handle actions: activate, deactivate, delete, install, update.
add_action( 'admin_init', 'wp_store_handle_actions' );
function wp_store_handle_actions() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    if ( empty( $_GET['wp_store_action'] ) ) {
        return;
    }

    $action = sanitize_key( $_GET['wp_store_action'] );
    $plugin = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';
    $prefix = isset( $_GET['prefix'] ) ? sanitize_text_field( wp_unslash( $_GET['prefix'] ) ) : '';
    $version = isset( $_REQUEST['version'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['version'] ) ) : '';

    switch ( $action ) {
        case 'activate':
            check_admin_referer( 'wp-store-activate_' . $plugin );
            activate_plugin( $plugin );
            break;
        case 'deactivate':
            check_admin_referer( 'wp-store-deactivate_' . $plugin );
            deactivate_plugins( $plugin );
            break;
        case 'delete':
            check_admin_referer( 'wp-store-delete_' . $plugin );
            delete_plugins( [ $plugin ] );
            break;
        case 'install':
            check_admin_referer( 'wp-store-install_' . $prefix );
            $output = wp_store_install_plugin( $prefix, $version );
            if ( is_wp_error( $output ) ) {
                wp_die( esc_html( $output->get_error_message() ) . wp_store_return_link() );
            }
            wp_die( $output . wp_store_return_link() );
        case 'update':
            check_admin_referer( 'wp-store-update_' . $plugin );
            if ( empty( $version ) ) {
                wp_store_version_selector_page( $prefix, 'update', $plugin );
            }
            $output = wp_store_install_plugin( $prefix, $version, true );
            if ( is_wp_error( $output ) ) {
                wp_die( esc_html( $output->get_error_message() ) . wp_store_return_link() );
            }
            wp_die( $output . wp_store_return_link() );
    }

    wp_safe_redirect( remove_query_arg( [ 'wp_store_action', 'plugin', 'prefix', '_wpnonce' ] ) );
    exit;
}

/**
 * Display the manager page.
 */
function wp_store_manager_page() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    $available = wp_store_available_plugins();
    $available_prefixes = array_keys( $available );

    // Filter installed plugins by available prefixes.
    $all_plugins = get_plugins();
    $plugins     = [];
    foreach ( $all_plugins as $file => $data ) {
        if ( in_array( dirname( $file ), $available_prefixes, true ) ) {
            $plugins[ $file ] = $data;
        }
    }

    echo '<div class="wrap">';
    echo '<h1>WP Plugin Store from iLTerra</h1>';

    // Installed plugins list.
    echo '<h2>' . esc_html__( 'Installed Plugins', 'wp-store' ) . '</h2>';
    if ( ! empty( $plugins ) ) {
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . esc_html__( 'Plugin', 'wp-store' ) . '</th><th>' . esc_html__( 'Actions', 'wp-store' ) . '</th></tr></thead><tbody>';
        foreach ( $plugins as $file => $data ) {
            $is_active = is_plugin_active( $file );
            $actions   = [];
            if ( $is_active ) {
                $url       = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'deactivate', 'plugin' => $file ] ), 'wp-store-deactivate_' . $file );
                $actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Deactivate', 'wp-store' ) . '</a>';
            } else {
                $url       = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'activate', 'plugin' => $file ] ), 'wp-store-activate_' . $file );
                $actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Activate', 'wp-store' ) . '</a>';
            }
            $url       = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'delete', 'plugin' => $file ] ), 'wp-store-delete_' . $file );
            $actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Delete', 'wp-store' ) . '</a>';

            $dir = dirname( $file );
            $latest = wp_store_get_latest_version( $dir );
            if ( $latest ) {
                $latest_clean = preg_replace( '/^v/', '', $latest );
                if ( $data['Version'] !== $latest_clean ) {
                    $url       = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'update', 'plugin' => $file, 'prefix' => $dir ] ), 'wp-store-update_' . $file );
                    $actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Update version', 'wp-store' ) . '</a>';
                }
            }

            echo '<tr>';
            echo '<td><strong>' . esc_html( $data['Name'] ) . '</strong><br/>' . esc_html( $data['Description'] ) . '<br/><em>' . esc_html( $data['Version'] ) . '</em></td>';
            echo '<td>' . implode( ' | ', $actions ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__( 'No plugins found.', 'wp-store' ) . '</p>';
    }

    // Available plugins.
    echo '<h2>' . esc_html__( 'Available Plugins', 'wp-store' ) . '</h2>';
    $installed_prefixes = [];
    foreach ( array_keys( $plugins ) as $plugin_file ) {
        $installed_prefixes[] = dirname( $plugin_file );
    }
    if ( ! empty( $available ) ) {
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . esc_html__( 'Plugin', 'wp-store' ) . '</th><th>' . esc_html__( 'Actions', 'wp-store' ) . '</th></tr></thead><tbody>';
        foreach ( $available as $prefix => $info ) {
            if ( in_array( $prefix, $installed_prefixes, true ) ) {
                continue;
            }
            $url = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'install', 'prefix' => $prefix ] ), 'wp-store-install_' . $prefix );
            echo '<tr>';
            echo '<td><strong>' . esc_html( $info['name'] ) . '</strong><br/>' . esc_html( $info['description'] ) . '</td>';
            echo '<td><a href="' . esc_url( $url ) . '">' . esc_html__( 'Install', 'wp-store' ) . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__( 'No plugins available for installation.', 'wp-store' ) . '</p>';
    }

    echo '</div>';
}

/**
 * Retrieve available versions for a plugin prefix from GitHub tags.
 *
 * @param string $prefix Plugin prefix.
 * @return array Array of version strings sorted from latest to oldest.
 */
function wp_store_get_versions( $prefix ) {
    $url      = 'https://api.github.com/repos/ilterracom/wp-store/git/refs/tags/' . rawurlencode( $prefix );
    $response = wp_remote_get( $url, [ 'timeout' => 15 ] );
    if ( is_wp_error( $response ) ) {
        return [];
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( 404 === (int) $code ) {
        return [];
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    if ( empty( $data ) ) {
        return [];
    }

    // When a single ref is matched GitHub returns an object; normalize to array.
    if ( isset( $data['ref'] ) ) {
        $data = [ $data ];
    }
    if ( ! is_array( $data ) ) {
        return [];
    }

    $versions = [];
    foreach ( $data as $item ) {
        if ( empty( $item['ref'] ) ) {
            continue;
        }
        $parts   = explode( '/', $item['ref'] );
        $version = end( $parts );
        if ( preg_match( '/^v(\d+\.\d+\.\d+)-build-(\d+)$/', $version, $m ) ) {
            $versions[] = [
                'full'   => $version,
                'semver' => $m[1],
                'build'  => (int) $m[2],
            ];
        }
    }

    if ( empty( $versions ) ) {
        return [];
    }

    usort( $versions, function( $a, $b ) {
        $cmp = version_compare( $a['semver'], $b['semver'] );
        if ( 0 === $cmp ) {
            return $a['build'] <=> $b['build'];
        }
        return $cmp;
    } );

    $versions = array_reverse( $versions );

    return array_map( function( $v ) {
        return $v['full'];
    }, $versions );
}

/**
 * Retrieve the latest available version for a plugin prefix from GitHub tags.
 *
 * @param string $prefix Plugin prefix.
 * @return string Latest version string or empty on failure.
 */
function wp_store_get_latest_version( $prefix ) {
    $versions = wp_store_get_versions( $prefix );
    return $versions ? $versions[0] : '';
}

/**
 * Build download URL for a plugin prefix and version.
 *
 * @param string $prefix  Plugin prefix.
 * @param string $version Version string.
 * @return string Download URL.
 */
function wp_store_build_download_url( $prefix, $version ) {
    return sprintf( 'https://github.com/ilterracom/wp-store/releases/download/%s/%s/%s_%s.zip', $prefix, $version, $prefix, $version );
}

/**
 * Install or update a plugin from the GitHub repository.
 *
 * @param string $prefix  Plugin prefix.
 * @param string $version   Optional version string. Defaults to latest release.
 * @param bool   $overwrite Whether to overwrite existing plugin.
 */
function wp_store_install_plugin( $prefix, $version = '', $overwrite = false ) {
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    include_once ABSPATH . 'wp-admin/includes/file.php';

    $messages = [];
    $messages[] = sprintf( 'Preparing installation for %s...', $prefix );

    if ( empty( $version ) ) {
        $version = wp_store_get_latest_version( $prefix );
    }

    if ( empty( $version ) ) {
        return new WP_Error( 'wp_store_no_version', __( 'No version found for the plugin.', 'wp-store' ) );
    }

    $messages[] = sprintf( 'Selected version: %s', $version );
    $download_url = wp_store_build_download_url( $prefix, $version );
    $messages[]   = sprintf( 'Downloading package from %s', $download_url );

    $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );

    add_filter( 'upgrader_clear_destination', function( $clear ) use ( $overwrite ) {
        return $overwrite ? true : $clear;
    } );

    $result = $upgrader->install( $download_url );
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    $plugin_file = $upgrader->plugin_info();
    if ( $plugin_file ) {
        $messages[] = __( 'Activating plugin...', 'wp-store' );
        activate_plugin( $plugin_file );
        $messages[] = __( 'Plugin activated.', 'wp-store' );
    }

    return implode( '<br/>', array_map( 'esc_html', $messages ) );
}

/**
 * Display version selection page for install or update actions.
 *
 * @param string $prefix Plugin prefix.
 * @param string $action Current action (install|update).
 * @param string $plugin Optional plugin file for update action.
 */
function wp_store_version_selector_page( $prefix, $action, $plugin = '' ) {
    $versions = wp_store_get_versions( $prefix );
    if ( empty( $versions ) ) {
        wp_die( esc_html__( 'No versions available for the plugin.', 'wp-store' ) . wp_store_return_link() );
    }

    $current_version = '';
    if ( 'update' === $action && $plugin ) {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
        $current_version = isset( $data['Version'] ) ? $data['Version'] : '';
    }

    $latest = $versions[0];
    $button_text = ( 'update' === $action ) ? __( 'Update', 'wp-store' ) : __( 'Install', 'wp-store' );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Select Version', 'wp-store' ) . '</h1>';
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="wp-store-manager" />';
    echo '<input type="hidden" name="wp_store_action" value="' . esc_attr( $action ) . '" />';
    echo '<input type="hidden" name="prefix" value="' . esc_attr( $prefix ) . '" />';
    if ( $plugin ) {
        echo '<input type="hidden" name="plugin" value="' . esc_attr( $plugin ) . '" />';
    }
    wp_nonce_field( 'wp-store-' . $action . '_' . ( 'update' === $action ? $plugin : $prefix ) );

    echo '<select name="version">';
    foreach ( $versions as $v ) {
        $clean = preg_replace( '/^v/', '', $v );
        $attrs = '';
        $label = $clean;
        if ( $clean === $current_version ) {
            $attrs = ' disabled="disabled"';
            $label .= ' ' . esc_html__( '(current)', 'wp-store' );
        } elseif ( $v === $latest ) {
            $attrs = ' selected="selected"';
            $label .= ' ' . esc_html__( '(latest)', 'wp-store' );
        }
        echo '<option value="' . esc_attr( $v ) . '"' . $attrs . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html( $button_text ) . '</button></p>';
    echo '</form>';
    echo wp_store_return_link();
    echo '</div>';
    exit;
}

/**
 * Helper to build return link HTML.
 *
 * @return string
 */
function wp_store_return_link() {
    $url = add_query_arg( [ 'page' => 'wp-store-manager' ], admin_url( 'admin.php' ) );
    return '<br/><a href="' . esc_url( $url ) . '">' . esc_html__( 'Return to WP-Store Manager', 'wp-store' ) . '</a>';
}
