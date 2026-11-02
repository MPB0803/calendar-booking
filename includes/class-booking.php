<?php
if (!defined('ABSPATH')) exit;

class CALEBO_Booking {

    public static function init() {
        add_shortcode('calebo_calendario',   [__CLASS__, 'shortcode_calendario']);
        add_shortcode('calebo_confirmacion', [__CLASS__, 'shortcode_confirmacion']);
        add_shortcode('calebo_cancelar',     [__CLASS__, 'shortcode_cancelar']);

        add_action('wp_ajax_calebo_disponibilidad',        [__CLASS__, 'ajax_disponibilidad']);
        add_action('wp_ajax_nopriv_calebo_disponibilidad', [__CLASS__, 'ajax_disponibilidad']);
        add_action('wp_ajax_calebo_reservar',              [__CLASS__, 'ajax_reservar']);
        add_action('wp_ajax_nopriv_calebo_reservar',       [__CLASS__, 'ajax_reservar']);
        add_action('wp_ajax_calebo_get_actividades',        [__CLASS__, 'ajax_get_actividades']);
        add_action('wp_ajax_nopriv_calebo_get_actividades', [__CLASS__, 'ajax_get_actividades']);
        add_action('wp_ajax_calebo_lista_espera',        [__CLASS__, 'ajax_lista_espera']);
        add_action('wp_ajax_nopriv_calebo_lista_espera', [__CLASS__, 'ajax_lista_espera']);

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets() {
        global $post;

        $shortcodes = ['calebo_calendario', 'calebo_confirmacion', 'calebo_cancelar'];
        $tiene_shortcode = false;

        // Comprobar en el contenido del post actual
        if ( is_a( $post, 'WP_Post' ) ) {
            foreach ( $shortcodes as $sc ) {
                if ( has_shortcode( $post->post_content, $sc ) ) {
                    $tiene_shortcode = true;
                    break;
                }
            }
        }

        // Si no se encontró, comprobar en el contenido renderizado (para páginas estáticas)
        if ( ! $tiene_shortcode && is_singular() ) {
            $queried_id = get_queried_object_id();
            if ( $queried_id ) {
                $queried_post = get_post( $queried_id );
                if ( $queried_post && is_a( $queried_post, 'WP_Post' ) ) {
                    foreach ( $shortcodes as $sc ) {
                        if ( has_shortcode( $queried_post->post_content, $sc ) ) {
                            $tiene_shortcode = true;
                            break;
                        }
                    }
                }
            }
        }

        if ( ! $tiene_shortcode ) {
            return;
        }

        $suffix  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

        // Si los archivos minificados no existen aún (entorno de desarrollo), usar los fuente.
        if ( $suffix && ! file_exists( CALEBO_PLUGIN_DIR . 'assets/js/calendar.min.js' ) ) {
            $suffix = '';
        }
        $css_file = $suffix && file_exists( CALEBO_PLUGIN_DIR . 'assets/css/calendar.min.css' )
            ? 'calendar.min.css'
            : 'calendar.css';
        $ver_css = filemtime( CALEBO_PLUGIN_DIR . 'assets/css/' . $css_file ) ?: time();
        $ver_js  = filemtime( CALEBO_PLUGIN_DIR . 'assets/js/calendar' . $suffix . '.js' ) ?: time();
        wp_deregister_style('calebo-calendar');
        wp_deregister_script('calebo-calendar');

        // Fuentes incluidas localmente en el plugin
        $fuentes_locales_disponibles = [
            'Poppins' => [
                400 => 'Poppins-400.woff',
                600 => 'Poppins-600.woff',
                700 => 'Poppins-700.woff',
            ],
            'Lora' => [
                400 => 'Lora-400.woff',
                700 => 'Lora-700.woff',
            ],
        ];

        $calendarios_activos = CALEBO_Database::get_calendarios();
        $css_font_face = '';

        foreach ( $calendarios_activos as $cal ) {
            $diseno_cal = json_decode( $cal->diseno ?? '{}', true ) ?: [];
            $fuente_cal = $diseno_cal['fuente'] ?? 'Montserrat';

            if ( isset( $fuentes_locales_disponibles[ $fuente_cal ] ) ) {
                // FIX 5: Usar helper centralizado en lugar de duplicar la lógica @font-face
                if ( strpos( $css_font_face, "font-family:'{$fuente_cal}'" ) === false ) {
                    $css_font_face .= CALEBO_Database::get_font_face_css(
                        $fuente_cal,
                        $fuentes_locales_disponibles[ $fuente_cal ]
                    );
                }
            }
        }

        wp_enqueue_style('calebo-calendar', CALEBO_PLUGIN_URL . 'assets/css/' . $css_file, [], $ver_css);

        // CSS de las páginas de confirmación/cancelación — encolado (sin <link> inline en el template).
        wp_enqueue_style(
            'calebo-confirmacion',
            CALEBO_PLUGIN_URL . 'assets/css/confirmation.css',
            [],
            filemtime( CALEBO_PLUGIN_DIR . 'assets/css/confirmation.css' ) ?: CALEBO_VERSION
        );

        // ── CSS dinámico por calendario — con caché en transient ──────────────
        // Se genera una sola vez y se almacena hasta que el administrador modifica
        // el diseño de cualquier calendario (la caché se invalida en class-admin.php).
        $css_cache_key = 'calebo_dynamic_css_' . md5( serialize(
            array_map( function( $c ) { return [ $c->id, $c->diseno, $c->color_primary, $c->color_secondary, $c->dias_disponibles ]; }, $calendarios_activos )
        ) );

        $css_dinamico = get_transient( $css_cache_key );

        if ( $css_dinamico === false ) {
            $css_dinamico = '';
        foreach ( $calendarios_activos as $cal ) {
            $diseno_cal  = json_decode( $cal->diseno ?? '{}', true ) ?: [];
            $d = array_merge([
                'fuente'            => 'Montserrat',
                'celda_radio'       => '6',
                'celda_padding'     => '12',
                'celda_ancho'       => '100',
                'completo_bg'       => '#1E3FA3',
                'completo_color'    => '#ffffff',
                'completo_texto'    => 'COMPLETO',
                'completo_rotacion' => '-10',
                'btn_radio'         => '6',
                'btn_texto'         => 'Confirmar reserva',
                'btn_uppercase'     => '1',
                'fondo_tipo'             => 'color',
                'fondo_color'            => '#ffffff',
                'fondo_imagen'           => '',
                'color_semana_texto'     => '#ffffff',
                'color_numeros_mes'      => '#2D2D2D',
                'multi_color'            => '#F7941D',
                'multi_forma'            => 'circulo',
                'multi_size'             => 16,
            ], $diseno_cal);

            $dias_cal        = array_map( 'intval', explode( ',', $cal->dias_disponibles ?? '1,2,3,4,5' ) );
            $num_cols_cal    = count( $dias_cal );
            $id              = intval( $cal->id );
            $primary         = sanitize_hex_color( $cal->color_primary   ?? '' ) ?: '#F7941D';
            $secondary       = sanitize_hex_color( $cal->color_secondary ?? '' ) ?: '#1E3FA3';
            $fuente          = sanitize_text_field( $d['fuente'] );
            $celda_radio     = intval( $d['celda_radio'] );
            $celda_padding   = intval( $d['celda_padding'] );
            $celda_ancho     = intval( $d['celda_ancho'] );
            $completo_bg     = sanitize_hex_color( $d['completo_bg']    ) ?: '#1E3FA3';
            $completo_color  = sanitize_hex_color( $d['completo_color'] ) ?: '#ffffff';
            $completo_texto  = esc_js( sanitize_text_field( $d['completo_texto'] ) );
            $completo_rot    = intval( $d['completo_rotacion'] );
            $btn_radio         = intval( $d['btn_radio'] );
            $btn_uppercase     = $d['btn_uppercase'] === '1' ? 'uppercase' : 'none';
            $semana_texto      = sanitize_hex_color( $d['color_semana_texto'] ) ?: '#ffffff';
            $numeros_color     = sanitize_hex_color( $d['color_numeros_mes']  ) ?: '#2D2D2D';
            $multi_color       = sanitize_hex_color( $d['multi_color'] ) ?: '#F7941D';
            $multi_forma_map   = [
                'circulo'    => 'border-radius:50%!important;clip-path:none!important',
                'cuadrado'   => 'border-radius:0!important;clip-path:none!important',
                'redondeado' => 'border-radius:4px!important;clip-path:none!important',
                'diamante'   => 'border-radius:0!important;clip-path:polygon(50% 0%,100% 50%,50% 100%,0% 50%)!important',
                'estrella'   => 'border-radius:0!important;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%)!important',
            ];
            $multi_forma_css   = $multi_forma_map[ $d['multi_forma'] ?? 'circulo' ] ?? $multi_forma_map['circulo'];
            $multi_size        = min(36, max(10, intval( $d['multi_size'] ?? 16 ))) . 'px';
            $min_height        = ( $celda_padding * 4 ) + 32;

            $fondo_css = '';
            if ( $d['fondo_tipo'] === 'imagen' && ! empty( $d['fondo_imagen'] ) ) {
                $fondo_url = esc_url( $d['fondo_imagen'] );
                $fondo_css = "background-image:url('{$fondo_url}');background-size:cover;background-position:center;";
            } else {
                $fondo_bg  = sanitize_hex_color( $d['fondo_color'] ) ?: '#ffffff';
                $fondo_css = "background-color:{$fondo_bg};";
            }

            $css_dinamico .= "#calebo-wrap-{$id}{"
                . "--calebo-primary:{$primary};"
                . "--calebo-secondary:{$secondary};"
                . "--calebo-num-cols:{$num_cols_cal};"
                . "font-family:'{$fuente}',Arial,sans-serif!important;"
                . $fondo_css
                . "}"
                . "#calebo-wrap-{$id} .calebo-day{"
                . "border-radius:{$celda_radio}px!important;"
                . "min-height:{$min_height}px!important;"
                . "padding-top:{$celda_padding}px!important;"
                . "padding-bottom:{$celda_padding}px!important;"
                . "padding-left:4px!important;padding-right:4px!important;"
                . "width:{$celda_ancho}%!important;margin:auto!important;"
                . "}"
                . "#calebo-wrap-{$id} .calebo-day--lleno::after{"
                . "content:'{$completo_texto}'!important;"
                . "background:{$completo_bg}!important;"
                . "color:{$completo_color}!important;"
                . "transform:translate(-50%,-50%) rotate({$completo_rot}deg)!important;"
                . "}"
                . "#calebo-wrap-{$id} .calebo-btn-reservar{"
                . "border-radius:{$btn_radio}px!important;"
                . "text-transform:{$btn_uppercase}!important;"
                . "}"
                . "#calebo-wrap-{$id} .calebo-cal-grid{"
                . "grid-template-columns:repeat({$num_cols_cal},1fr)!important;"
                . "}"
                . "#calebo-wrap-{$id} .calebo-day-label{"
                . "background:{$secondary}!important;"
                . "color:{$semana_texto}!important;"
                . "}"
                . "#calebo-wrap-{$id} .calebo-day-num{"
                . "color:{$numeros_color}!important;"
                . "}"
                . "#calebo-wrap-{$id} .calebo-day-multi-dot{"
                . "background:{$multi_color}!important;"
                . "width:{$multi_size}!important;height:{$multi_size}!important;"
                . "{$multi_forma_css};"
                . "}";
        }

            // Guardar CSS generado en caché (válida 12 horas).
            // Se invalida automáticamente cuando cambia la configuración de diseño
            // (ver CALEBO_Booking::invalidar_css_cache() llamado desde class-admin.php).
            set_transient( $css_cache_key, $css_dinamico, 12 * HOUR_IN_SECONDS );

        } // end if css cache miss

        if ( $css_font_face ) {
            wp_add_inline_style( 'calebo-calendar', $css_font_face );
        }
        if ( $css_dinamico ) {
            wp_add_inline_style( 'calebo-calendar', $css_dinamico );
        }
        wp_enqueue_script('calebo-calendar', CALEBO_PLUGIN_URL . 'assets/js/calendar' . $suffix . '.js', ['jquery'], $ver_js, true);
        wp_localize_script('calebo-calendar', 'caleboAjax', [
            'url'      => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('calebo_nonce'),
            'months'   => [
                '',
                __('Enero', 'calendar-booking'),
                __('Febrero', 'calendar-booking'),
                __('Marzo', 'calendar-booking'),
                __('Abril', 'calendar-booking'),
                __('Mayo', 'calendar-booking'),
                __('Junio', 'calendar-booking'),
                __('Julio', 'calendar-booking'),
                __('Agosto', 'calendar-booking'),
                __('Septiembre', 'calendar-booking'),
                __('Octubre', 'calendar-booking'),
                __('Noviembre', 'calendar-booking'),
                __('Diciembre', 'calendar-booking'),
            ],
            'strings'  => [
                'today'              => __('HOY', 'calendar-booking'),
                /* translators: %d: plazas disponibles. */
                'slots_available'    => __('%d libres', 'calendar-booking'),
                /* translators: %d: plazas disponibles. */
                'remaining_slots'    => __('¡%d plaza(s)!', 'calendar-booking'),
                'required_fields'    => __('Por favor, rellena todos los campos obligatorios.', 'calendar-booking'),
                'sending'            => __('Enviando...', 'calendar-booking'),
                'confirm_button'     => __('Confirmar reserva', 'calendar-booking'),
                'available_places'   => __('Plazas disponibles:', 'calendar-booking'),
                'of_total'           => __('de', 'calendar-booking'),
                'child_label'        => __('menor(es) apuntado(s)', 'calendar-booking'),
                'email_notice'       => __('Te hemos enviado un correo de confirmación con el enlace para cancelar si lo necesitas.', 'calendar-booking'),
                'activities_title'   => __('Actividades disponibles', 'calendar-booking'),
                'book_activity'      => __('Reservar', 'calendar-booking'),
                'activity_full'      => __('Completo', 'calendar-booking'),
                /* translators: %d: plazas libres de la actividad. */
                'activity_slots'     => __('%d plaza(s) libres', 'calendar-booking'),
                'activity_sala'      => __('Sala:', 'calendar-booking'),
                'back_to_activities' => __('← Volver a actividades', 'calendar-booking'),
                'loading_activities' => __('Cargando actividades...', 'calendar-booking'),
                'activity_label'     => __('Actividad:', 'calendar-booking'),
                'multi_event_tooltip' => __('Múltiples eventos disponibles', 'calendar-booking'),
                'waitlist_join'      => __('Lista de espera', 'calendar-booking'),
                'waitlist_info'      => __('Rellena tus datos. Si se libera una plaza, la reserva se confirmará automáticamente.', 'calendar-booking'),
                'waitlist_name'      => __('Tu nombre:', 'calendar-booking'),
                'waitlist_email'     => __('Tu email:', 'calendar-booking'),
                'waitlist_done'      => __('En lista de espera', 'calendar-booking'),
            ],
        ]);
    }

    public static function shortcode_calendario($atts) {
        $atts       = shortcode_atts(['id' => 1], $atts);
        $calendario = CALEBO_Database::get_calendario(intval($atts['id']));
        if (!$calendario) return '<p>' . esc_html__('Calendario no encontrado.', 'calendar-booking') . '</p>';

        $diseno = json_decode($calendario->diseno ?? '{}', true) ?: [];
        $diseno = array_merge([
            'fuente'            => 'Montserrat',
            'celda_radio'       => '6',
            'celda_padding'     => '12',
            'celda_ancho'       => '100',
            'completo_bg'       => '#1E3FA3',
            'completo_color'    => '#ffffff',
            'completo_texto'    => 'COMPLETO',
            'completo_rotacion' => '-10',
            'btn_radio'         => '6',
            'btn_texto'         => 'Confirmar reserva',
            'btn_uppercase'     => '1',
            'fondo_tipo'        => 'color',
            'fondo_color'       => '#ffffff',
            'fondo_imagen'      => '',
        ], $diseno);

        ob_start();
        include CALEBO_PLUGIN_DIR . 'templates/calendar.php';
        return ob_get_clean();
    }

    public static function shortcode_confirmacion() {
        // Leer token de la URL y obtener datos de la reserva
        $token   = isset($_GET['calebo_token']) ? sanitize_text_field(wp_unslash($_GET['calebo_token'])) : '';
        $reserva = null;
        $actividad  = null;
        $calendario = null;

        if ($token) {
            $reserva = CALEBO_Database::get_reserva_por_token($token);
            if ($reserva) {
                $actividad  = CALEBO_Database::get_actividad((int) $reserva->actividad_id);
                $calendario = CALEBO_Database::get_calendario((int) $reserva->calendario_id);
            }
        }

        ob_start();
        include CALEBO_PLUGIN_DIR . 'templates/confirmacion.php';
        return ob_get_clean();
    }

    /**
     * Devuelve la URL de la página que contiene el shortcode [calebo_confirmacion].
     * Busca primero en la opción guardada; si no, busca entre todas las páginas publicadas.
     *
     * @return string|null  URL completa o null si no se encuentra.
     */
    public static function get_url_confirmacion() {
        // Opción configurable manualmente
        $page_id = (int) get_option('calebo_pagina_confirmacion_id', 0);
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) return $url;
        }

