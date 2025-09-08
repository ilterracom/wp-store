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
        'WP-Store',
        'WP-Store',
        'manage_options',
        'wp-store',
        'wp_store_manager_page',
        'dashicons-store',
        65
    );
    add_submenu_page(
        'wp-store',
        'Manager',
        'Manager',
        'manage_options',
        'wp-store',
        'wp_store_manager_page'
    );
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
    $version = isset( $_GET['version'] ) ? sanitize_text_field( wp_unslash( $_GET['version'] ) ) : '';

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
            check_admin_referer( 'wp-store-install_' . $prefix . '_' . $version );
            wp_store_install_plugin( $prefix, $version );
            break;
        case 'update':
            check_admin_referer( 'wp-store-update_' . $plugin );
            wp_store_install_plugin( $prefix, $version, true );
            break;
    }

    wp_safe_redirect( remove_query_arg( [ 'wp_store_action', 'plugin', 'prefix', 'version', '_wpnonce' ] ) );
    exit;
}

/**
 * Display the manager page.
 */
function wp_store_manager_page() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>WP-Store Manager</h1>';

    // Installed plugins list.
    echo '<h2>' . esc_html__( 'Installed Plugins', 'wp-store' ) . '</h2>';
    $plugins = get_plugins();
    if ( ! empty( $plugins ) ) {
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . esc_html__( 'Plugin', 'wp-store' ) . '</th><th>' . esc_html__( 'Actions', 'wp-store' ) . '</th></tr></thead><tbody>';
        foreach ( $plugins as $file => $data ) {
            $is_active = is_plugin_active( $file );
            $actions   = [];
            if ( $is_active ) {
                $url = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'deactivate', 'plugin' => $file ] ), 'wp-store-deactivate_' . $file );
                $actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Deactivate', 'wp-store' ) . '</a>';
            } else {
                $url = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'activate', 'plugin' => $file ] ), 'wp-store-activate_' . $file );
                $actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Activate', 'wp-store' ) . '</a>';
            }
            $url = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'delete', 'plugin' => $file ] ), 'wp-store-delete_' . $file );
            $actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Delete', 'wp-store' ) . '</a>';

            // Update check for plugins managed by store.
            $dir = dirname( $file );
            $available = wp_store_available_plugins();
            if ( isset( $available[ $dir ] ) ) {
                $versions = wp_store_get_versions( $dir );
                if ( ! empty( $versions ) ) {
                    usort( $versions, 'version_compare' );
                    $latest = end( $versions );
                    if ( version_compare( $data['Version'], $latest, '<' ) ) {
                        $url = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'update', 'plugin' => $file, 'prefix' => $dir, 'version' => $latest ] ), 'wp-store-update_' . $file );
                        $actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Update to', 'wp-store' ) . ' ' . esc_html( $latest ) . '</a>';
                    }
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
    $available = wp_store_available_plugins();
    $installed_prefixes = [];
    foreach ( array_keys( $plugins ) as $plugin_file ) {
        $installed_prefixes[] = dirname( $plugin_file );
    }
    if ( ! empty( $available ) ) {
        echo '<ul>';
        foreach ( $available as $prefix => $info ) {
            if ( in_array( $prefix, $installed_prefixes, true ) ) {
                continue;
            }
            $url = add_query_arg( [ 'page' => 'wp-store', 'prefix' => $prefix ] );
            $url = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'show_versions', 'prefix' => $prefix ], $url ), 'wp-store-show_versions_' . $prefix );
            echo '<li><strong>' . esc_html( $info['name'] ) . '</strong> — ' . esc_html( $info['description'] ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Install', 'wp-store' ) . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>' . esc_html__( 'No plugins available for installation.', 'wp-store' ) . '</p>';
    }

    // Show versions for a requested prefix.
    if ( isset( $_GET['wp_store_action'] ) && 'show_versions' === $_GET['wp_store_action'] && ! empty( $_GET['prefix'] ) ) {
        $prefix   = sanitize_text_field( wp_unslash( $_GET['prefix'] ) );
        $versions = wp_store_get_versions( $prefix );
        if ( ! empty( $versions ) ) {
            echo '<h2>' . sprintf( esc_html__( 'Install %s', 'wp-store' ), esc_html( $available[ $prefix ]['name'] ) ) . '</h2>';
            echo '<ul>';
            foreach ( $versions as $version ) {
                $url = wp_nonce_url( add_query_arg( [ 'wp_store_action' => 'install', 'prefix' => $prefix, 'version' => $version ] ), 'wp-store-install_' . $prefix . '_' . $version );
                echo '<li>' . esc_html( $version ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Install', 'wp-store' ) . '</a></li>';
            }
            echo '</ul>';
        }
    }

    echo '</div>';
}

/**
 * Retrieve available versions for a plugin prefix from GitHub tags.
 *
 * @param string $prefix Plugin prefix.
 * @return array List of versions.
 */
function wp_store_get_versions( $prefix ) {
    $url      = 'https://api.github.com/repos/ilterracom/wp-store/git/matching-refs/tags/' . rawurlencode( $prefix ) . '/';
    $response = wp_remote_get( $url, [ 'timeout' => 15 ] );
    if ( is_wp_error( $response ) ) {
        return [];
    }
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    if ( empty( $data ) || ! is_array( $data ) ) {
        return [];
    }
    $versions = [];
    foreach ( $data as $item ) {
        if ( ! empty( $item['ref'] ) ) {
            $ref     = $item['ref']; // refs/tags/prefix/version
            $parts   = explode( '/', $ref );
            $version = end( $parts );
            if ( $version ) {
                $versions[] = $version;
            }
        }
    }
    return $versions;
}

/**
 * Build download URL for a plugin prefix and version.
 *
 * @param string $prefix  Plugin prefix.
 * @param string $version Version string.
 * @return string Download URL.
 */
function wp_store_build_download_url( $prefix, $version ) {
    return sprintf( 'https://github.com/ilterracom/wp-store/archive/refs/tags/%s/%s.zip', $prefix, $version );
}

/**
 * Install or update a plugin from the GitHub repository.
 *
 * @param string $prefix  Plugin prefix.
 * @param string $version Version string.
 * @param bool   $overwrite Whether to overwrite existing plugin.
 */
function wp_store_install_plugin( $prefix, $version, $overwrite = false ) {
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    include_once ABSPATH . 'wp-admin/includes/file.php';

    $download_url = wp_store_build_download_url( $prefix, $version );
    $upgrader     = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );

    add_filter( 'upgrader_clear_destination', function( $clear, $dest, $up, $hook ) use ( $overwrite ) {
        return $overwrite ? true : $clear;
    }, 10, 4 );

    $upgrader->install( $download_url );
}
