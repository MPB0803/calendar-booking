<?php
/**
 * Plugin Name: CalendiFlow – Booking & Event Calendar
 * Plugin URI:  https://github.com/MPB0803/calendar-booking
 * Description: Booking system with capacity limit per number of attendees. Ideal for playrooms, workshops, medical appointments and any service that needs to control attendance.
 * Version:     2.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * Author:      Misael Francisco Palomino Beltrán
 * Author URI:  https://github.com/MPB0803
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: calendar-booking
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── CONSTANTES ──
define( 'CALEBO_VERSION',     '2.0.1' );
define( 'CALEBO_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CALEBO_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CALEBO_PLUGIN_FILE', __FILE__ );

/**
 * Comprueba si es versión Pro.
 * WordPress.org version: always returns false.
 *
 * @return bool
 */
function calebo_is_pro() {
    return false;
}

/**
 * Devuelve la IP real del visitante, respetando cabeceras de proxy/CDN
 * de confianza solo si el servidor está detrás de uno conocido.
 *
 * @return string
 */
function calebo_get_real_ip() {
    $ip = '0.0.0.0';
    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    }
    // Si REMOTE_ADDR es local/proxy, consultar cabeceras comunes de CDN
    $trusted_local = [ '127.0.0.1', '::1' ];
    if ( in_array( $ip, $trusted_local, true ) || filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
        foreach ( [ 'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP' ] as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
                $candidate = trim( $parts[0] );
                if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    $ip = $candidate;
                    break;
                }
            }
        }
    }
    return $ip;
}

// ── CARGAR CLASES ──
require_once CALEBO_PLUGIN_DIR . 'includes/class-database.php';
require_once CALEBO_PLUGIN_DIR . 'includes/class-booking.php';
require_once CALEBO_PLUGIN_DIR . 'includes/class-notifications.php';
require_once CALEBO_PLUGIN_DIR . 'includes/class-privacy.php';
require_once CALEBO_PLUGIN_DIR . 'includes/class-reports.php';
require_once CALEBO_PLUGIN_DIR . 'includes/class-smtp.php';
require_once CALEBO_PLUGIN_DIR . 'admin/class-admin.php';
require_once CALEBO_PLUGIN_DIR . 'admin/class-settings.php';
require_once CALEBO_PLUGIN_DIR . 'admin/class-actividades.php';

// ── ACTIVACIÓN / DESACTIVACIÓN ──
register_activation_hook( __FILE__, [ 'CALEBO_Database', 'instalar' ] );
register_deactivation_hook( __FILE__, [ 'CALEBO_Database', 'desactivar' ] );

// ── SMTP ──
add_action( 'phpmailer_init', [ 'CALEBO_SMTP', 'configurar_smtp' ] );

// ── INICIAR PLUGIN ──
add_action( 'plugins_loaded', 'calebo_init' );

/**
 * Inicializa el plugin.
 */
function calebo_init() {
    // Ejecutar migraciones pendientes si la versión almacenada difiere de la actual
    CALEBO_Database::maybe_migrate();
    CALEBO_Admin::init();
    CALEBO_Booking::init();
    CALEBO_Privacy::init();
    CALEBO_Reports::init();
    CALEBO_SMTP::init();
    CALEBO_Actividades::init();
}