        // Cache en transient para evitar LIKE sobre posts en cada petición
        $cached = get_transient('calebo_url_confirmacion');
        if ($cached) {
            return $cached;
        }

        // Auto-detección: buscar página publicada con el shortcode
        global $wpdb;
        $page = $wpdb->get_row(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('page','post')
               AND post_content LIKE '%[calebo_confirmacion%'
             LIMIT 1"
        );

        if ($page) {
            // Guardar en opción para no volver a buscar cada vez
            update_option('calebo_pagina_confirmacion_id', (int) $page->ID);
            $url = get_permalink($page->ID);
            set_transient('calebo_url_confirmacion', $url, DAY_IN_SECONDS);
            return $url;
        }

        return null;
    }

    public static function shortcode_cancelar() {
        ob_start();
        include CALEBO_PLUGIN_DIR . 'templates/cancelar.php';
        return ob_get_clean();
    }

    /**
     * Calcula la ventana de reserva activa actualmente para una actividad.
     * Devuelve un array con la información del ciclo (meses/años) o null si no hay límite.
     */
    public static function obtener_ventana_reserva($act, $calendario, $mes_actual = 0, $anio_actual = 0, $dia_actual = 0) {
        if (!$mes_actual)  $mes_actual  = intval(gmdate('n'));
        if (!$anio_actual) $anio_actual = intval(gmdate('Y'));
        if (!$dia_actual)  $dia_actual  = intval(gmdate('j'));

        $meses_max      = intval(CALEBO_Database::resolver_config_actividad($act, $calendario, 'meses_max') ?? 0);
        $dia_activacion = intval(CALEBO_Database::resolver_config_actividad($act, $calendario, 'dia_activacion') ?? 0);
        $mes_inicio     = intval(CALEBO_Database::resolver_config_actividad($act, $calendario, 'mes_inicio_ciclo') ?? $calendario->mes_inicio_ciclo ?? 1);

        if ($meses_max <= 0) return null;

        $mes_abs_actual = $anio_actual * 12 + ($mes_actual - 1);

        $anio_base = $anio_actual;
        if ($mes_inicio > $mes_actual) {
            $anio_base--;
        }
        $mes_abs_base = $anio_base * 12 + ($mes_inicio - 1);
        $meses_transcurridos = $mes_abs_actual - $mes_abs_base;

        // Determinar mes absoluto de inicio de la actividad
        $mes_abs_inicio_act = null;
        if (!empty($act->fecha_inicio)) {
            $ts_ini = strtotime($act->fecha_inicio);
            if ($ts_ini && $ts_ini > 0) {
                $mes_abs_inicio_act = intval(wp_date('Y', $ts_ini)) * 12 + (intval(wp_date('n', $ts_ini)) - 1);
            }
        }

        if ($mes_abs_inicio_act !== null && $mes_abs_actual < $mes_abs_inicio_act) {
            // Caso especial: la actividad aún no ha comenzado.
            // Forzar el ciclo al que corresponde el mes de inicio real de la actividad.
            $anio_base_act = intval(wp_date('Y', $ts_ini));
            if ($mes_inicio > intval(wp_date('n', $ts_ini))) {
                $anio_base_act--;
            }
            $mes_abs_base_act = $anio_base_act * 12 + ($mes_inicio - 1);
            
            $meses_trans_act = max(0, $mes_abs_inicio_act - $mes_abs_base_act);
            $ciclo = (int) floor($meses_trans_act / $meses_max);
            
            $mes_abs_ini_v = $mes_abs_base_act + ($ciclo * $meses_max);
            $mes_abs_fin_v = $mes_abs_ini_v + $meses_max - 1;
        } else {
            // Lógica normal: basarnos en meses transcurridos hasta hoy
            $ciclo = (int) floor($meses_transcurridos / $meses_max);
            $mes_abs_ini_v = $mes_abs_base + ($ciclo * $meses_max);
            $mes_abs_fin_v = $mes_abs_ini_v + $meses_max - 1;

            // ¿Estamos en el último mes de la ventana actual?
            $es_ultimo_mes = (($meses_transcurridos % $meses_max) == ($meses_max - 1));

            // Si estamos en el ÚLTIMO mes de la ventana Y ha llegado el día de activación
            // la ventana se desliza automáticamente al siguiente ciclo.
            if ($es_ultimo_mes && $dia_activacion > 0 && $dia_actual >= $dia_activacion) {
                $mes_abs_ini_v += $meses_max;
                $mes_abs_fin_v += $meses_max;
            }
        }

        return [
            'mes_ini'   => ($mes_abs_ini_v % 12) + 1,
            'anio_ini'  => (int) floor($mes_abs_ini_v / 12),
            'mes_fin'   => ($mes_abs_fin_v % 12) + 1,
            'anio_fin'  => (int) floor($mes_abs_fin_v / 12),
            'prox_mes'  => (($mes_abs_fin_v + 1) % 12) + 1,
            'prox_anio' => (int) floor(($mes_abs_fin_v + 1) / 12),
            'efectivos' => [
                'meses_max'      => $meses_max,
                'dia_activacion' => $dia_activacion,
                'mes_inicio'     => $mes_inicio,
            ]
        ];
    }

    /**
     * Calcula la fecha máxima reservable para una actividad según su configuración de ciclo.
     * Devuelve una fecha en formato 'Y-m-d' (último día del mes límite) o null si no hay límite.
     *
     * @param object $act        Fila de actividad de la BD.
     * @param object $calendario Fila del calendario de la BD.
     * @param int    $mes_actual  Mes actual (1-12). 0 = usar servidor.
     * @param int    $anio_actual Año actual. 0 = usar servidor.
     * @return string|null
     */
    public static function calcular_fecha_max($act, $calendario, $mes_actual = 0, $anio_actual = 0) {
        $ventana = self::obtener_ventana_reserva($act, $calendario, $mes_actual, $anio_actual);
        if (!$ventana) return null;

        return gmdate('Y-m-t', mktime(0, 0, 0, $ventana['mes_fin'], 1, $ventana['anio_fin']));
    }


    /**
     * Disponibilidad del mes: basada únicamente en actividades con rango de fechas.
     * Un día está disponible si hay al menos una actividad activa cuyo rango incluya ese día.
     */
    public static function ajax_disponibilidad() {
        check_ajax_referer('calebo_nonce', 'nonce');

        $post          = wp_unslash($_POST);
        $calendario_id = intval($post['calendario_id'] ?? 0);
        $mes           = intval($post['mes']  ?? gmdate('n'));
        $anio          = intval($post['anio'] ?? gmdate('Y'));

        // Validar mes y año — permitimos navegar hasta 5 años en el futuro y 1 año atrás
        if ($mes < 1 || $mes > 12 || $anio < intval(gmdate('Y')) - 1 || $anio > intval(gmdate('Y')) + 5) {
            wp_send_json_error(['mensaje' => __('Fecha no válida.', 'calendar-booking')]);
        }

        $calendario    = CALEBO_Database::get_calendario($calendario_id);

        if (!$calendario) wp_send_json_error();

        // Caché de disponibilidad
        $cache_key = 'calebo_disp_' . $calendario_id . '_' . $mes . '_' . $anio;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            wp_send_json_success($cached);
        }

        $dias         = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $hoy          = gmdate('Y-m-d');
        $fecha_inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fecha_fin    = sprintf('%04d-%02d-%02d', $anio, $mes, $dias);

        // Actividades cuyo rango se solapa con este mes
        $actividades_mes = CALEBO_Database::get_actividades_rango($calendario_id, $fecha_inicio, $fecha_fin);

        // Reservas agrupadas por fecha y actividad_id
        $reservas_acts = CALEBO_Database::get_reservas_actividades_rango($calendario_id, $fecha_inicio, $fecha_fin);

        // Sin límite global de navegación: cada actividad tiene su propia fecha máxima reservable.
        // Pre-calcular fecha máxima por actividad usando el método compartido.
        $max_por_act = [];
        foreach ($actividades_mes as $act) {
            $max_por_act[(int)$act->id] = self::calcular_fecha_max($act, $calendario);
        }

        // Festivos globales del calendario
        $festivos_calendario = array_column(json_decode($calendario->festivos ?? '[]', true) ?: [], 'fecha');

        // Pre-calcular por actividad sus propias fechas bloqueadas, y después construir acts_por_fecha
        // Primero recopilamos la info de bloqueo de cada actividad individualmente
        $bloqueos_por_act = [];  // act_id => ['festivos' => [...], 'vacaciones_bloqueadas' => [...]]
        foreach ($actividades_mes as $act) {
            $festivos_act = array_column(json_decode($act->festivos ?? '[]', true) ?: [], 'fecha');

            $vac_bloqueadas = [];
            $vacaciones_act = json_decode($act->vacaciones ?? '[]', true) ?: [];
            foreach ($vacaciones_act as $v) {
                if (empty($v['inicio']) || empty($v['fin'])) continue;
                if (($v['tipo'] ?? 'bloqueado') !== 'bloqueado') continue;
                $cur = $v['inicio'];
                while ($cur <= $v['fin']) {
                    $vac_bloqueadas[$cur] = true;
                    $cur = gmdate('Y-m-d', strtotime($cur . ' +1 day'));
                }
            }

            $bloqueos_por_act[(int) $act->id] = [
                'festivos'    => array_flip($festivos_act),
                'vacaciones'  => $vac_bloqueadas,
            ];
        }

        // Construir acts_por_fecha excluyendo solo los días que ESTA actividad tiene bloqueados
        // o que superan su propia fecha máxima reservable
        $acts_por_fecha = [];
        foreach ($actividades_mes as $act) {
            $bloqueos  = $bloqueos_por_act[(int) $act->id];
            $max_act   = $max_por_act[(int) $act->id]; // null = sin límite
            $d_start   = max($fecha_inicio, $act->fecha_inicio);
            $d_end     = min($fecha_fin, $act->fecha_fin);
            $current   = $d_start;
            while ($current <= $d_end) {
                $bloqueado     = isset($bloqueos['festivos'][$current]) || isset($bloqueos['vacaciones'][$current]);
                $fuera_de_rango = ($max_act !== null && $current > $max_act);
                if (!$bloqueado && !$fuera_de_rango) {
                    $acts_por_fecha[$current][] = $act;
                }
                $current = gmdate('Y-m-d', strtotime($current . ' +1 day'));
            }
        }

        $resultado = [];

        for ($dia = 1; $dia <= $dias; $dia++) {
            $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);

            // Día pasado — no disponible
            if ($fecha < $hoy) {
                $resultado[$fecha] = ['estado' => 'no_disponible'];
                continue;
            }

            // Festivo global del calendario (cierra todo el día)
            if (in_array($fecha, $festivos_calendario)) {
                $resultado[$fecha] = ['estado' => 'cerrado'];
                continue;
            }

            // Sin actividades disponibles ese día = cerrado (pero navegable)
            if (empty($acts_por_fecha[$fecha])) {
                $resultado[$fecha] = ['estado' => 'cerrado'];
                continue;
            }

            // Calcular plazas entre las actividades que sí están disponibles ese día
            $actividades_dia = $acts_por_fecha[$fecha];
            $num_acts        = count($actividades_dia);
            $total_libres    = 0;
            $todas_llenas    = true;

            foreach ($actividades_dia as $act) {
                $ocp = $reservas_acts[$fecha][(int) $act->id] ?? 0;
                $lib = max(0, $act->limite - $ocp);
                $total_libres += $lib;
                if ($lib > 0) $todas_llenas = false;
            }

            if ($todas_llenas) {
                $resultado[$fecha] = ['estado' => 'lleno', 'num_eventos' => $num_acts];
            } elseif ($total_libres <= 5) {
                $resultado[$fecha] = ['estado' => 'casi_lleno', 'libres' => $total_libres, 'num_eventos' => $num_acts];
            } else {
                $resultado[$fecha] = ['estado' => 'disponible', 'libres' => $total_libres, 'num_eventos' => $num_acts];
            }
        }

        set_transient($cache_key, $resultado, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success($resultado);
    }

    /**
     * Devuelve las actividades disponibles para una fecha concreta.
     */
    public static function ajax_get_actividades() {
        check_ajax_referer('calebo_nonce', 'nonce');

        $post          = wp_unslash($_POST);
        $calendario_id = intval($post['calendario_id'] ?? 0);
        $fecha         = sanitize_text_field($post['fecha'] ?? '');

        if (!$calendario_id || !$fecha) wp_send_json_error();

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || strtotime($fecha) === false) {
            wp_send_json_error(['mensaje' => __('Fecha no válida.', 'calendar-booking')]);
        }

        $actividades = CALEBO_Database::get_actividades_fecha($calendario_id, $fecha);
        $reservas    = CALEBO_Database::get_reservas_por_actividad($calendario_id, $fecha);
        $calendario  = CALEBO_Database::get_calendario($calendario_id);

        // Festivos globales del calendario
        $festivos_calendario = array_column(json_decode($calendario->festivos ?? '[]', true) ?: [], 'fecha');
        $dia_bloqueado_calendario = in_array($fecha, $festivos_calendario);

        $resultado = [];
        foreach ($actividades as $act) {
            // Comprobar si esta actividad tiene bloqueado este día por festivo propio
            $festivos_act = array_column(json_decode($act->festivos ?? '[]', true) ?: [], 'fecha');
            if (in_array($fecha, $festivos_act)) continue;

            // Comprobar si este día cae en vacaciones bloqueadas de la actividad
            $vacaciones_act = json_decode($act->vacaciones ?? '[]', true) ?: [];
            $en_vacaciones  = false;
            foreach ($vacaciones_act as $v) {
                if (empty($v['inicio']) || empty($v['fin'])) continue;
                if (($v['tipo'] ?? 'bloqueado') === 'bloqueado' && $fecha >= $v['inicio'] && $fecha <= $v['fin']) {
                    $en_vacaciones = true;
                    break;
                }
            }
            if ($en_vacaciones) continue;

            // Comprobar si la fecha supera la ventana de reserva de esta actividad
            $fecha_max = self::calcular_fecha_max($act, $calendario);
            if ($fecha_max !== null && $fecha > $fecha_max) continue;

            $ocupadas = $reservas[(int) $act->id] ?? 0;
            $libres   = max(0, $act->limite - $ocupadas);
            $campos_extra_decoded = [];
            if (!empty($act->campos_extra)) {
                $campos_extra_decoded = json_decode($act->campos_extra, true) ?: [];
            }
            $resultado[] = [
                'id'           => (int) $act->id,
                'nombre'       => $act->nombre,
                'horario'      => $act->horario,
                'sala'         => $act->sala ?? '',
                'limite'       => (int) $act->limite,
                'ocupadas'     => $ocupadas,
                'libres'       => $libres,
                'descripcion'  => $act->descripcion ?? '',
                'lleno'        => $libres <= 0,
                'campos_extra' => $campos_extra_decoded,
                'horario_vacaciones'      => CALEBO_Database::resolver_config_actividad($act, $calendario, 'horario_vacaciones') ?: ($calendario->horario_vacaciones ?? ''),
                'usar_horario_vacaciones' => (int) CALEBO_Database::resolver_config_actividad($act, $calendario, 'usar_horario_vacaciones'),
                'vacaciones'   => json_decode($act->vacaciones ?? '[]', true) ?: [],
                'lista_espera' => (int) ($act->lista_espera ?? 0),
            ];
        }

        wp_send_json_success($resultado);
    }

    /**
     * Lógica de negocio centralizada de reserva (testeable).
     *
     * @param array $post Datos del formulario (sin sanitizar previo; se sanitiza aquí).
     * @return array ['status'=>'ok', 'token'=>..., ...] o ['error'=>'codigo', 'mensaje'=>...]
     */
    public static function procesar_reserva($post) {
        $calendario_id = intval($post['calendario_id'] ?? 0);
        $actividad_id  = intval($post['actividad_id']  ?? 0);
        $fecha         = sanitize_text_field($post['fecha'] ?? '');

        // Validación de fecha en servidor
        if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return ['error' => 'fecha_invalida', 'mensaje' => __('Fecha no válida.', 'calendar-booking')];
        }
        $fecha_ts = strtotime($fecha);
        if ($fecha_ts === false || $fecha_ts < strtotime('today')) {
            return ['error' => 'fecha_pasada', 'mensaje' => __('No se pueden hacer reservas en fechas pasadas.', 'calendar-booking')];
        }

        // Rate-limiting por IP + User-Agent + usuario
        $ip  = function_exists('calebo_get_real_ip') ? calebo_get_real_ip() : '0.0.0.0';
        $ua  = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $ip  = apply_filters('calebo_rate_limit_ip', $ip);
        $ua  = apply_filters('calebo_rate_limit_ua', $ua);
        $uid = get_current_user_id() ? get_current_user_id() : (isset($_COOKIE[LOGGED_IN_COOKIE]) ? md5(wp_unslash($_COOKIE[LOGGED_IN_COOKIE])) : '0');
        $key = 'calebo_limit_' . md5($ip . '|' . $ua . '|' . $uid);
        $retry = (int) get_transient($key);
        $rate_limit = (int) get_option('calebo_rate_limit', 5);

        if ($retry >= $rate_limit) {
            do_action('calebo_security_log', 'rate_limit_reserva', ['ip' => $ip, 'codigo' => 'ip_ua_user']);
            return ['error' => 'rate_limit', 'mensaje' => __('Has superado el límite de intentos. Por favor, espera una hora.', 'calendar-booking')];        }
        set_transient($key, $retry + 1, HOUR_IN_SECONDS);

        // Segunda dimensión de rate-limit por email+actividad+fecha
        $rl_email = sanitize_email($post['email'] ?? '');
        $rl_act   = intval($post['actividad_id'] ?? 0);
        $rl_fecha = sanitize_text_field($post['fecha'] ?? '');
        if ($rl_email && $rl_act && $rl_fecha) {
            $key2   = 'calebo_limit_ef_' . md5($rl_email . '|' . $rl_act . '|' . $rl_fecha);
            $retry2 = (int) get_transient($key2);
            if ($retry2 >= $rate_limit) {
                do_action('calebo_security_log', 'rate_limit_reserva', ['ip' => $ip, 'email' => $rl_email, 'codigo' => 'email_actividad_fecha']);
                return ['error' => 'rate_limit', 'mensaje' => __('Has superado el límite de intentos para esta reserva. Por favor, espera una hora.', 'calendar-booking')];
            }
            set_transient($key2, $retry2 + 1, HOUR_IN_SECONDS);
        }

        $calendario = CALEBO_Database::get_calendario($calendario_id);
        if (!$calendario || !$fecha || $actividad_id <= 0) {
            return ['error' => 'datos_incompletos', 'mensaje' => __('Por favor, selecciona una actividad y rellena los campos obligatorios.', 'calendar-booking')];
        }

        $actividad = CALEBO_Database::get_actividad($actividad_id);
        if (!$actividad || (int) $actividad->calendario_id !== $calendario_id) {
            return ['error' => 'actividad_invalida', 'mensaje' => __('Actividad no válida.', 'calendar-booking')];
        }

        if ($fecha < $actividad->fecha_inicio || $fecha > $actividad->fecha_fin) {
            return ['error' => 'fuera_de_rango', 'mensaje' => __('La fecha no está dentro del rango de esta actividad.', 'calendar-booking')];
        }

        $fecha_max_rv = self::calcular_fecha_max($actividad, $calendario);
        if ($fecha_max_rv !== null && $fecha > $fecha_max_rv) {
            return ['error' => 'fuera_de_ventana', 'mensaje' => __('Esta actividad no está disponible para la fecha seleccionada.', 'calendar-booking')];
        }

        // Verificar plazas con bloqueo para evitar race conditions
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $ocupadas = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(num_menores), 0) FROM {$wpdb->prefix}calebo_reservas
             WHERE calendario_id = %d AND actividad_id = %d AND fecha = %s AND estado = 'activa'
             FOR UPDATE",
            $calendario_id, $actividad_id, $fecha
        ));
        $plazas_libres = max(0, $actividad->limite - $ocupadas);

        if ($plazas_libres <= 0) {
            $wpdb->query('ROLLBACK');
            return ['error' => 'sin_plazas', 'mensaje' => __('Lo sentimos, esta actividad ya está completa.', 'calendar-booking')];
        }

        // Recoger campos_extra del formulario
        $datos_extra_arr = [];
        if (!empty($post['campos_extra']) && is_array($post['campos_extra'])) {
            foreach ($post['campos_extra'] as $ce_nombre => $ce_valor) {
                $datos_extra_arr[sanitize_key($ce_nombre)] = sanitize_text_field($ce_valor);
            }
        }

        // Validar campos obligatorios de la actividad
        $campos_config = [];
        if (!empty($actividad->campos_extra)) {
            $campos_config = json_decode($actividad->campos_extra, true) ?: [];
        }
        foreach ($campos_config as $campo) {
            $clave = $campo['nombre'] ?? '';
            $requerido = !empty($campo['requerido']);
            if ($requerido && $clave) {
                $valor = $datos_extra_arr[$clave] ?? '';
                if (trim($valor) === '') {
                    $label = $campo['label'] ?? $campo['etiqueta'] ?? $clave;
                    return ['error' => 'campo_obligatorio', 'mensaje' => sprintf(
                        /* translators: %s: nombre del campo. */
                        __('El campo "%s" es obligatorio.', 'calendar-booking'),
                        $label
                    )];
                }
            }
        }

        // Mapear campos conocidos
        $nombre      = sanitize_text_field($datos_extra_arr['nombre'] ?? '');
        $email       = sanitize_email($datos_extra_arr['email'] ?? '');
        $telefono    = sanitize_text_field($datos_extra_arr['telefono'] ?? '');
        $num_menores = max(1, intval($datos_extra_arr['num_menores'] ?? 1));
        $edades      = sanitize_text_field($datos_extra_arr['edades'] ?? '');

        if ($email && !is_email($email)) {
            return ['error' => 'email_invalido', 'mensaje' => __('El email no tiene un formato válido.', 'calendar-booking')];
        }

        if ($num_menores > $plazas_libres) {
            return ['error' => 'sin_plazas', 'mensaje' => sprintf(/* translators: %d: plazas disponibles. */ __('Solo quedan %d plaza(s) disponibles en esta actividad.', 'calendar-booking'), $plazas_libres)];
        }

        // Doble reserva
        if ($email) {
            $ya_reservado = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}calebo_reservas
                 WHERE calendario_id = %d AND actividad_id = %d AND fecha = %s AND email = %s AND estado = 'activa'",
                $calendario_id, $actividad_id, $fecha, $email
            ));
            if ($ya_reservado > 0) {
                return ['error' => 'doble_reserva', 'mensaje' => __('Ya tienes una reserva activa para esta actividad en esta fecha.', 'calendar-booking')];
            }
        }

        $horario = $actividad->horario;

        $token = CALEBO_Database::guardar_reserva([
            'calendario_id' => $calendario_id,
            'actividad_id'  => $actividad_id,
            'fecha'         => $fecha,
            'horario'       => $horario,
            'nombre'        => $nombre,
            'email'         => $email,
            'telefono'      => $telefono,
            'num_menores'   => $num_menores,
            'edades'        => $edades,
            'datos_extra'   => !empty($datos_extra_arr) ? wp_json_encode($datos_extra_arr) : null,
            'estado'        => 'activa',
        ]);

        if (!$token) {
            $wpdb->query('ROLLBACK');
            return ['error' => 'guardar_fallo', 'mensaje' => __('Error al guardar la reserva. Inténtalo de nuevo.', 'calendar-booking')];
        }
        $wpdb->query('COMMIT');

        // Invalidar caché
        $mes_reserva  = intval(gmdate('n', strtotime($fecha)));
        $anio_reserva = intval(gmdate('Y', strtotime($fecha)));
        delete_transient('calebo_disp_' . $calendario_id . '_' . $mes_reserva . '_' . $anio_reserva);

        // Notificaciones
        if ($email) {
            CALEBO_Notifications::enviar_confirmacion_cliente($calendario, $fecha, $actividad, $datos_extra_arr, $campos_config, $token);
        }
        CALEBO_Notifications::enviar_confirmacion_admin($calendario, $fecha, $actividad, $datos_extra_arr, $campos_config, $ocupadas);

        $url_confirmacion = self::get_url_confirmacion();
        $redirect_url     = null;
        if ($url_confirmacion) {
            $redirect_url = add_query_arg('calebo_token', $token, $url_confirmacion);
        }

        return [
            'status'       => 'ok',
            'mensaje'      => __('¡Reserva confirmada!', 'calendar-booking'),
            'fecha'        => date_i18n('d/m/Y', strtotime($fecha)),
            'horario'      => $horario,
            'num_menores'  => $num_menores,
            'token'        => $token,
            'actividad'    => $actividad->nombre,
            'sala'         => $actividad->sala ?? '',
            'redirect_url' => $redirect_url,
        ];
    }

    /**
     * Cancelar reserva por token (método testeable).
     *
     * @param string $token Token de la reserva.
     * @return array ['ok'=>true|false]
     */
    public static function cancelar_por_token($token) {
        $reserva = CALEBO_Database::get_reserva_por_token($token);
        if (empty($reserva) || $reserva->estado !== 'activa') {
            return ['ok' => false];
        }
        $result = CALEBO_Database::cancelar_por_token($token);
        return ['ok' => (bool) $result];
    }

    /**
     * Wrapper AJAX para procesar_reserva().
     */
    public static function ajax_reservar() {
        check_ajax_referer('calebo_nonce', 'nonce');
        $result = self::procesar_reserva(wp_unslash($_POST));
        if (!empty($result['error'])) {
            $code = 200;
            if ($result['error'] === 'rate_limit') {
                $code = 429;
            }
            wp_send_json_error(['mensaje' => $result['mensaje']], $code);
        }
        wp_send_json_success($result);
    }

    /**
     * Registrar en lista de espera cuando la actividad está llena.
     * Recoge los mismos campos que una reserva normal para poder promocionar
     * al usuario automáticamente sin pedirle datos de nuevo.
     */
    public static function ajax_lista_espera() {
        check_ajax_referer('calebo_nonce', 'nonce');

        $post          = wp_unslash($_POST);
        global $wpdb;

        // Rate-limiting propio de la lista de espera (clave separada para no consumir
        // la cuota de reservas de usuarios legítimos).
        $ip_wl          = function_exists('calebo_get_real_ip') ? calebo_get_real_ip() : '0.0.0.0';
        $ip_wl          = apply_filters('calebo_rate_limit_ip', $ip_wl);
        $ua_wl          = apply_filters('calebo_rate_limit_ua', sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $uid_wl         = get_current_user_id()
                        ? get_current_user_id()
                        : (isset($_COOKIE[LOGGED_IN_COOKIE]) ? md5($_COOKIE[LOGGED_IN_COOKIE]) : '0');
        $key_wl         = 'calebo_limit_wl_' . md5($ip_wl . '|' . $ua_wl . '|' . $uid_wl);
        $retry_wl       = (int) get_transient($key_wl);
        $rate_limit_wl  = (int) get_option('calebo_rate_limit', 5);
        if ($retry_wl >= $rate_limit_wl) {
            do_action('calebo_security_log', 'rate_limit_espera', ['ip' => $ip_wl, 'codigo' => 'ip_ua_user']);
            wp_send_json_error(['mensaje' => __('Has superado el límite de intentos. Por favor, espera una hora.', 'calendar-booking')], 429);
        }
        set_transient($key_wl, $retry_wl + 1, HOUR_IN_SECONDS);

        $calendario_id = intval($post['calendario_id'] ?? 0);
        $actividad_id  = intval($post['actividad_id']  ?? 0);
        $fecha         = sanitize_text_field($post['fecha'] ?? '');

        if (!$calendario_id || !$actividad_id || !$fecha) {
            wp_send_json_error(['mensaje' => __('Por favor, completa los campos obligatorios.', 'calendar-booking')]);
        }

        // Validar fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || strtotime($fecha) === false) {
            wp_send_json_error(['mensaje' => __('Fecha no válida.', 'calendar-booking')]);
        }

        // Verificar que la actividad existe y tiene lista de espera activada
        $actividad_espera = CALEBO_Database::get_actividad($actividad_id);
        if (!$actividad_espera || empty($actividad_espera->lista_espera)) {
            wp_send_json_error(['mensaje' => __('La lista de espera no está disponible para esta actividad.', 'calendar-booking')]);
        }

        // Verificar que la actividad pertenece al calendario indicado
        if ((int) $actividad_espera->calendario_id !== $calendario_id) {
            wp_send_json_error(['mensaje' => __('Datos de actividad no válidos.', 'calendar-booking')]);
        }

        // Verificar que la fecha está en rango
        if ($fecha < $actividad_espera->fecha_inicio || $fecha > $actividad_espera->fecha_fin) {
            wp_send_json_error(['mensaje' => __('La fecha indicada no está disponible para esta actividad.', 'calendar-booking')]);
        }

        // Recoger campos_extra del formulario (igual que ajax_reservar)
        $datos_extra_arr = [];
        if (!empty($post['campos_extra']) && is_array($post['campos_extra'])) {
            foreach ($post['campos_extra'] as $ce_nombre => $ce_valor) {
                $datos_extra_arr[sanitize_key($ce_nombre)] = sanitize_text_field($ce_valor);
            }
        }

        // Validar campos obligatorios de la actividad
        $campos_config = json_decode($actividad_espera->campos_extra ?? '[]', true) ?: [];
        foreach ($campos_config as $campo) {
            $clave     = $campo['nombre'] ?? '';
            $requerido = !empty($campo['requerido']);
            if ($requerido && $clave) {
                $valor = $datos_extra_arr[$clave] ?? '';
                if (trim($valor) === '') {
                    $label = $campo['label'] ?? $campo['etiqueta'] ?? $clave;
                    wp_send_json_error(['mensaje' => sprintf(
                        /* translators: %s: nombre del campo. */
                        __('El campo "%s" es obligatorio.', 'calendar-booking'),
                        $label
                    )]);
                }
            }
        }

        // Mapear campos estándar desde datos_extra
        $nombre      = sanitize_text_field($datos_extra_arr['nombre']      ?? '');
        $email       = sanitize_email(     $datos_extra_arr['email']       ?? '');
        $telefono    = sanitize_text_field($datos_extra_arr['telefono']    ?? '');
        $num_menores = max(1, intval(      $datos_extra_arr['num_menores'] ?? 1));
        $edades      = sanitize_text_field($datos_extra_arr['edades']      ?? '');

        if (!$email) {
            wp_send_json_error(['mensaje' => __('El email es obligatorio.', 'calendar-booking')]);
        }

        if (!is_email($email)) {
            wp_send_json_error(['mensaje' => __('El email no tiene un formato válido.', 'calendar-booking')]);
        }

        // Solo se admite en la espera si la actividad está llena o si el número de
        // menores solicitado no cabe en las plazas que quedan (la reserva sería rechazada).
        $ocupadas_wl = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(num_menores), 0) FROM {$wpdb->prefix}calebo_reservas
             WHERE calendario_id = %d AND actividad_id = %d AND fecha = %s AND estado = 'activa'",
            $calendario_id, $actividad_id, $fecha
        ));
        $plazas_libres_wl = max(0, (int) ($actividad_espera->limite ?? 0) - $ocupadas_wl);
        if ($plazas_libres_wl > 0 && $num_menores <= $plazas_libres_wl) {
            wp_send_json_error(['mensaje' => __('Todavía hay plazas disponibles en esta actividad.', 'calendar-booking')]);
        }

        // Segunda dimensión de rate-limit: email + actividad + fecha (mismo patrón que la reserva).
        $key2_wl   = 'calebo_limit_wlef_' . md5($email . '|' . $actividad_id . '|' . $fecha);
        $retry2_wl = (int) get_transient($key2_wl);
        if ($retry2_wl >= $rate_limit_wl) {
            do_action('calebo_security_log', 'rate_limit_espera', ['ip' => $ip_wl, 'email' => $email, 'codigo' => 'email_actividad_fecha']);
            wp_send_json_error(['mensaje' => __('Has superado el límite de intentos para esta solicitud. Por favor, espera una hora.', 'calendar-booking')], 429);
        }
        set_transient($key2_wl, $retry2_wl + 1, HOUR_IN_SECONDS);

        if (CALEBO_Database::existe_en_espera($email, $actividad_id, $fecha)) {
            wp_send_json_error(['mensaje' => __('Ya estás en la lista de espera para esta actividad.', 'calendar-booking')]);
        }

        $guardado = CALEBO_Database::guardar_espera([
            'calendario_id' => $calendario_id,
            'actividad_id'  => $actividad_id,
            'fecha'         => $fecha,
            'nombre'        => $nombre,
            'email'         => $email,
            'telefono'      => $telefono,
            'num_menores'   => $num_menores,
            'edades'        => $edades,
            'datos_extra'   => !empty($datos_extra_arr) ? wp_json_encode($datos_extra_arr) : null,
        ]);

        // Fallback por si las columnas nuevas aún no existen en BD (instalaciones sin migrar)
        if ($guardado === false) {
            CALEBO_Database::maybe_migrate(); // forzar migración ahora
            $guardado = CALEBO_Database::guardar_espera([
                'calendario_id' => $calendario_id,
                'actividad_id'  => $actividad_id,
                'fecha'         => $fecha,
                'nombre'        => $nombre,
                'email'         => $email,
                'telefono'      => $telefono,
                'num_menores'   => $num_menores,
                'edades'        => $edades,
                'datos_extra'   => !empty($datos_extra_arr) ? wp_json_encode($datos_extra_arr) : null,
            ]);
        }

        if ($guardado === false) {
            wp_send_json_error(['mensaje' => __('Error al guardar en la lista de espera. Inténtalo de nuevo.', 'calendar-booking')]);
        }

        wp_send_json_success([
            'mensaje' => __('Te hemos añadido a la lista de espera. Te avisaremos si se libera una plaza.', 'calendar-booking'),
        ]);
    }

    /**
     * Invalida la caché del CSS dinámico de todos los calendarios.
     * Llamar desde class-admin.php cada vez que se guarda la configuración
     * de diseño o colores de cualquier calendario.
     */
    public static function invalidar_css_cache() {
        global $wpdb;
        // Eliminar todos los transients calebo_dynamic_css_* de la BD
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_calebo_dynamic_css_%' OR option_name LIKE '_transient_timeout_calebo_dynamic_css_%'"
        );
    }
}
