<?php
/**
 * Uninstall CalendiFlow
 *
 * Fired when the plugin is uninstalled via the WordPress admin.
 * Acts as a standard fallback alongside the Freemius after_uninstall hook.
 *
 * @package CalendarBooking
 */

// Exit if not called by WordPress uninstall routine.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove plugin tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}calebo_reservas" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}calebo_espera" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}calebo_campos" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}calebo_calendarios" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}calebo_actividades" );

// Remove plugin options.
delete_option( 'calebo_version' );
delete_option( 'calebo_smtp_opciones' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'calebo_email_diario' );
