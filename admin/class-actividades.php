<?php
if (!defined('ABSPATH')) exit;

class CALEBO_Actividades {

    public static function init() {
        add_action('admin_post_calebo_guardar_actividad',  [__CLASS__, 'guardar']);
        add_action('admin_post_calebo_eliminar_actividad', [__CLASS__, 'eliminar']);
        add_action('wp_ajax_calebo_guardar_festivos_actividad', [__CLASS__, 'ajax_guardar_festivos']);
        add_action('wp_ajax_calebo_guardar_vacaciones_actividad', [__CLASS__, 'ajax_guardar_vacaciones']);
    }

    /**
     * AJAX: Guardar festivos de una actividad
     */
    public static function ajax_guardar_festivos() {
        check_ajax_referer('calebo_festivos_actividad');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No tienes permisos para realizar esta acción.', 'calendar-booking')]);
        }

        $actividad_id = intval($_POST['actividad_id'] ?? 0);
        $festivos     = wp_unslash( $_POST['festivos'] ?? [] );

        if (!$actividad_id) {
            wp_send_json_error(['message' => __('ID de actividad no válido.', 'calendar-booking')]);
        }

        $festivos_sanitized = [];
        foreach ($festivos as $f) {
            if (!empty($f['fecha'])) {
                $festivos_sanitized[] = [
                    'label' => sanitize_text_field($f['label'] ?? ''),
                    'fecha' => sanitize_text_field($f['fecha']),
                ];
            }
        }

        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}calebo_actividades",
            ['festivos' => wp_json_encode($festivos_sanitized)],
            ['id' => $actividad_id]
        );

        // Invalidar caché del calendario asociado
        $actividad_para_cache = CALEBO_Database::get_actividad($actividad_id);
        if ($actividad_para_cache) {
            self::invalidar_cache_calendario((int) $actividad_para_cache->calendario_id);
        }

        wp_send_json_success(['message' => __('Festivos guardados correctamente.', 'calendar-booking')]);
    }

    /**
     * AJAX: Guardar vacaciones de una actividad
     */
    public static function ajax_guardar_vacaciones() {
        check_ajax_referer('calebo_vacaciones_actividad');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No tienes permisos para realizar esta acción.', 'calendar-booking')]);
        }

        $actividad_id = intval($_POST['actividad_id'] ?? 0);
        $vacaciones   = wp_unslash( $_POST['vacaciones'] ?? [] );

        if (!$actividad_id) {
            wp_send_json_error(['message' => __('ID de actividad no válido.', 'calendar-booking')]);
        }

        $vacaciones_sanitized = [];
        foreach ($vacaciones as $v) {
            if (!empty($v['inicio']) && !empty($v['fin'])) {
                $vacaciones_sanitized[] = [
                    'label'            => sanitize_text_field($v['label'] ?? ''),
                    'inicio'           => sanitize_text_field($v['inicio']),
                    'fin'              => sanitize_text_field($v['fin']),
                    'tipo'             => sanitize_text_field($v['tipo'] ?? 'bloqueado'),
                    'horario_especial' => ($v['tipo'] ?? 'bloqueado') === 'horario_especial' ? 1 : 0,
                ];
            }
        }

        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}calebo_actividades",
            ['vacaciones' => wp_json_encode($vacaciones_sanitized)],
            ['id' => $actividad_id]
        );

        // Invalidar caché del calendario asociado
        $actividad_para_cache_v = CALEBO_Database::get_actividad($actividad_id);
        if ($actividad_para_cache_v) {
            self::invalidar_cache_calendario((int) $actividad_para_cache_v->calendario_id);
        }

        wp_send_json_success(['message' => __('Vacaciones guardadas correctamente.', 'calendar-booking')]);
    }

    public static function guardar() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'calendar-booking'));
        }
        check_admin_referer('calebo_guardar_actividad');

        $post          = wp_unslash($_POST);
        $calendario_id = intval($post['calendario_id'] ?? 0);
        $id            = intval($post['id'] ?? 0);

        // Detectar desde qué pestaña se envía el formulario
        $tab_origen = sanitize_key($post['tab_origen'] ?? 'configuracion');

        // Solo validar campos básicos cuando se guarda desde la pestaña de configuración
        if ($tab_origen === 'configuracion') {
            $nombre       = sanitize_text_field($post['nombre'] ?? '');
            $fecha_inicio = sanitize_text_field($post['fecha_inicio'] ?? '');
            $fecha_fin    = sanitize_text_field($post['fecha_fin'] ?? '');
            $horario      = sanitize_text_field($post['horario'] ?? '');

            $error = '';
            if (empty($nombre) || empty($fecha_inicio) || empty($fecha_fin) || empty($horario)) {
                $error = 'campos_vacios';
            } elseif ($fecha_fin < $fecha_inicio) {
                $error = 'fechas_invalidas';
            }

            if ($error) {
                $url = admin_url('admin.php?page=calebo-actividades&calendario_id=' . $calendario_id);
                if ($id) {
                    $url .= '&editar=' . $id . '&tab=configuracion&error=' . $error;
                } else {
                    $url .= '&tab=editar&error=' . $error;
                }
                wp_safe_redirect(esc_url_raw(wp_nonce_url($url, 'calebo_actividades_page')));
                exit;
            }

            $data = [
                'calendario_id' => $calendario_id,
                'fecha_inicio'  => $fecha_inicio,
                'fecha_fin'     => $fecha_fin,
                'nombre'        => $nombre,
                'horario'       => $horario,
                'sala'          => sanitize_text_field($post['sala'] ?? ''),
                'limite'        => max(1, intval($post['limite'] ?? 20)),
                'descripcion'   => sanitize_textarea_field($post['descripcion'] ?? ''),
                'activo'        => 1,
                'lista_espera'  => isset($post['lista_espera']) ? 1 : 0,
            ];

        } elseif ($tab_origen === 'avanzada') {
            // Solo guardar campos de configuración avanzada
            if (!$id) {
                wp_safe_redirect(esc_url_raw(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $calendario_id), 'calebo_actividades_page')));
                exit;
            }
            $personalizar = isset($post['calebo_personalizar_config']);
            $data = [
                'id'              => $id,
                'calendario_id'   => $calendario_id,
                'meses_max'       => $personalizar && $post['meses_max'] !== '' ? max(1, intval($post['meses_max'])) : null,
                'dia_activacion'  => $personalizar && isset($post['dia_activacion']) && $post['dia_activacion'] !== '' ? intval($post['dia_activacion']) : null,
                'mes_inicio_ciclo' => $personalizar && !empty($post['mes_inicio_ciclo']) ? intval($post['mes_inicio_ciclo']) : null,
            ];

        } elseif ($tab_origen === 'festivos_vacaciones') {
            // Solo guardar horario de vacaciones
            if (!$id) {
                wp_safe_redirect(esc_url_raw(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $calendario_id), 'calebo_actividades_page')));
                exit;
            }
            // Si el campo viene en el POST (aunque esté vacío) lo guardamos.
            // String vacío = borrar personalización (heredar del calendario).
            // Si no viene en el POST en absoluto, no tocamos el valor existente (null = sin cambio).
            $data = [
                'id'            => $id,
                'calendario_id' => $calendario_id,
            ];
            if (isset($post['horario_vacaciones'])) {
                $valor_horario = sanitize_text_field(trim($post['horario_vacaciones']));
                // Guardamos el valor tal cual (puede ser cadena vacía para "heredar del calendario")
                // guardar_actividad omite null pero sí guarda string vacío
                $data['horario_vacaciones'] = $valor_horario !== '' ? $valor_horario : null;
            }
            // Si no vino en el POST, no añadimos la clave → guardar_actividad no lo toca

        } else {
            // Pestaña desconocida, redirigir sin cambios
            wp_safe_redirect(esc_url_raw(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $calendario_id . ($id ? '&editar=' . $id . '&tab=' . $tab_origen : '')), 'calebo_actividades_page')));
            exit;
        }

        if ($id && !isset($data['id'])) {
            $data['id'] = $id;
        }

        $resultado = CALEBO_Database::guardar_actividad($data);

        // Invalidar caché de disponibilidad para el calendario afectado
        self::invalidar_cache_calendario($calendario_id);

        $url = admin_url('admin.php?page=calebo-actividades&calendario_id=' . $calendario_id);
        if ($resultado) {
            if ($id) {
                $url .= '&editar=' . $id . '&tab=' . $tab_origen . '&guardado=1';
            } else {
                $url .= '&guardado=1';
            }
        } else {
            if ($id) {
                $url .= '&editar=' . $id . '&tab=' . $tab_origen . '&error=1';
            } else {
                $url .= '&tab=editar&error=1';
            }
        }
        wp_safe_redirect(esc_url_raw(wp_nonce_url($url, 'calebo_actividades_page')));
        exit;
    }

    public static function eliminar() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'calendar-booking'));
        }

        $id            = intval(wp_unslash($_GET['id']            ?? 0));
        $calendario_id = intval(wp_unslash($_GET['calendario_id'] ?? 0));

        check_admin_referer('calebo_eliminar_actividad_' . $id);
        CALEBO_Database::eliminar_actividad($id);

        // Invalidar caché de disponibilidad
        self::invalidar_cache_calendario($calendario_id);

        $url = admin_url('admin.php?page=calebo-actividades&calendario_id=' . $calendario_id . '&eliminado=1');
        wp_safe_redirect(esc_url_raw(wp_nonce_url($url, 'calebo_actividades_page')));
        exit;
    }

    public static function pagina() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes para acceder a esta página.', 'calendar-booking'));
        }
        // Nonce: obligatorio cuando hay parámetros de navegación. El menú admin no requiere nonce.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Entrada verificada con check_admin_referer inmediatamente después.
        if ( isset( $_GET['calendario_id'] ) || isset( $_GET['editar'] ) || isset( $_GET['guardado'] ) || isset( $_GET['eliminado'] ) || isset( $_GET['error'] ) || isset( $_GET['tab'] ) ) {
            check_admin_referer('calebo_actividades_page');
        }
        // phpcs:enable

        $calendarios   = CALEBO_Database::get_calendarios();
        $cal_id        = intval(wp_unslash($_GET['calendario_id'] ?? ($calendarios[0]->id ?? 0)));
        $editar_id     = intval(wp_unslash($_GET['editar'] ?? 0));
        $guardado      = isset($_GET['guardado']);
        $eliminado     = isset($_GET['eliminado']);
        $error         = isset($_GET['error']);

        $actividades   = CALEBO_Database::get_actividades_by_calendario($cal_id);
        $editar        = $editar_id ? CALEBO_Database::get_actividad($editar_id) : null;

        // Verificar que la actividad existe y pertenece al calendario seleccionado
        if ($editar_id && (!$editar || $editar->calendario_id != $cal_id)) {
            $editar = null;
            $editar_id = 0;
        }

        // Pestaña activa
        $tab_actual = sanitize_key( wp_unslash( $_GET['tab'] ?? 'listado' ) );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Actividades', 'calendar-booking'); ?></h1>

            <?php if ($guardado): ?>
            <div class="notice notice-success is-dismissible"><p>✅ <?php esc_html_e('Actividad guardada correctamente.', 'calendar-booking'); ?></p></div>
            <?php endif; ?>
            <?php if ($eliminado): ?>
            <div class="notice notice-success is-dismissible"><p>🗑️ <?php esc_html_e('Actividad eliminada.', 'calendar-booking'); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    ❌ <?php
                    $error_tipo = sanitize_key( wp_unslash( $_GET['error'] ) );
                    if ($error_tipo === 'fechas_invalidas') {
                        esc_html_e('La fecha de fin no puede ser anterior a la fecha de inicio.', 'calendar-booking');
                    } else {
                        esc_html_e('Error al guardar. Por favor, revisa que todos los campos obligatorios estén completos.', 'calendar-booking');
                    }
                    ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- FILTROS SUPERIORES -->
            <form method="get" style="display:flex;align-items:flex-end;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
                <input type="hidden" name="page" value="calebo-actividades">

                <?php if (count($calendarios) > 1): ?>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;"><?php esc_html_e('Calendario', 'calendar-booking'); ?></label>
                    <select id="calebo-filtro-calendario" name="calendario_id">
                        <?php foreach ($calendarios as $cal): ?>
                        <option value="<?php echo esc_attr($cal->id); ?>" <?php selected($cal->id, $cal_id); ?>>
                            <?php echo esc_html($cal->nombre); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="calendario_id" value="<?php echo esc_attr($cal_id); ?>">
                <?php endif; ?>

                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $cal_id . '&tab=editar'), 'calebo_actividades_page')); ?>"
                   class="button button-primary" style="margin-left:auto;">
                    + <?php esc_html_e('Nueva actividad', 'calendar-booking'); ?>
                </a>
            </form>

            <?php if ($editar): ?>
                <!-- VISTA DE EDICIÓN CON PESTAÑAS -->
                <?php self::render_editar_con_pestanas($editar, $cal_id, $tab_actual); ?>
            <?php elseif ($tab_actual === 'editar'): ?>
                <!-- FORMULARIO DE NUEVA ACTIVIDAD -->
                <?php self::render_nueva_actividad($cal_id); ?>
            <?php else: ?>
                <!-- LISTADO DE ACTIVIDADES -->
                <?php self::render_listado($actividades, $cal_id); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderiza el listado de actividades
     */
    private static function render_listado($actividades, $cal_id) {
        ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
            <table class="wp-list-table widefat striped" style="border-radius:8px;overflow:hidden;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Nombre', 'calendar-booking'); ?></th>
                        <th style="width:110px;"><?php esc_html_e('Desde', 'calendar-booking'); ?></th>
                        <th style="width:110px;"><?php esc_html_e('Hasta', 'calendar-booking'); ?></th>
                        <th style="width:100px;"><?php esc_html_e('Horario', 'calendar-booking'); ?></th>
                        <th style="width:90px;text-align:center;"><?php esc_html_e('Plazas', 'calendar-booking'); ?></th>
                        <th style="width:100px;"><?php esc_html_e('Sala', 'calendar-booking'); ?></th>
                        <th style="width:150px;"><?php esc_html_e('Acciones', 'calendar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($actividades)): ?>
                    <tr>
                        <td colspan="7" style="padding:40px;text-align:center;color:#888;">
                            <div style="font-size:32px;margin-bottom:12px;">📋</div>
                            <?php esc_html_e('No hay actividades programadas. Crea la primera.', 'calendar-booking'); ?>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($actividades as $act): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($act->nombre); ?></strong>
                                <?php if ($act->descripcion): ?>
                                <div style="font-size:12px;color:#888;margin-top:2px;"><?php echo esc_html(wp_trim_words($act->descripcion, 10)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($act->fecha_inicio))); ?></td>
                            <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($act->fecha_fin))); ?></td>
                            <td><?php echo esc_html($act->horario); ?></td>
                            <td style="text-align:center;"><?php echo esc_html($act->limite); ?></td>
                            <td><?php echo esc_html($act->sala ?? '—'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $cal_id . '&editar=' . $act->id . '&tab=configuracion'), 'calebo_actividades_page')); ?>"
                                   class="button button-small"><?php esc_html_e('Configurar', 'calendar-booking'); ?></a>
                                <a href="<?php echo esc_url(wp_nonce_url(
                                    admin_url('admin-post.php?action=calebo_eliminar_actividad&id=' . $act->id . '&calendario_id=' . $cal_id),
                                    'calebo_eliminar_actividad_' . $act->id
                                )); ?>"
                                   class="button button-small calebo-confirm-eliminar"
                                   data-confirm="<?php echo esc_attr( __( '¿Eliminar esta actividad?', 'calendar-booking' ) ); ?>"
                                   style="color:#c62828;border-color:#ef9a9a;">
                                    <?php esc_html_e('Eliminar', 'calendar-booking'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Renderiza la vista de edición con pestañas
     */
    private static function render_editar_con_pestanas($actividad, $cal_id, $tab_actual) {
        if (!$actividad) {
            echo '<div class="notice notice-error"><p>' . esc_html__('La actividad que intentas editar no existe o ha sido eliminada.', 'calendar-booking') . '</p></div>';
            echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $cal_id), 'calebo_actividades_page')) . '" class="button button-secondary">' . esc_html__('Volver al listado', 'calendar-booking') . '</a>';
            return;
        }

        $calendario = CALEBO_Database::get_calendario($cal_id);

        // Pestañas disponibles
        $pestanas = [
            'configuracion'    => ['label' => '📋 ' . __('Configuración', 'calendar-booking'), 'order' => 1],
            'festivos_vacaciones' => ['label' => '📅 ' . __('Festivos y Vacaciones', 'calendar-booking'), 'order' => 2],
            'avanzada'         => ['label' => '⚙️ ' . __('Configuración Avanzada', 'calendar-booking'), 'order' => 3],
        ];

        // Ordenar pestañas
        uasort($pestanas, function($a, $b) {
            return $a['order'] - $b['order'];
        });

        // Decodificar festivos y vacaciones
        $festivos   = json_decode($actividad->festivos ?? '[]', true) ?: [];
        $vacaciones = json_decode($actividad->vacaciones ?? '[]', true) ?: [];

        // Valores con fallback al calendario
        $horario_vacaciones_act      = $actividad->horario_vacaciones ?? $calendario->horario_vacaciones ?? '10:00-14:00';
        $usar_horario_vacaciones_act = !empty($actividad->usar_horario_vacaciones);
        $meses_max_act               = $actividad->meses_max ?? $calendario->meses_max ?? 3;
        $dia_activacion_act          = $actividad->dia_activacion ?? $calendario->dia_activacion ?? 0;
        $mes_inicio_ciclo_act        = $actividad->mes_inicio_ciclo ?? $calendario->mes_inicio_ciclo ?? 1;

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
            12 => __('Diciembre', 'calendar-booking'),
        ];
        ?>

        <!-- PESTAÑAS DE NAVEGACIÓN -->
        <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
            <?php foreach ($pestanas as $tab_key => $tab_data): ?>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $cal_id . '&editar=' . $actividad->id . '&tab=' . $tab_key), 'calebo_actividades_page')); ?>"
               class="nav-tab <?php echo esc_attr( $tab_actual === $tab_key ? 'nav-tab-active' : '' ); ?>">
                <?php echo esc_html($tab_data['label']); ?>
            </a>
            <?php endforeach; ?>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $cal_id), 'calebo_actividades_page')); ?>"
               class="nav-tab" style="margin-left:auto;">
                ← <?php esc_html_e('Volver al listado', 'calendar-booking'); ?>
            </a>
        </nav>

        <!-- INFO BAR -->
        <div style="background:#FFF7F0;border:1px solid #F7941D33;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
            <div>
                <strong>📅 <?php echo esc_html($actividad->nombre); ?></strong>
            </div>
            <div style="color:#888;">
                <?php echo esc_html(date_i18n('d/m/Y', strtotime($actividad->fecha_inicio))); ?>
                — <?php echo esc_html(date_i18n('d/m/Y', strtotime($actividad->fecha_fin))); ?>
            </div>
            <div style="color:#888;">
                🕐 <?php echo esc_html($actividad->horario); ?>
            </div>
            <?php if ($actividad->sala): ?>
            <div style="color:#888;">
                📍 <?php echo esc_html($actividad->sala); ?>
            </div>
            <?php endif; ?>
            <div style="color:#888;">
                👥 <?php
                /* translators: %d: número de plazas de la actividad. */
                echo esc_html( sprintf( __( '%d plazas', 'calendar-booking' ), (int) $actividad->limite ) );
                ?>
            </div>
        </div>

        <!-- CONTENIDO DE CADA PESTAÑA -->
        <?php if ($tab_actual === 'configuracion'): ?>
            <?php self::render_pestana_configuracion($actividad, $cal_id); ?>
        <?php elseif ($tab_actual === 'festivos_vacaciones'): ?>
            <?php self::render_pestana_festivos_vacaciones($actividad, $festivos, $vacaciones, $calendario); ?>
        <?php elseif ($tab_actual === 'avanzada'): ?>
            <?php self::render_pestana_avanzada($actividad, $calendario, $meses); ?>
        <?php endif; ?>

        <?php
    }

    /**
     * Pestaña: Configuración Básica
     */
    private static function render_pestana_configuracion($actividad, $cal_id) {
        $es_nueva = empty($actividad->id);
        ?>
        <div class="calebo-actividad-config-wrap">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('calebo_guardar_actividad'); ?>
                <input type="hidden" name="action" value="calebo_guardar_actividad">
                <input type="hidden" name="tab_origen" value="configuracion">
                <input type="hidden" name="calendario_id" value="<?php echo esc_attr($cal_id); ?>">
                <input type="hidden" name="id" value="<?php echo esc_attr($actividad->id); ?>">

                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px;margin-bottom:16px;font-size:13px;">
                    <strong>ℹ️ <?php esc_html_e('Información importante:', 'calendar-booking'); ?></strong>
                    <span style="color:#856404;"><?php esc_html_e('Los campos marcados con * son obligatorios. Después de guardar, podrás configurar el resto de opciones en las otras pestañas.', 'calendar-booking'); ?></span>
                </div>

                <table class="form-table">
                    <tr>
                        <th style="width:150px;"><?php esc_html_e('Nombre *', 'calendar-booking'); ?></th>
                        <td>
                            <input type="text" name="nombre" required class="regular-text"
                                   value="<?php echo esc_attr($actividad->nombre); ?>"
                                   placeholder="<?php esc_attr_e('Ej: Taller de manualidades', 'calendar-booking'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Fecha inicio *', 'calendar-booking'); ?></th>
                        <td>
                            <input type="date" name="fecha_inicio" required
                                   value="<?php echo esc_attr($actividad->fecha_inicio); ?>"
                                   style="width:200px;">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Fecha fin *', 'calendar-booking'); ?></th>
                        <td>
                            <input type="date" name="fecha_fin" required
                                   value="<?php echo esc_attr($actividad->fecha_fin); ?>"
                                   style="width:200px;">
                            <p class="description"><?php esc_html_e('La fecha de fin debe ser igual o posterior a la fecha de inicio.', 'calendar-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Horario *', 'calendar-booking'); ?></th>
                        <td>
                            <input type="text" name="horario" required
                                   value="<?php echo esc_attr($actividad->horario); ?>"
                                   placeholder="<?php esc_attr_e('Ej: 10:00-12:00', 'calendar-booking'); ?>"
                                   style="width:200px;">
                            <p class="description"><?php esc_html_e('Horario específico de esta actividad. Formato: HH:MM-HH:MM', 'calendar-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Sala / Ubicación', 'calendar-booking'); ?></th>
                        <td>
                            <input type="text" name="sala"
                                   value="<?php echo esc_attr($actividad->sala ?? ''); ?>"
                                   placeholder="<?php esc_attr_e('Ej: Sala A, Patio, Aula 1...', 'calendar-booking'); ?>"
                                   style="width:200px;">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Descripción', 'calendar-booking'); ?></th>
                        <td>
                            <textarea name="descripcion" rows="4" class="large-text"
                                      placeholder="<?php esc_attr_e('Descripción breve visible al usuario...', 'calendar-booking'); ?>"><?php echo esc_textarea($actividad->descripcion ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Límite de plazas', 'calendar-booking'); ?></th>
                        <td>
                            <input type="number" name="limite" min="1" max="999"
                                   value="<?php echo esc_attr($actividad->limite ?? 20); ?>"
                                   style="width:100px;">
                            <p class="description"><?php esc_html_e('Número máximo de participantes por sesión.', 'calendar-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Lista de espera', 'calendar-booking'); ?></th>
                        <td>
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <div class="calebo-toggle-wrap" style="position:relative;display:inline-block;width:48px;height:26px;">
                                    <input type="checkbox" name="lista_espera" id="calebo-toggle-espera" value="1"
                                           <?php checked(!empty($actividad->lista_espera)); ?>
                                           style="opacity:0;width:0;height:0;position:absolute;">
                                    <span class="calebo-toggle-slider" style="
                                        position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;
                                        background:<?php echo !empty($actividad->lista_espera) ? '#F7941D' : '#ccc'; ?>;
                                        border-radius:26px;transition:.3s;
                                    ">
                                        <span style="
                                            position:absolute;content:'';height:20px;width:20px;
                                            left:<?php echo !empty($actividad->lista_espera) ? '24px' : '3px'; ?>;
                                            bottom:3px;background:white;border-radius:50%;transition:.3s;
                                        "></span>
                                    </span>
                                </div>
                                <span style="font-size:13px;font-weight:600;">
                                    <?php esc_html_e('Activar lista de espera cuando la actividad esté completa', 'calendar-booking'); ?>
                                </span>
                            </label>
                            <p class="description" style="margin-top:6px;"><?php esc_html_e('Cuando esté activa, los usuarios podrán apuntarse a la lista de espera si no hay plazas disponibles. Se les notificará automáticamente si se libera una plaza.', 'calendar-booking'); ?></p>
                            <?php // Toggle script moved to assets/js/admin-actividades.js ?>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php echo esc_attr($es_nueva ? '💾 ' : '💾 ') . esc_attr($es_nueva ? __('Crear actividad', 'calendar-booking') : __('Guardar configuración', 'calendar-booking')); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Pestaña: Plazas y Límites
     */
    /**
     * Pestaña: Festivos y Vacaciones (unificada)
     */
    private static function render_pestana_festivos_vacaciones($actividad, $festivos, $vacaciones, $calendario) {
        $horario_vacaciones_act = $actividad->horario_vacaciones ?? $calendario->horario_vacaciones ?? '10:00-14:00';
        ?>
        <div class="calebo-actividad-festivos-vacaciones-wrap">

            <!-- SECCIÓN: DÍAS FESTIVOS -->
            <h3 style="margin:0 0 8px;font-size:14px;font-weight:600;">📅 <?php esc_html_e('Días Festivos', 'calendar-booking'); ?></h3>
            <p style="color:#888;font-size:13px;margin-bottom:16px;">
                <?php esc_html_e('Configura los días festivos específicos para esta actividad. Estos días no estarán disponibles para reserva.', 'calendar-booking'); ?>
            </p>

            <div id="calebo-festivos-actividad-container" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:32px;">
                <div id="calebo-festivos-list">
                    <?php foreach ($festivos as $f): ?>
                    <div class="calebo-festivo-item"
                         data-fecha="<?php echo esc_attr($f['fecha'] ?? ''); ?>"
                         data-label="<?php echo esc_attr($f['label'] ?? ''); ?>"
                         style="display:flex;align-items:center;gap:12px;padding:12px;background:#f9f9f9;border-radius:6px;margin-bottom:8px;">
                        <span style="font-size:18px;color:#888;">📅</span>
                        <span style="flex:1;font-weight:500;"><?php echo esc_html($f['label'] ?? ''); ?></span>
                        <span style="color:#888;font-size:13px;"><?php echo esc_html(date_i18n('d/m/Y', strtotime($f['fecha']))); ?></span>
                        <button type="button" class="button button-small calebo-remove-festivo"
                                style="color:#c62828;border-color:#ef9a9a;">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="calebo-festivo-nuevo" style="margin-top:16px;padding-top:16px;border-top:1px solid #e0e0e0;">
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <input type="text" id="festivo-label" placeholder="<?php esc_attr_e('Nombre del festivo (Ej: Navidad)', 'calendar-booking'); ?>"
                               style="width:200px;">
                        <input type="date" id="festivo-fecha" style="width:180px;">
                        <button type="button" id="calebo-add-festivo" class="button button-secondary">
                            + <?php esc_html_e('Añadir festivo', 'calendar-booking'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div id="calebo-festivos-guardando" style="display:none;margin-top:12px;color:#F7941D;">
                <?php esc_html_e('Guardando...', 'calendar-booking'); ?>
            </div>
            <div id="calebo-festivos-guardado" style="display:none;margin-top:12px;color:#28a745;">
                ✅ <?php esc_html_e('Festivos guardados correctamente.', 'calendar-booking'); ?>
            </div>

            <!-- SECCIÓN: VACACIONES -->
            <h3 style="margin:0 0 8px;font-size:14px;font-weight:600;">🏖️ <?php esc_html_e('Vacaciones', 'calendar-booking'); ?></h3>
            <p style="color:#888;font-size:13px;margin-bottom:16px;">
                <?php esc_html_e('Configura los períodos de vacaciones para esta actividad. Puedes elegir entre bloquear completamente el período o aplicar un horario especial.', 'calendar-booking'); ?>
            </p>

            <!-- Horario vacaciones -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 20px;margin-bottom:16px;">
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="width:180px;padding:8px 0;"><?php esc_html_e('Horario vacaciones', 'calendar-booking'); ?></th>
                        <td style="padding:8px 0;">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="form-horario-vacaciones">
                                <?php wp_nonce_field('calebo_guardar_actividad'); ?>
                                <input type="hidden" name="action" value="calebo_guardar_actividad">
                                <input type="hidden" name="tab_origen" value="festivos_vacaciones">
                                <input type="hidden" name="calendario_id" value="<?php echo esc_attr($calendario->id); ?>">
                                <input type="hidden" name="id" value="<?php echo esc_attr($actividad->id); ?>">
                                <input type="hidden" name="calebo_personalizar_config" value="1">
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <input type="text" name="horario_vacaciones"
                                           placeholder="<?php echo esc_attr($calendario->horario_vacaciones ?? '10:00-14:00'); ?>"
                                           value="<?php echo esc_attr($actividad->horario_vacaciones ?? ''); ?>"
                                           style="width:200px;">
                                    <input type="submit" class="button button-secondary" value="<?php esc_attr_e('💾 Guardar', 'calendar-booking'); ?>">
                                </div>
                                <p class="description" style="margin-top:6px;"><?php esc_html_e('Horario especial para períodos vacacionales (ej: 10:00-14:00).', 'calendar-booking'); ?></p>
                            </form>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="calebo-vacaciones-actividad-container" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;">
                <div id="calebo-vacaciones-list">
                    <?php foreach ($vacaciones as $v): 
                        $tipo = $v['tipo'] ?? 'bloqueado';
                    ?>
                    <div class="calebo-vacaciones-item"
                         data-inicio="<?php echo esc_attr($v['inicio'] ?? ''); ?>"
                         data-fin="<?php echo esc_attr($v['fin'] ?? ''); ?>"
                         data-label="<?php echo esc_attr($v['label'] ?? ''); ?>"
                         data-tipo="<?php echo esc_attr($tipo); ?>"
                         style="display:flex;align-items:center;gap:12px;padding:12px;background:#f9f9f9;border-radius:6px;margin-bottom:8px;flex-wrap:wrap;">
                        <span style="font-size:18px;color:#888;">🏖️</span>
                        <span style="flex:1;font-weight:500;min-width:120px;"><?php echo esc_html($v['label'] ?? ''); ?></span>
                        <span style="color:#888;font-size:12px;">
                            <?php echo esc_html(date_i18n('d/m/Y', strtotime($v['inicio']))); ?>
                            — <?php echo esc_html(date_i18n('d/m/Y', strtotime($v['fin']))); ?>
                        </span>
                        <span style="font-size:11px;padding:2px 8px;background:<?php echo esc_attr( $tipo === 'bloqueado' ? '#ffebee' : '#FFF3E0' ); ?>;color:<?php echo esc_attr( $tipo === 'bloqueado' ? '#c62828' : '#F7941D' ); ?>;border-radius:4px;">
                            <?php echo $tipo === 'bloqueado' ? '🚫 ' . esc_html__( 'Bloqueado', 'calendar-booking' ) : '🕐 ' . esc_html__( 'Horario especial', 'calendar-booking' ); ?>
                        </span>
                        <button type="button" class="button button-small calebo-remove-vacaciones"
                                style="color:#c62828;border-color:#ef9a9a;">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="calebo-vacaciones-nuevo" style="margin-top:16px;padding-top:16px;border-top:1px solid #e0e0e0;">
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <input type="text" id="vacaciones-label" placeholder="<?php esc_attr_e('Ej: Semana Santa', 'calendar-booking'); ?>"
                               style="width:160px;">
                        <label style="font-size:12px;"><?php esc_html_e('Desde', 'calendar-booking'); ?></label>
                        <input type="date" id="vacaciones-inicio" style="width:150px;">
                        <label style="font-size:12px;"><?php esc_html_e('Hasta', 'calendar-booking'); ?></label>
                        <input type="date" id="vacaciones-fin" style="width:150px;">
                        <select id="vacaciones-tipo" style="font-size:13px;">
                            <option value="bloqueado"><?php esc_html_e('🚫 Bloqueado', 'calendar-booking'); ?></option>
                            <option value="horario_especial"><?php esc_html_e('🕐 Horario especial', 'calendar-booking'); ?></option>
                        </select>
                        <button type="button" id="calebo-add-vacaciones" class="button button-secondary">
                            + <?php esc_html_e('Añadir período', 'calendar-booking'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div id="calebo-vacaciones-guardando" style="display:none;margin-top:12px;color:#F7941D;">
                <?php esc_html_e('Guardando...', 'calendar-booking'); ?>
            </div>
            <div id="calebo-vacaciones-guardado" style="display:none;margin-top:12px;color:#28a745;">
                ✅ <?php esc_html_e('Vacaciones guardadas correctamente.', 'calendar-booking'); ?>
            </div>
        </div>

<?php
        // Festivos/vacaciones: JS externalizado a assets/js/admin-actividades.js (se encola con admin_enqueue_scripts).
        // Aquí solo se inyecta el objeto de datos dinámicos ANTES del script externo (posición 'before').
        wp_add_inline_script(
            'calebo-admin-actividades',
            'var caleboActividadData = ' . wp_json_encode( [
                'actividadId' => intval( $actividad->id ),
                'nonceF'      => wp_create_nonce( 'calebo_festivos_actividad' ),
                'nonceV'      => wp_create_nonce( 'calebo_vacaciones_actividad' ),
                'strings'     => [
                    'rellenaNombreFecha'   => __( 'Rellena el nombre y la fecha del festivo.', 'calendar-booking' ),
                    'rellenaCamposVac'     => __( 'Rellena todos los campos del período de vacaciones.', 'calendar-booking' ),
                    'fechaInicioAnterior'  => __( 'La fecha de inicio debe ser anterior a la de fin.', 'calendar-booking' ),
                    'bloqueado'            => __( 'Bloqueado', 'calendar-booking' ),
                    'horarioEspecial'      => __( 'Horario especial', 'calendar-booking' ),
                ],
            ] ) . ';',
            'before'
        );
        ?>
        <?php
    }

    /**
     * Pestaña: Configuración Avanzada
     */
    private static function render_pestana_avanzada($actividad, $calendario, $meses) {
        $tiene_overrides = (
            ($actividad->meses_max ?? null) !== null ||
            ($actividad->dia_activacion ?? null) !== null ||
            ($actividad->mes_inicio_ciclo ?? null) !== null
        );

        // ── Calcular ventana de reserva activa ──────────────────────────
        $ventana = CALEBO_Booking::obtener_ventana_reserva($actividad, $calendario);

        $ventana_html = '';
        if ($ventana) {
            $meses_max_ef  = $ventana['efectivos']['meses_max'];
            $dia_activ_ef  = $ventana['efectivos']['dia_activacion'];
            $mes_inicio_ef = $ventana['efectivos']['mes_inicio'];

            $anio_ini_ventana = $ventana['anio_ini'];
            $mes_ini_ventana  = $ventana['mes_ini'];
            $anio_fin_ventana = $ventana['anio_fin'];
            $mes_fin_ventana  = $ventana['mes_fin'];

            $prox_anio = $ventana['prox_anio'];
            $prox_mes  = $ventana['prox_mes'];

            $nombre_mes_ini = $meses[$mes_ini_ventana] ?? $mes_ini_ventana;
            $nombre_mes_fin = $meses[$mes_fin_ventana] ?? $mes_fin_ventana;
            $nombre_prox    = $meses[$prox_mes] ?? $prox_mes;

            $src_meses_max  = ($actividad->meses_max ?? null) !== null
                ? '<span style="color:#28a745;font-size:11px;"> ✓ personalizado</span>'
                : '<span style="color:#888;font-size:11px;"> (heredado del calendario)</span>';
            $src_mes_inicio = ($actividad->mes_inicio_ciclo ?? null) !== null
                ? '<span style="color:#28a745;font-size:11px;"> ✓ personalizado</span>'
                : '<span style="color:#888;font-size:11px;"> (heredado del calendario)</span>';

            $prox_info = $dia_activ_ef > 0
                ? sprintf(
                    /* translators: %d: día de activación del ciclo. */
                    __('El siguiente ciclo abre el día <strong>%d</strong> del mes en que corresponda.', 'calendar-booking'),
                    $dia_activ_ef
                  )
                : __('Sin activación automática — el siguiente ciclo empieza cuando el admin lo configure.', 'calendar-booking');

            ob_start();
            ?>
            <div style="background:#E8F5E9;border:1px solid #A5D6A7;border-radius:8px;padding:16px 20px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <span style="font-size:18px;">📅</span>
                    <strong style="font-size:14px;color:#1B5E20;"><?php esc_html_e('Ventana de reserva activa ahora mismo', 'calendar-booking'); ?></strong>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;font-size:13px;">
                    <div style="background:#fff;border-radius:6px;padding:10px 14px;">
                        <div style="color:#888;font-size:11px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><?php esc_html_e('Ventana actual', 'calendar-booking'); ?></div>
                        <strong style="font-size:15px;color:#1B5E20;">
                            <?php echo esc_html($nombre_mes_ini . ' ' . $anio_ini_ventana); ?>
                            &nbsp;→&nbsp;
                            <?php echo esc_html($nombre_mes_fin . ' ' . $anio_fin_ventana); ?>
                        </strong>
                    </div>
                    <div style="background:#fff;border-radius:6px;padding:10px 14px;">
                        <div style="color:#888;font-size:11px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><?php esc_html_e('Meses por ciclo', 'calendar-booking'); ?></div>
                        <strong><?php echo esc_html($meses_max_ef); ?> <?php esc_html_e('meses', 'calendar-booking'); ?></strong>
                        <?php echo wp_kses( $src_meses_max, [ 'span' => [ 'style' => [] ] ] ); ?>
                    </div>
                    <div style="background:#fff;border-radius:6px;padding:10px 14px;">
                        <div style="color:#888;font-size:11px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><?php esc_html_e('Inicio de ciclo', 'calendar-booking'); ?></div>
                        <strong><?php echo esc_html($meses[$mes_inicio_ef] ?? $mes_inicio_ef); ?></strong>
                        <?php echo wp_kses( $src_mes_inicio, [ 'span' => [ 'style' => [] ] ] ); ?>
                    </div>
                    <div style="background:#fff;border-radius:6px;padding:10px 14px;">
                        <div style="color:#888;font-size:11px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><?php esc_html_e('Próximo ciclo', 'calendar-booking'); ?></div>
                        <strong><?php echo esc_html($nombre_prox . ' ' . $prox_anio); ?></strong>
                        <div style="color:#888;font-size:11px;margin-top:4px;"><?php echo wp_kses($prox_info, ['strong' => []]); ?></div>
                    </div>
                </div>
            </div>
            <?php
            $ventana_html = ob_get_clean();
        }
        ?>
        <div class="calebo-actividad-avanzada-wrap">
            <?php echo $ventana_html; // phpcs:ignore WordPress.Security.EscapeOutput -- already escaped via esc_html/ob ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('calebo_guardar_actividad'); ?>
                <input type="hidden" name="action" value="calebo_guardar_actividad">
                <input type="hidden" name="tab_origen" value="avanzada">
                <input type="hidden" name="calendario_id" value="<?php echo esc_attr($calendario->id); ?>">
                <input type="hidden" name="id" value="<?php echo esc_attr($actividad->id); ?>">

                <table class="form-table">
                    <tr>
                        <td colspan="2" style="padding:8px 0 16px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;font-size:13px;">
                                <input type="checkbox" id="calebo-toggle-config" name="calebo_personalizar_config"
                                       <?php echo $tiene_overrides ? 'checked' : ''; ?>>
                                ⚙️ <?php esc_html_e('Personalizar configuración avanzada para esta actividad', 'calendar-booking'); ?>
                            </label>
                            <p style="font-size:12px;color:#888;margin:8px 0 0 26px;">
                                <?php esc_html_e('Si no se personaliza, se usarán los valores del calendario.', 'calendar-booking'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div id="calebo-config-avanzada" style="<?php echo esc_attr( $tiene_overrides ? '' : 'display:none;' ); ?>background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:20px;">
                    <table class="form-table">
                        <tr>
                            <th style="width:180px;"><?php esc_html_e('Meses máx. reserva', 'calendar-booking'); ?></th>
                            <td>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <input type="number" name="meses_max" min="1" max="12"
                                           placeholder="<?php echo esc_attr($calendario->meses_max ?? 3); ?>"
                                           value="<?php echo esc_attr(($actividad->meses_max ?? null) !== null ? $actividad->meses_max : ''); ?>"
                                           style="width:80px;">
                                    <span style="color:#888;font-size:12px;"><?php esc_html_e('meses', 'calendar-booking'); ?></span>
                                </div>
                                <p class="description"><?php esc_html_e('Número de meses anticipados que se pueden reservar.', 'calendar-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Día activación ciclo', 'calendar-booking'); ?></th>
                            <td>
                                <input type="number" name="dia_activacion" min="0" max="28"
                                       placeholder="<?php echo esc_attr($calendario->dia_activacion ?? 0); ?>"
                                       value="<?php echo esc_attr(($actividad->dia_activacion ?? null) !== null ? $actividad->dia_activacion : ''); ?>"
                                       style="width:80px;">
                                <p class="description"><?php esc_html_e('Día del mes para activar el siguiente mes. 0 = desactivado.', 'calendar-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Mes inicio ciclo', 'calendar-booking'); ?></th>
                            <td>
                                <select name="mes_inicio_ciclo">
                                    <option value=""><?php esc_html_e('— Heredar del calendario —', 'calendar-booking'); ?></option>
                                    <?php foreach ($meses as $num => $nombre_mes): ?>
                                    <option value="<?php echo esc_attr($num); ?>"
                                            <?php selected(($actividad->mes_inicio_ciclo ?? ''), $num); ?>>
                                        <?php echo esc_html($nombre_mes); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Mes en el que comienza el ciclo anual.', 'calendar-booking'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('💾 Guardar configuración avanzada', 'calendar-booking'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Invalida la caché de disponibilidad de todos los meses futuros de un calendario.
     * Se llama cada vez que se modifica una actividad, sus festivos o vacaciones.
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

    /**
     * Formulario de creación de nueva actividad.
     * Se muestra cuando tab=editar pero no hay editar=ID en la URL.
     */
    private static function render_nueva_actividad($cal_id) {
        $actividad = (object)[
            'id'                    => 0,
            'nombre'                => '',
            'fecha_inicio'          => '',
            'fecha_fin'             => '',
            'horario'               => '',
            'sala'                  => '',
            'descripcion'           => '',
            'limite'                => 20,
            'lista_espera'          => 0,
            'horario_vacaciones'    => '',
            'usar_horario_vacaciones' => 0,
            'meses_max'             => null,
            'dia_activacion'        => null,
            'mes_inicio_ciclo'      => null,
        ];
        ?>
        <div class="calebo-actividad-nueva-wrap">
            <h2 style="display:flex;align-items:center;gap:10px;">
                <?php esc_html_e('Nueva actividad', 'calendar-booking'); ?>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $cal_id), 'calebo_actividades_page')); ?>"
                   class="button button-secondary" style="font-size:13px;">
                    ← <?php esc_html_e('Volver al listado', 'calendar-booking'); ?>
                </a>
            </h2>
            <?php self::render_pestana_configuracion($actividad, $cal_id); ?>
        </div>
        <?php
    }
}
