<?php
if (!defined('ABSPATH')) exit;

class CALEBO_Settings {

    /**
     * Invalida la caché de disponibilidad de todos los meses de un calendario.
     */
    private static function invalidar_cache_calendario($calendario_id) {
        if (!$calendario_id) return;
        $anio_actual = intval(gmdate('Y'));
        for ($anio = $anio_actual - 1; $anio <= $anio_actual + 6; $anio++) {
            for ($mes = 1; $mes <= 12; $mes++) {
                delete_transient('calebo_disp_' . $calendario_id . '_' . $mes . '_' . $anio);
            }
        }
    }

    private static function ensure_manage_options() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes para acceder a esta página.', 'calendar-booking'));
        }
    }

    public static function pagina_calendarios() {
        self::ensure_manage_options();
        // Nonce: obligatorio cuando hay parámetros de navegación/acción. El menú admin
        // (URL plana sin parámetros) no requiere nonce.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Entrada verificada con check_admin_referer inmediatamente después.
        if ( isset( $_GET['editar'] ) ) {
            // Solo navegación de vista; 'eliminar_calendario' verifica su propio nonce más abajo.
            check_admin_referer('calebo_calendarios_page');
        }
        // phpcs:enable
        global $wpdb;

        // ── ELIMINAR CALENDARIO ──
        if (isset($_GET['eliminar_calendario'])) {
            $del_id = intval(wp_unslash($_GET['eliminar_calendario']));
            check_admin_referer('calebo_eliminar_calendario_' . $del_id);
            // Bloquear si tiene actividades asociadas
            $actividades_del = CALEBO_Database::get_actividades_by_calendario($del_id);
            if (!empty($actividades_del)) {
                echo '<div class="notice notice-error"><p>' . esc_html__('No se puede eliminar el calendario porque tiene actividades asociadas. Elimina primero las actividades.', 'calendar-booking') . '</p></div>';
            } else {
                $wpdb->delete("{$wpdb->prefix}calebo_calendarios", ['id' => $del_id]);
                self::invalidar_cache_calendario($del_id);
                echo '<div class="notice notice-success"><p>' . esc_html__('Calendario eliminado correctamente.', 'calendar-booking') . '</p></div>';
            }
        }

        if (isset($_POST['calebo_guardar_calendario'])) {
            check_admin_referer('calebo_guardar_calendario');
            $post = wp_unslash($_POST);
            $id = intval($post['id'] ?? 0);
            // Formulario único: guardar todos los campos disponibles en el POST
            $data = [
                'nombre'           => sanitize_text_field($post['nombre'] ?? ''),
                'descripcion'      => sanitize_textarea_field($post['descripcion'] ?? ''),
                'dias_disponibles' => implode(',', array_map('intval', $post['dias_disponibles'] ?? [])),
            ];
            // Campos avanzados solo presentes al editar
            if (!empty($post['horario_normal'])) {
                $data['horario_normal'] = sanitize_text_field($post['horario_normal']) ?: '16:00-20:00';
            }
            if (isset($post['limite'])) {
                $data['limite'] = max(1, intval($post['limite']));
            }
            if (isset($post['horario_vacaciones'])) {
                $data['horario_vacaciones'] = sanitize_text_field($post['horario_vacaciones']);
            }
            if (isset($post['meses_max'])) {
                $data['meses_max'] = max(1, intval($post['meses_max']));
            }
            if (isset($post['mes_inicio_ciclo'])) {
                $data['mes_inicio_ciclo'] = max(1, min(12, intval($post['mes_inicio_ciclo'])));
            }
            if (isset($post['dia_activacion'])) {
                $data['dia_activacion'] = max(0, min(28, intval($post['dia_activacion'])));
            }
            // Campos de apariencia solo presentes al editar
            if (isset($post['color_primary'])) {
                $data['color_primary'] = sanitize_hex_color($post['color_primary']) ?: '#F7941D';
            }
            if (isset($post['color_secondary'])) {
                $data['color_secondary'] = sanitize_hex_color($post['color_secondary']) ?: '#1E3FA3';
            }
            if (isset($post['logo_url'])) {
                $data['logo_url'] = esc_url_raw($post['logo_url']);
            }

            if ($id) {
                $wpdb->update("{$wpdb->prefix}calebo_calendarios", $data, ['id' => $id]);
                // Invalidar caché de disponibilidad del calendario
                self::invalidar_cache_calendario($id);
                echo '<div class="notice notice-success"><p>' . esc_html__('Calendario actualizado.', 'calendar-booking') . '</p></div>';
            } else {
                $wpdb->insert("{$wpdb->prefix}calebo_calendarios", $data);
                echo '<div class="notice notice-success"><p>' . esc_html__('Calendario creado. Shortcode:', 'calendar-booking') . ' <code>[calebo_calendario id="' . esc_attr($wpdb->insert_id) . '"]</code></p></div>';
            }
        }

        $calendarios  = CALEBO_Database::get_calendarios();
        $editar       = isset($_GET['editar']) ? CALEBO_Database::get_calendario(intval(wp_unslash($_GET['editar']))) : null;
        $dias_activos = $editar ? explode(',', $editar->dias_disponibles ?? '1,2,3,4,5') : ['1','2','3','4','5'];

        // Variables para compatibilidad (ya no se usan en el formulario pero se mantienen por si acaso)
        $festivos   = $editar ? json_decode($editar->festivos ?? '[]', true) ?: [] : [];
        $vacaciones = $editar ? json_decode($editar->vacaciones ?? '[]', true) ?: [] : [];

        $dias_semana = [
            1 => __('Lunes', 'calendar-booking'),
            2 => __('Martes', 'calendar-booking'),
            3 => __('Miércoles', 'calendar-booking'),
            4 => __('Jueves', 'calendar-booking'),
            5 => __('Viernes', 'calendar-booking'),
            6 => __('Sábado', 'calendar-booking'),
            7 => __('Domingo', 'calendar-booking')
        ];
        $meses = [
            1 => __('Enero', 'calendar-booking'),
            2 => __('Febrero', 'calendar-booking'),
            3 => __('Marzo', 'calendar-booking'),
            4 => __('Abril', 'calendar-booking'),
            5 => __('Mayo', 'calendar-booking'),
            6 => __('Junio', 'calendar-booking'),
            7 => __('Julio', 'calendar-booking'),
            8 => __('Agosto', 'calendar-booking'),
            9 => __('Septiembre', 'calendar-booking'),
            10 => __('Octubre', 'calendar-booking'),
            11 => __('Noviembre', 'calendar-booking'),
            12 => __('Diciembre', 'calendar-booking')
        ];
        include CALEBO_PLUGIN_DIR . 'templates/admin/settings-calendarios.php';
    }

    // ── PÁGINA DE DISEÑO ──
    public static function pagina_diseno() {
        self::ensure_manage_options();
        // Nonce: obligatorio cuando hay parámetros de navegación. El menú admin no requiere nonce.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Entrada verificada con check_admin_referer inmediatamente después.
        if ( isset( $_GET['calendario_id'] ) ) {
            check_admin_referer('calebo_diseno_page');
        }
        // phpcs:enable
        $calendarios = CALEBO_Database::get_calendarios();
        $cal_id      = intval(wp_unslash($_GET['calendario_id'] ?? ($calendarios[0]->id ?? 0)));
        $calendario  = CALEBO_Database::get_calendario($cal_id);
        $diseno      = json_decode($calendario->diseno ?? '{}', true) ?: [];

        $d = array_merge([
            'fuente'              => 'Montserrat',
            'celda_radio'         => '6',
            'celda_padding'       => '12',
            'celda_ancho'         => '100',
            'completo_bg'         => '#1E3FA3',
            'completo_color'      => '#ffffff',
            'completo_texto'      => 'COMPLETO',
            'completo_rotacion'   => '-10',
            'btn_radio'           => '6',
            'btn_texto'           => 'Confirmar reserva',
            'btn_uppercase'       => '1',
            'fondo_tipo'             => 'color',
            'fondo_color'            => '#ffffff',
            'fondo_imagen'           => '',
            'color_semana_texto'     => '#F7941D',
            'color_numeros_mes'      => '#2D2D2D',
            'multi_color'            => '#F7941D',
            'multi_forma'            => 'circulo',
            'multi_size'             => 16,
            'email_asunto_conf'   => '✅ Reserva confirmada – {calendario}',
            'email_msg_conf'      => 'Hola <strong>{nombre}</strong>,<br>tu reserva en <strong>{calendario}</strong> ha sido confirmada correctamente.',
            'email_asunto_canc'   => '❌ Reserva cancelada – {calendario}',
            'email_msg_canc'      => 'Hola <strong>{nombre}</strong>,<br>tu reserva ha sido <strong>cancelada correctamente</strong>.',
            'email_msg_canc_admin'=> 'Hola <strong>{nombre}</strong>,<br>lamentamos comunicarle que por motivos excepcionales <strong>hemos tenido que cancelar su reserva</strong>. Disculpe las molestias ocasionadas.',
        ], $diseno);

        if (isset($_POST['calebo_guardar_diseno'])) {
            check_admin_referer('calebo_guardar_diseno_' . $cal_id);
            $post = wp_unslash($_POST);
            $nuevo = [
                'fuente'              => sanitize_text_field($post['fuente'] ?? 'Arial'),
                'celda_radio'         => intval($post['celda_radio'] ?? 6),
                'celda_padding'       => intval($post['celda_padding'] ?? 12),
                'celda_ancho'         => intval($post['celda_ancho'] ?? 100),
                'completo_bg'         => sanitize_hex_color($post['completo_bg'] ?? '') ?: '#1E3FA3',
                'completo_color'      => sanitize_hex_color($post['completo_color'] ?? '') ?: '#ffffff',
                'completo_texto'      => sanitize_text_field($post['completo_texto'] ?? 'COMPLETO'),
                'completo_rotacion'   => intval($post['completo_rotacion'] ?? -10),
                'btn_radio'           => intval($post['btn_radio'] ?? 6),
                'btn_texto'           => sanitize_text_field($post['btn_texto'] ?? ''),
                'btn_uppercase'       => isset($post['btn_uppercase']) ? '1' : '0',
                'fondo_tipo'             => sanitize_text_field($post['fondo_tipo'] ?? 'color'),
                'fondo_color'            => sanitize_hex_color($post['fondo_color'] ?? '') ?: '#ffffff',
                'fondo_imagen'           => esc_url_raw($post['fondo_imagen'] ?? ''),
                'color_semana_texto'     => sanitize_hex_color($post['color_semana_texto'] ?? '') ?: '#F7941D',
                'color_numeros_mes'      => sanitize_hex_color($post['color_numeros_mes'] ?? '') ?: '#2D2D2D',
                'multi_color'            => sanitize_hex_color($post['multi_color'] ?? '') ?: '#F7941D',
                'multi_forma'            => in_array($post['multi_forma'] ?? '', ['circulo','cuadrado','redondeado','diamante','estrella']) ? $post['multi_forma'] : 'circulo',
                'multi_size'             => min(36, max(10, intval($post['multi_size'] ?? 16))),
                'email_asunto_conf'   => sanitize_text_field($post['email_asunto_conf'] ?? ''),
                'email_msg_conf'      => wp_kses_post($post['email_msg_conf'] ?? ''),
                'email_asunto_canc'   => sanitize_text_field($post['email_asunto_canc'] ?? ''),
                'email_msg_canc'      => wp_kses_post($post['email_msg_canc'] ?? ''),
                'email_msg_canc_admin'=> wp_kses_post($post['email_msg_canc_admin'] ?? ''),
            ];

            global $wpdb;
            $wpdb->update("{$wpdb->prefix}calebo_calendarios", [
                'color_primary'   => sanitize_hex_color($post['color_primary'] ?? '') ?: '#F7941D',
                'color_secondary' => sanitize_hex_color($post['color_secondary'] ?? '') ?: '#1E3FA3',
                'diseno'          => wp_json_encode($nuevo),
            ], ['id' => $cal_id]);

            $d          = $nuevo;
            $calendario = CALEBO_Database::get_calendario($cal_id);
            self::invalidar_cache_calendario($cal_id);
            // Invalidar también la caché del CSS dinámico del frontend
            CALEBO_Booking::invalidar_css_cache();
            echo '<div class="notice notice-success"><p>' . esc_html__('Diseño guardado correctamente.', 'calendar-booking') . '</p></div>';
        }

        $fuentes_locales = ['Poppins', 'Lora'];

        $fuentes = [
            'sistema' => [
                'label'   => __('Sistema (sin carga externa)', 'calendar-booking'),
                'fuentes' => [
                    'Arial', 'Helvetica', 'Tahoma', 'Verdana',
                    'Trebuchet MS', 'Georgia', 'Times New Roman',
                ],
            ],
            'local' => [
                'label'   => __('Incluidas en el plugin (sin carga externa)', 'calendar-booking'),
                'fuentes' => $fuentes_locales,
            ],
            'google' => [
                'label'   => __('Google Fonts (excepción permitida)', 'calendar-booking'),
                'fuentes' => [ 'Montserrat' ],
            ],
        ];
        $primary   = $calendario->color_primary   ?? '#F7941D';
        $secondary = $calendario->color_secondary ?? '#1E3FA3';
        include CALEBO_PLUGIN_DIR . 'templates/admin/settings-diseno.php';
        wp_enqueue_media();
    }

    // ── PÁGINA DE CAMPOS (modelo unificado: solo actividades) ──
    public static function pagina_campos() {
        self::ensure_manage_options();
        // Nonce: obligatorio cuando hay parámetros de navegación. El menú admin no requiere nonce.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Entrada verificada con check_admin_referer inmediatamente después.
        if ( ! isset( $_GET['eliminar_campo_actividad'] ) && ( isset( $_GET['calendario_id'] ) || isset( $_GET['actividad_id'] ) || isset( $_GET['editar_campo_actividad'] ) ) ) {
            // Solo navegación pura; 'eliminar_campo_actividad' verifica su propio nonce más abajo.
            check_admin_referer('calebo_campos_page');
        }
        // phpcs:enable
        $calendarios = CALEBO_Database::get_calendarios();
        $cal_id      = intval(wp_unslash($_GET['calendario_id'] ?? ($calendarios[0]->id ?? 0)));
        $act_id      = intval(wp_unslash($_GET['actividad_id'] ?? 0));
        $actividades_cb = CALEBO_Database::get_actividades_by_calendario($cal_id);

        // Si no hay actividad seleccionada y hay actividades, seleccionar la primera
        if ($act_id === 0 && !empty($actividades_cb)) {
            $act_id = (int) $actividades_cb[0]->id;
        }

        $plantillas = [
            'ludoteca' => [
                'label'  => '🎮 ' . __('Ludoteca', 'calendar-booking'),
                'campos' => [
                    ['nombre' => 'nombre',      'etiqueta' => __('Nombre y apellidos', 'calendar-booking'), 'tipo' => 'text',   'placeholder' => __('Nombre completo del adulto', 'calendar-booking'), 'obligatorio' => 1],
                    ['nombre' => 'email',        'etiqueta' => __('Email',              'calendar-booking'), 'tipo' => 'email',  'placeholder' => 'tu@email.com',               'obligatorio' => 1],
                    ['nombre' => 'telefono',     'etiqueta' => __('Teléfono',           'calendar-booking'), 'tipo' => 'tel',    'placeholder' => '612 345 678',                'obligatorio' => 1],
                    ['nombre' => 'num_menores',  'etiqueta' => __('Nº de menores',      'calendar-booking'), 'tipo' => 'number', 'placeholder' => __('Ej: 2', 'calendar-booking'),                      'obligatorio' => 1],
                    ['nombre' => 'edades',       'etiqueta' => __('Edades',             'calendar-booking'), 'tipo' => 'text',   'placeholder' => __('Ej: 5, 7, 9 años', 'calendar-booking'),           'obligatorio' => 0],
                ],
            ],
            'generica' => [
                'label'  => '📅 ' . __('Reserva genérica', 'calendar-booking'),
                'campos' => [
                    ['nombre' => 'nombre',      'etiqueta' => __('Nombre y apellidos', 'calendar-booking'), 'tipo' => 'text',     'placeholder' => __('Nombre completo', 'calendar-booking'), 'obligatorio' => 1],
                    ['nombre' => 'email',       'etiqueta' => __('Email',              'calendar-booking'), 'tipo' => 'email',    'placeholder' => 'tu@email.com',    'obligatorio' => 1],
                    ['nombre' => 'telefono',    'etiqueta' => __('Teléfono',           'calendar-booking'), 'tipo' => 'tel',      'placeholder' => '612 345 678',     'obligatorio' => 1],
                    ['nombre' => 'comentarios', 'etiqueta' => __('Comentarios',        'calendar-booking'), 'tipo' => 'textarea', 'placeholder' => __('Comentarios...', 'calendar-booking'),  'obligatorio' => 0],
                ],
            ],
            'medica' => [
                'label'  => '🏥 ' . __('Cita médica', 'calendar-booking'),
                'campos' => [
                    ['nombre' => 'nombre',   'etiqueta' => __('Nombre y apellidos', 'calendar-booking'), 'tipo' => 'text',     'placeholder' => __('Nombre completo', 'calendar-booking'),      'obligatorio' => 1],
                    ['nombre' => 'email',    'etiqueta' => __('Email',              'calendar-booking'), 'tipo' => 'email',    'placeholder' => 'tu@email.com',          'obligatorio' => 1],
                    ['nombre' => 'telefono', 'etiqueta' => __('Teléfono',           'calendar-booking'), 'tipo' => 'tel',      'placeholder' => '612 345 678',           'obligatorio' => 1],
                    ['nombre' => 'motivo',   'etiqueta' => __('Motivo de la cita',  'calendar-booking'), 'tipo' => 'textarea', 'placeholder' => __('Describe el motivo...', 'calendar-booking'), 'obligatorio' => 1],
                ],
            ],
            'taller' => [
                'label'  => '🎨 ' . __('Clase o taller', 'calendar-booking'),
                'campos' => [
                    ['nombre' => 'nombre',      'etiqueta' => __('Nombre y apellidos', 'calendar-booking'), 'tipo' => 'text',     'placeholder' => __('Nombre completo', 'calendar-booking'),  'obligatorio' => 1],
                    ['nombre' => 'email',       'etiqueta' => __('Email',              'calendar-booking'), 'tipo' => 'email',    'placeholder' => 'tu@email.com',      'obligatorio' => 1],
                    ['nombre' => 'telefono',    'etiqueta' => __('Teléfono',           'calendar-booking'), 'tipo' => 'tel',      'placeholder' => '612 345 678',       'obligatorio' => 1],
                    ['nombre' => 'nivel',       'etiqueta' => __('Nivel',              'calendar-booking'), 'tipo' => 'select',   'placeholder' => '',                  'obligatorio' => 1,
                     'opciones' => __("Principiante\nIntermedio\nAvanzado", 'calendar-booking')],
                    ['nombre' => 'comentarios', 'etiqueta' => __('Comentarios',        'calendar-booking'), 'tipo' => 'textarea', 'placeholder' => __('Comentarios...', 'calendar-booking'),    'obligatorio' => 0],
                ],
            ],
        ];

        // ── Aplicar plantilla a actividad ──
        if (isset($_POST['calebo_aplicar_plantilla_actividad']) && $act_id > 0) {
            check_admin_referer('calebo_aplicar_plantilla_actividad_' . $act_id);
            $post = wp_unslash($_POST);
            $tipo_plantilla = sanitize_key($post['plantilla'] ?? '');
            if (isset($plantillas[$tipo_plantilla])) {
                $campos_plantilla = [];
                foreach ($plantillas[$tipo_plantilla]['campos'] as $campo) {
                    $campos_plantilla[] = [
                        'nombre'      => $campo['nombre'],
                        'label'       => $campo['etiqueta'],
                        'tipo'        => $campo['tipo'],
                        'placeholder' => $campo['placeholder'],
                        'opciones'    => $campo['opciones'] ?? '',
                        'requerido'   => $campo['obligatorio'],
                    ];
                }
                CALEBO_Database::actualizar_campos_actividad($act_id, wp_json_encode($campos_plantilla));
                echo '<div class="notice notice-success"><p>✅ ' . esc_html__('Plantilla', 'calendar-booking') . ' <strong>' . esc_html($plantillas[$tipo_plantilla]['label']) . '</strong> ' . esc_html__('aplicada.', 'calendar-booking') . '</p></div>';
            }
        }

        // ── Guardar / eliminar / reordenar campos de actividad ──
        if ($act_id > 0) {
            $actividad_obj = CALEBO_Database::get_actividad($act_id);
            $campos_act_arr = [];
            if ($actividad_obj && !empty($actividad_obj->campos_extra)) {
                $campos_act_arr = json_decode($actividad_obj->campos_extra, true) ?: [];
            }

            if (isset($_POST['calebo_guardar_campo_actividad'])) {
                check_admin_referer('calebo_guardar_campo_actividad_' . $act_id);
                $post = wp_unslash($_POST);
                $idx  = intval($post['campo_idx'] ?? -1);
                $item = [
                    'nombre'      => sanitize_key($post['nombre'] ?? ''),
                    'label'       => sanitize_text_field($post['etiqueta'] ?? ''),
                    'tipo'        => sanitize_text_field($post['tipo'] ?? 'text'),
                    'placeholder' => sanitize_text_field($post['placeholder'] ?? ''),
                    'opciones'    => sanitize_textarea_field($post['opciones'] ?? ''),
                    'requerido'   => isset($post['obligatorio']) ? 1 : 0,
                ];
                if ($idx >= 0 && isset($campos_act_arr[$idx])) {
                    $campos_act_arr[$idx] = $item;
                } else {
                    $campos_act_arr[] = $item;
                }
                CALEBO_Database::actualizar_campos_actividad($act_id, wp_json_encode($campos_act_arr));
                echo '<div class="notice notice-success"><p>' . esc_html__('Campo guardado.', 'calendar-booking') . '</p></div>';
            }

            if (isset($_GET['eliminar_campo_actividad'])) {
                $del_idx = intval(wp_unslash($_GET['eliminar_campo_actividad']));
                check_admin_referer('calebo_eliminar_campo_actividad_' . $del_idx . '_' . $act_id);
                array_splice($campos_act_arr, $del_idx, 1);
                CALEBO_Database::actualizar_campos_actividad($act_id, wp_json_encode(array_values($campos_act_arr)));
                echo '<div class="notice notice-success"><p>' . esc_html__('Campo eliminado.', 'calendar-booking') . '</p></div>';
            }

            // Reordenación de campos se maneja por AJAX en CALEBO_Admin::ajax_orden_campos
        }

        $tipos  = [
            'text'     => __('Texto corto', 'calendar-booking'),
            'textarea' => __('Texto largo', 'calendar-booking'),
            'email'    => __('Email', 'calendar-booking'),
            'tel'      => __('Teléfono', 'calendar-booking'),
            'number'   => __('Número', 'calendar-booking'),
            'select'   => __('Selector (dropdown)', 'calendar-booking'),
            'checkbox' => __('Casilla', 'calendar-booking'),
            'date'     => __('Fecha', 'calendar-booking'),
        ];

        wp_enqueue_script( 'sortablejs', CALEBO_PLUGIN_URL . 'assets/js/Sortable.min.js', [], '1.15.0', true );
        include CALEBO_PLUGIN_DIR . 'templates/admin/settings-campos.php';
    }
}
