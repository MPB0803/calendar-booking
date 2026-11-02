<?php
if (!defined('ABSPATH')) exit;

class CALEBO_Privacy {

    public static function init() {
        add_action('admin_init', [__CLASS__, 'add_privacy_policy_content']);
        add_filter('wp_privacy_personal_data_exporters', [__CLASS__, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [__CLASS__, 'register_eraser']);
    }

    public static function add_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p>' . esc_html__('Calendar Booking stores reservation data in your WordPress database to manage bookings and send notifications.', 'calendar-booking') . '</p>';
        $content .= '<ul>';
        $content .= '<li>' . esc_html__('Customer name', 'calendar-booking') . '</li>';
        $content .= '<li>' . esc_html__('Customer email address', 'calendar-booking') . '</li>';
        $content .= '<li>' . esc_html__('Customer phone number', 'calendar-booking') . '</li>';
        $content .= '<li>' . esc_html__('Booking details (date, time slot, attendees and optional notes)', 'calendar-booking') . '</li>';
        $content .= '</ul>';
        $content .= '<p>' . esc_html__('If SMTP or third-party services are configured, reservation notifications may be sent through those providers. The plugin also includes the Freemius SDK for plugin licensing and update infrastructure according to your plugin settings and consent flow.', 'calendar-booking') . '</p>';

        wp_add_privacy_policy_content(
            esc_html__('Calendar Booking', 'calendar-booking'),
            wp_kses_post($content)
        );
    }

    public static function register_exporter($exporters) {
        $exporters['calendar-booking-reservas'] = [
            'exporter_friendly_name' => __('Calendar Booking reservations', 'calendar-booking'),
            'callback'               => [__CLASS__, 'export_personal_data'],
        ];
        return $exporters;
    }

    public static function register_eraser($erasers) {
        $erasers['calendar-booking-reservas'] = [
            'eraser_friendly_name' => __('Calendar Booking reservations', 'calendar-booking'),
            'callback'             => [__CLASS__, 'erase_personal_data'],
        ];
        return $erasers;
    }

    public static function export_personal_data($email_address, $page = 1) {
        global $wpdb;

        $page = max(1, (int) $page);
        $limit = 100;
        $offset = ($page - 1) * $limit;
        $email = sanitize_email($email_address);

        if (empty($email)) {
            return ['data' => [], 'done' => true];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, calendario_id, fecha, horario, nombre, email, telefono, num_menores, edades, estado, creada_en
             FROM {$wpdb->prefix}calebo_reservas
             WHERE email = %s
             ORDER BY creada_en ASC
             LIMIT %d OFFSET %d",
            $email,
            $limit,
            $offset
        ));

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'group_id'    => 'calendar-booking-reservations',
                'group_label' => __('Calendar Booking Reservations', 'calendar-booking'),
                'item_id'     => 'calebo-reservation-' . (int) $row->id,
                'data'        => [
                    ['name' => __('Name', 'calendar-booking'), 'value' => $row->nombre],
                    ['name' => __('Email', 'calendar-booking'), 'value' => $row->email],
                    ['name' => __('Phone', 'calendar-booking'), 'value' => $row->telefono],
                    ['name' => __('Date', 'calendar-booking'), 'value' => $row->fecha],
                    ['name' => __('Time slot', 'calendar-booking'), 'value' => $row->horario],
                    ['name' => __('Attendees', 'calendar-booking'), 'value' => (string) $row->num_menores],
                    ['name' => __('Ages', 'calendar-booking'), 'value' => $row->edades],
                    ['name' => __('Status', 'calendar-booking'), 'value' => $row->estado],
                    ['name' => __('Created at', 'calendar-booking'), 'value' => $row->creada_en],
                ],
            ];
        }

        return [
            'data' => $data,
            'done' => count($rows) < $limit,
        ];
    }

    public static function erase_personal_data($email_address, $page = 1) {
        global $wpdb;

        $page = max(1, (int) $page);
        $limit = 100;
        $offset = ($page - 1) * $limit;
        $email = sanitize_email($email_address);

        if (empty($email)) {
            return [
                'items_removed'  => false,
                'items_retained' => false,
                'messages'       => [],
                'done'           => true,
            ];
        }

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id
             FROM {$wpdb->prefix}calebo_reservas
             WHERE email = %s
             ORDER BY id ASC
             LIMIT %d OFFSET %d",
            $email,
            $limit,
            $offset
        ));

        $items_removed = false;
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $updated = $wpdb->update(
                    "{$wpdb->prefix}calebo_reservas",
                    [
                        'nombre'   => '',
                        'email'    => '',
                        'telefono' => '',
                        'edades'   => '',
                        'estado'   => 'anonimizada',
                    ],
                    ['id' => (int) $id],
                    ['%s', '%s', '%s', '%s', '%s'],
                    ['%d']
                );
                if ($updated !== false) {
                    $items_removed = true;
                }
            }
        }

        return [
            'items_removed'  => $items_removed,
            'items_retained' => false,
            'messages'       => [],
            'done'           => count($ids) < $limit,
        ];
    }
}
