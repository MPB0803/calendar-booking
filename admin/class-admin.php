<?php
if (!defined('ABSPATH')) exit;

class CALEBO_Admin {

    public static function init() {
        add_action('admin_menu',            [__CLASS__, 'registrar_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_calebo_orden_campos', [__CLASS__, 'ajax_orden_campos']);
        // Process form actions before any output is sent to avoid "headers already sent" errors.
        add_action('admin_init', [__CLASS__, 'procesar_acciones_reservas']);
    }

    /**
     * Process redirect-producing actions for the reservations page.
     * Must run on admin_init (before any output) to allow wp_safe_redirect() to work.
     */
    public static function procesar_acciones_reservas() {
        // Only act on our own admin page.
        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        if ($page !== 'calebo-reservas') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $action = sanitize_key(wp_unslash($_GET['action'] ?? ''));

        // ── Acción: eliminar todas las reservas (y espera) de una actividad ──
        if ($action === 'eliminar_actividad_reservas' && isset($_GET['actividad_id'])) {
            $act_id = intval(wp_unslash($_GET['actividad_id']));
            check_admin_referer('calebo_eliminar_actividad_reservas_' . $act_id);
            if ($act_id > 0) {
                // Recoger fechas/calendarios antes de borrar para invalidar caché
                $filas = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT fecha, calendario_id FROM {$wpdb->prefix}calebo_reservas WHERE actividad_id = %d",
                    $act_id
                ));
                // Eliminar todas las reservas de esta actividad (cualquier estado)
                $wpdb->delete("{$wpdb->prefix}calebo_reservas", ['actividad_id' => $act_id]);
                // Eliminar también la lista de espera de esta actividad
                $wpdb->delete("{$wpdb->prefix}calebo_espera", ['actividad_id' => $act_id]);
                // Invalidar caché de disponibilidad
                foreach ($filas as $row) {
                    $mes_r  = intval(gmdate('n', strtotime($row->fecha)));
                    $anio_r = intval(gmdate('Y', strtotime($row->fecha)));
                    delete_transient('calebo_disp_' . $row->calendario_id . '_' . $mes_r . '_' . $anio_r);
                }
                wp_safe_redirect(esc_url_raw(wp_nonce_url(admin_url('admin.php?page=calebo-reservas&calebo_tab=todas&eliminadas=1'), 'calebo_reservas_page')));
                exit;
            }
        }

        // ── Acción: eliminar entrada de lista de espera ──
        if ($action === 'eliminar_espera' && isset($_GET['espera_id'])) {
            $espera_id = intval(wp_unslash($_GET['espera_id']));
            check_admin_referer('calebo_eliminar_espera_' . $espera_id);
            if ($espera_id > 0) {
                CALEBO_Database::eliminar_entrada_espera($espera_id);
                $back_tab = sanitize_key(wp_unslash($_GET['calebo_tab'] ?? 'todas'));
                wp_safe_redirect(esc_url_raw(wp_nonce_url(admin_url('admin.php?page=calebo-reservas&calebo_tab=' . $back_tab), 'calebo_reservas_page')));
                exit;
            }
        }
    }

    /**
     * AJAX: reordenar campos_extra de una actividad.
     */
    public static function ajax_orden_campos() {
        $post   = wp_unslash($_POST);
        $act_id = intval($post['actividad_id'] ?? 0);

        if (!$act_id || !current_user_can('manage_options')) {
            wp_send_json_error();
        }

        check_ajax_referer('calebo_orden_campos_actividad_' . $act_id);

        $actividad = CALEBO_Database::get_actividad($act_id);
        if (!$actividad) wp_send_json_error();

        $campos_act_arr = [];
        if (!empty($actividad->campos_extra)) {
            $campos_act_arr = json_decode($actividad->campos_extra, true) ?: [];
        }

        $new_order = array_map('intval', $post['indices'] ?? []);
        $reordered = [];
        foreach ($new_order as $i) {
            if (isset($campos_act_arr[$i])) {
                $reordered[] = $campos_act_arr[$i];
            }
        }

        CALEBO_Database::actualizar_campos_actividad($act_id, wp_json_encode($reordered));
        wp_send_json_success();
    }

    public static function registrar_menu() {
        // Menú principal - Dashboard
        add_menu_page(
            'CalendiFlow', 'CalendiFlow',
            'manage_options', 'calendar-booking',
            [__CLASS__, 'pagina_principal'],
            'dashicons-calendar-alt', 30
        );

        //  CALENDARIO (solo configuración básica)
        add_submenu_page(
            'calendar-booking',
            __('Calendario', 'calendar-booking'),
            __('Calendario', 'calendar-booking'),
            'manage_options',
            'calebo-calendarios',
            ['CALEBO_Settings', 'pagina_calendarios']
        );

        //  ACTIVIDADES (gestión completa con pestañas)
        add_submenu_page(
            'calendar-booking',
            __('Actividades', 'calendar-booking'),
            __('Actividades', 'calendar-booking'),
            'manage_options',
            'calebo-actividades',
            ['CALEBO_Actividades', 'pagina']
        );

        //  CAMPOS (configuración separada de campos para actividades)
        add_submenu_page(
            'calendar-booking',
            __('Campos', 'calendar-booking'),
            __('Campos', 'calendar-booking'),
            'manage_options',
            'calebo-campos',
            ['CALEBO_Settings', 'pagina_campos']
        );

        //  RESERVAS
        add_submenu_page(
            'calendar-booking',
            __('Reservas', 'calendar-booking'),
            __('Reservas', 'calendar-booking'),
            'manage_options',
            'calebo-reservas',
            [__CLASS__, 'pagina_reservas']
        );

        //  INFORMES
        add_submenu_page(
            'calendar-booking',
            __('Informes', 'calendar-booking'),
            __('Informes', 'calendar-booking'),
            'manage_options',
            'calebo-informes',
            [__CLASS__, 'pagina_informes']
        );

        //  DISEÑO
        add_submenu_page(
            'calendar-booking',
            __('Diseño', 'calendar-booking'),
            __('Diseño', 'calendar-booking'),
            'manage_options',
            'calebo-diseno',
            ['CALEBO_Settings', 'pagina_diseno']
        );

        //  CORREO SMTP
        add_submenu_page(
            'calendar-booking',
            __('Correo', 'calendar-booking'),
            __('Correo', 'calendar-booking'),
            'manage_options',
            'calebo-smtp',
            ['CALEBO_SMTP', 'pagina_smtp']
        );
    }

    public static function enqueue_assets($hook) {
        if (strpos($hook, 'calendar-booking') === false &&
            strpos($hook, '_page_calebo-') === false) return;
        wp_enqueue_style('calebo-admin', CALEBO_PLUGIN_URL . 'assets/css/admin.css', [], CALEBO_VERSION);
        wp_register_script( 'calebo-admin', false, [ 'jquery' ], CALEBO_VERSION, true );
        wp_enqueue_script( 'calebo-admin' );

        // Scripts JS externalizados (obligatorio wp_enqueue para pasar el escáner de WP.org).
        // Cada página carga su propio fichero; los datos dinámicos se inyectan con
        // wp_add_inline_script(posición 'before') desde el template que la renderiza.
        if ( 'toplevel_page_calendar-booking' === $hook ) {
            wp_enqueue_script('calebo-admin-dashboard', CALEBO_PLUGIN_URL . 'assets/js/admin-dashboard.js', [], CALEBO_VERSION, true);
        }
        if ( false !== strpos( $hook, '_page_calebo-actividades' ) ) {
            wp_enqueue_script('calebo-admin-actividades', CALEBO_PLUGIN_URL . 'assets/js/admin-actividades.js', [], CALEBO_VERSION, true);
        }
        if ( false !== strpos( $hook, '_page_calebo-diseno' ) ) {
            wp_enqueue_script('calebo-admin-diseno', CALEBO_PLUGIN_URL . 'assets/js/admin-diseno.js', [], CALEBO_VERSION, true);
        }
        if ( false !== strpos( $hook, '_page_calebo-smtp' ) ) {
            wp_enqueue_script('calebo-admin-smtp', CALEBO_PLUGIN_URL . 'assets/js/admin-smtp.js', [], CALEBO_VERSION, true);
        }

        // Inyectar @font-face de fuentes locales para que la preview del diseño funcione
        $fuentes_locales = [
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
        // FIX 5: Usar helper centralizado en lugar de duplicar la lógica @font-face
        $css_admin_fonts = '';
        foreach ( $fuentes_locales as $nombre_fuente => $pesos ) {
            $css_admin_fonts .= CALEBO_Database::get_font_face_css($nombre_fuente, $pesos);
        }
        wp_add_inline_style( 'calebo-admin', $css_admin_fonts );
    }

    public static function pagina_principal() {
        global $wpdb;

        $calendarios = CALEBO_Database::get_calendarios();
        $total_calendarios = count($calendarios);
        $hoy = gmdate('Y-m-d');

        // Reservas de hoy
        $reservas_hoy = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(num_menores), 0) FROM {$wpdb->prefix}calebo_reservas WHERE fecha = %s AND estado = 'activa'",
            $hoy
        ));

        // Reservas esta semana
        $lunes = gmdate('Y-m-d', strtotime('monday this week'));
        $domingo = gmdate('Y-m-d', strtotime('sunday this week'));
        $reservas_semana = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(num_menores), 0) FROM {$wpdb->prefix}calebo_reservas WHERE fecha BETWEEN %s AND %s AND estado = 'activa'",
            $lunes, $domingo
        ));

        // Total en lista de espera
        $total_espera = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}calebo_espera WHERE notificado = 0"
        );

        // Próximas 5 reservas
        $proximas_reservas = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, a.nombre AS actividad_nombre
             FROM {$wpdb->prefix}calebo_reservas r
             LEFT JOIN {$wpdb->prefix}calebo_actividades a ON r.actividad_id = a.id
             WHERE r.fecha >= %s AND r.estado = 'activa'
             ORDER BY r.fecha ASC, r.id ASC
             LIMIT 5",
            $hoy
        ));

        // Alertas de capacidad (actividades al >=90%) — optimizado: una sola query agrupada
        $alertas_capacidad = [];
        $dias_adelante = gmdate('Y-m-d', strtotime('+7 days'));
        $actividades_proximas = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.nombre, a.limite, a.fecha_inicio, a.fecha_fin, a.calendario_id
             FROM {$wpdb->prefix}calebo_actividades a
             WHERE a.activo = 1 AND a.fecha_fin >= %s AND a.fecha_inicio <= %s",
            $hoy, $dias_adelante
        ));

        if (!empty($actividades_proximas)) {
            $act_ids = array_map('intval', wp_list_pluck($actividades_proximas, 'id'));
            $act_ids_str = implode(',', $act_ids);
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $act_ids_str es una cadena de enteros separados por comas, cada uno casteda con intval().
            $ocupacion_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT actividad_id, fecha, COALESCE(SUM(num_menores), 0) AS total
                 FROM {$wpdb->prefix}calebo_reservas
                 WHERE fecha BETWEEN %s AND %s AND estado = 'activa' AND actividad_id IN ({$act_ids_str})
                 GROUP BY actividad_id, fecha",
                $hoy, $dias_adelante
            ));

            $ocupacion_map = [];
            foreach ($ocupacion_raw as $row) {
                $ocupacion_map[(int) $row->actividad_id][$row->fecha] = (int) $row->total;
            }

            foreach ($actividades_proximas as $act) {
                $start = max($hoy, $act->fecha_inicio);
                $end   = min($dias_adelante, $act->fecha_fin);
                $current = $start;
                while ($current <= $end) {
                    $ocupadas = $ocupacion_map[(int) $act->id][$current] ?? 0;
                    if ($act->limite > 0) {
                        $porcentaje = round(($ocupadas / $act->limite) * 100);
                        if ($porcentaje >= 90) {
                            $alertas_capacidad[] = [
                                'actividad'  => $act->nombre,
                                'fecha'      => date_i18n('d/m/Y', strtotime($current)),
                                'ocupadas'   => $ocupadas,
                                'limite'     => $act->limite,
                                'porcentaje' => $porcentaje,
                            ];
                        }
                    }
                    $current = gmdate('Y-m-d', strtotime($current . ' +1 day'));
                }
            }
        }

        include CALEBO_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    public static function pagina_reservas() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes para acceder a esta página.', 'calendar-booking'));
        }
        // Nonce: obligatorio cuando hay parámetros de navegación/acción. El menú admin
        // (URL plana sin parámetros) no requiere nonce.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Entrada verificada con check_admin_referer inmediatamente después.
        if ( isset( $_GET['action'] ) || isset( $_GET['eliminadas'] ) || isset( $_GET['calebo_tab'] ) || isset( $_GET['paged'] ) ) {
            check_admin_referer('calebo_reservas_page');
        }
        // phpcs:enable
        global $wpdb;

        $action = sanitize_key(wp_unslash($_GET['action'] ?? ''));

        // ── Avisos de acciones procesadas en admin_init ──
        // (Las acciones con redirección se procesan antes de cualquier output
        //  para evitar el error "headers already sent".)
        if (isset($_GET['eliminadas']) && $_GET['eliminadas'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Todas las reservas de esa actividad han sido eliminadas correctamente.', 'calendar-booking') . '</p></div>';
        }

        // ── Acción: cancelar reserva ──
        if ($action === 'cancelar' && isset($_GET['id'])) {
            $id = intval(wp_unslash($_GET['id']));
            check_admin_referer('calebo_cancelar_reserva_' . $id);
            $reserva = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}calebo_reservas WHERE id = %d AND estado = 'activa'", $id));
            if ($reserva && current_user_can('manage_options')) {
                $wpdb->update("{$wpdb->prefix}calebo_reservas", ['estado' => 'cancelada'], ['id' => $id]);
                // Invalidar caché de disponibilidad
                $mes_r  = intval(gmdate('n', strtotime($reserva->fecha)));
                $anio_r = intval(gmdate('Y', strtotime($reserva->fecha)));
                delete_transient('calebo_disp_' . $reserva->calendario_id . '_' . $mes_r . '_' . $anio_r);
                if (class_exists('CALEBO_Notifications')) {
                    CALEBO_Notifications::enviar_cancelacion_cliente($reserva, true);
                }
                // FIX 4: Notificar lista de espera al cancelar desde el panel de admin
                if (!empty($reserva->actividad_id)) {
                    CALEBO_Database::procesar_espera_automatica($reserva);
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Reserva cancelada y cliente notificado por correo.', 'calendar-booking') . '</p></div>';
            }
        }

        // ── Obtener actividades con reservas activas ──
        $actividades_con_reservas = $wpdb->get_results(
            "SELECT a.id, a.nombre, a.campos_extra, COUNT(r.id) as total_reservas
             FROM {$wpdb->prefix}calebo_actividades a
             LEFT JOIN {$wpdb->prefix}calebo_reservas r ON r.actividad_id = a.id AND r.estado = 'activa'
             WHERE a.activo = 1
             GROUP BY a.id, a.nombre, a.campos_extra
             ORDER BY a.nombre ASC"
        );

        // Total de reservas sin actividad asignada (legacy)
        $total_sin_actividad = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}calebo_reservas WHERE estado = 'activa' AND (actividad_id IS NULL OR actividad_id = 0)"
        );

        // Tab activo
        $tab_activo = sanitize_key(wp_unslash($_GET['calebo_tab'] ?? 'todas'));

        // Paginación
        $por_pagina = 20;
        $pagina     = max(1, intval(wp_unslash($_GET['paged'] ?? 1)));
        $offset     = ($pagina - 1) * $por_pagina;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_tab contiene únicamente fragmentos SQL literales y un valor preparado con %d;_PHPCS no puede analizar el patrón de construcción incremental.
        // ── Construir query según tab ──
        $where_tab = "r.estado = 'activa'";
        if ($tab_activo === 'todas') {
            // Sin filtro extra
        } elseif ($tab_activo === 'sin_actividad') {
            $where_tab .= " AND (r.actividad_id IS NULL OR r.actividad_id = 0)";
        } else {
            $act_id_tab = intval($tab_activo);
            if ($act_id_tab > 0) {
                $where_tab .= $wpdb->prepare(" AND r.actividad_id = %d", $act_id_tab);
            }
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_tab contiene únicamente fragmentos SQL literales y un valor ya preparado con %d; _PHPCS no puede analizar el patrón de construcción incremental.
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}calebo_reservas r WHERE {$where_tab}");
        $num_paginas = ceil($total / $por_pagina);

        $reservas = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, c.nombre as calendario_nombre, a.nombre as actividad_nombre
             FROM {$wpdb->prefix}calebo_reservas r
             LEFT JOIN {$wpdb->prefix}calebo_calendarios c ON r.calendario_id = c.id
             LEFT JOIN {$wpdb->prefix}calebo_actividades a ON r.actividad_id = a.id
             WHERE {$where_tab}
             ORDER BY r.fecha ASC, r.id ASC
             LIMIT %d OFFSET %d",
            $por_pagina,
            $offset
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        // ── Determinar columnas dinámicas del tab activo ──
        $campos_actividad = [];
        if ($tab_activo !== 'todas' && $tab_activo !== 'sin_actividad') {
            $act_id_tab = intval($tab_activo);
            foreach ($actividades_con_reservas as $act) {
                if ((int) $act->id === $act_id_tab && !empty($act->campos_extra)) {
                    $campos_actividad = json_decode($act->campos_extra, true) ?: [];
                    break;
                }
            }
        }

        // Columnas fijas que se excluyen de datos_extra para no duplicar
        $campos_fijos = ['nombre', 'email', 'telefono', 'num_menores', 'edades', 'actividad', 'horario'];

        // Total general para tab "Todas"
        $total_general = 0;
        foreach ($actividades_con_reservas as $act) {
            $total_general += (int) $act->total_reservas;
        }
        $total_general += $total_sin_actividad;

        // ── Base URL para tabs (sin paged ni calebo_tab) con nonce de página ──
        $base_url  = admin_url('admin.php?page=calebo-reservas');
        $nonce_pag = wp_create_nonce('calebo_reservas_page');
        $url_base  = wp_nonce_url($base_url, 'calebo_reservas_page');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Reservas activas', 'calendar-booking'); ?></h1>
            <hr class="wp-header-end">

            <!-- ── TABS ── -->
            <nav class="nav-tab-wrapper calebo-reservas-tabs" style="margin-bottom:0;">
                <a href="<?php echo esc_url($url_base . '&calebo_tab=todas'); ?>"
                   class="nav-tab <?php echo esc_attr( $tab_activo === 'todas' ? 'nav-tab-active' : '' ); ?>">
                    <?php
                    /* translators: %s: número total de reservas. */
                    printf( esc_html__( 'Todas (%s)', 'calendar-booking' ), esc_html( number_format_i18n( $total_general ) ) );
                    ?>
                </a>
                <?php foreach ($actividades_con_reservas as $act): ?>
                    <span class="calebo-tab-group" style="display:inline-flex;align-items:center;position:relative;">
                        <a href="<?php echo esc_url($url_base . '&calebo_tab=' . $act->id); ?>"
                           class="nav-tab <?php echo esc_attr( $tab_activo === (string) $act->id ? 'nav-tab-active' : '' ); ?>"
                           style="padding-right:30px;">
                            <?php echo esc_html($act->nombre); ?>
                            <span class="calebo-tab-count"><?php echo esc_html($act->total_reservas); ?></span>
                        </a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-reservas&calebo_tab=' . $act->id . '&action=eliminar_actividad_reservas&actividad_id=' . $act->id), 'calebo_eliminar_actividad_reservas_' . $act->id)); ?>"
                           class="calebo-tab-delete"
                           title="<?php esc_attr_e('Eliminar todas las reservas de esta actividad', 'calendar-booking'); ?>"
                           onclick="return confirm('<?php
                           /* translators: %s: nombre de la actividad. */
                           printf(esc_attr__('¿Seguro que quieres ELIMINAR PERMANENTEMENTE todas las reservas de "%s"?\n\nEsto borrará también la lista de espera. Esta acción no se puede deshacer.', 'calendar-booking'), esc_attr($act->nombre)); ?>')">
                            &times;
                        </a>
                    </span>
                <?php endforeach; ?>
                <?php if ($total_sin_actividad > 0): ?>
                    <a href="<?php echo esc_url($url_base . '&calebo_tab=sin_actividad'); ?>"
                       class="nav-tab <?php echo esc_attr( $tab_activo === 'sin_actividad' ? 'nav-tab-active' : '' ); ?>">
                        <?php
                        /* translators: %s: número de reservas sin actividad. */
                        printf( esc_html__( 'Sin actividad (%s)', 'calendar-booking' ), esc_html( number_format_i18n( $total_sin_actividad ) ) );
                        ?>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- ── TABLA ── -->
            <div class="calebo-table-scroll"><table class="wp-list-table widefat striped calebo-reservas-table">
                <thead>
                    <tr>
                        <?php if ($tab_activo === 'todas'): ?>
                            <th><?php esc_html_e('Actividad', 'calendar-booking'); ?></th>
                        <?php endif; ?>
                        <th><?php esc_html_e('Fecha', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Horario', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Nombre', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Email', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Teléfono', 'calendar-booking'); ?></th>
                        <?php if ($tab_activo === 'todas' || $tab_activo === 'sin_actividad'): ?>
                            <!-- Columnas genéricas -->
                            <th><?php esc_html_e('Menores', 'calendar-booking'); ?></th>
                            <th><?php esc_html_e('Edades', 'calendar-booking'); ?></th>
                        <?php else: ?>
                            <!-- Columnas dinámicas de la actividad -->
                            <?php foreach ($campos_actividad as $campo):
                                $campo_nombre = $campo['nombre'] ?? '';
                                if (in_array(strtolower($campo_nombre), $campos_fijos, true)) continue;
                            ?>
                                <th><?php echo esc_html($campo['etiqueta'] ?? $campo_nombre); ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <th class="column-acciones"><?php esc_html_e('Acciones', 'calendar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reservas)): ?>
                        <tr><td colspan="20"><?php esc_html_e('No hay reservas activas en esta sección.', 'calendar-booking'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($reservas as $r):
                            $datos = json_decode($r->datos_extra ?? '', true) ?: [];
                        ?>
                        <tr>
                            <?php if ($tab_activo === 'todas'): ?>
                                <td><strong><?php echo esc_html($r->actividad_nombre ?: '—'); ?></strong></td>
                            <?php endif; ?>
                            <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($r->fecha))); ?></td>
                            <td><?php echo esc_html($r->horario); ?></td>
                            <td><?php echo esc_html($r->nombre); ?></td>
                            <td><?php echo esc_html($r->email); ?></td>
                            <td><?php echo esc_html($r->telefono); ?></td>
                            <?php if ($tab_activo === 'todas' || $tab_activo === 'sin_actividad'): ?>
                                <td><?php echo esc_html($r->num_menores); ?></td>
                                <td><?php echo esc_html($r->edades); ?></td>
                            <?php else: ?>
                                <?php foreach ($campos_actividad as $campo):
                                    $campo_nombre = $campo['nombre'] ?? '';
                                    if (in_array(strtolower($campo_nombre), $campos_fijos, true)) continue;
                                    $valor = $datos[$campo_nombre] ?? '';
                                ?>
                                    <td><?php echo esc_html($valor); ?></td>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <td>
                                <?php if (strtotime($r->fecha) >= strtotime('today')): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-reservas&calebo_tab=' . $tab_activo . '&action=cancelar&id=' . $r->id), 'calebo_cancelar_reserva_' . $r->id)); ?>"
                                       class="button button-small"
                                       style="color:#c62828;border-color:#ef9a9a;"
                                       onclick="return confirm('<?php esc_attr_e('¿Seguro que quieres cancelar esta reserva? Se enviará un email al usuario automáticamente.', 'calendar-booking'); ?>')">
                                        <?php esc_html_e('Cancelar', 'calendar-booking'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table></div>

            <?php if ($num_paginas > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php
                        /* translators: %s: número de reservas mostradas. */
                        echo esc_html( sprintf( _n( '%s elemento', '%s elementos', $total, 'calendar-booking' ), number_format_i18n( $total ) ) );
                        ?></span>
                        <span class="pagination-links">
                            <?php
                            echo wp_kses_post( paginate_links([
                                'base'      => add_query_arg(['paged' => '%#%', 'calebo_tab' => $tab_activo, '_wpnonce' => $nonce_pag]),
                                'format'    => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total'     => $num_paginas,
                                'current'   => $pagina,
                            ]) );
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            <!-- ── LISTA DE ESPERA ── -->
            <?php
            $act_id_espera = ($tab_activo !== 'todas' && $tab_activo !== 'sin_actividad') ? intval($tab_activo) : 0;
            if ($act_id_espera > 0):
                $act_info_espera = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, nombre, lista_espera, limite FROM {$wpdb->prefix}calebo_actividades WHERE id = %d",
                    $act_id_espera
                ));
                $espera_entries = CALEBO_Database::get_espera_by_actividad($act_id_espera);
                $espera_count   = count($espera_entries);
                $act_lista_espera_activa = !empty($act_info_espera->lista_espera);
            ?>
            <div class="calebo-waitlist-section" style="margin-top:28px;">
                <div class="calebo-waitlist-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <h2 style="margin:0;font-size:16px;display:flex;align-items:center;gap:10px;">
                        <span style="font-size:20px;">⏳</span>
                        <?php esc_html_e('Lista de espera', 'calendar-booking'); ?>
                        <span class="calebo-waitlist-badge <?php echo esc_attr( $act_lista_espera_activa ? 'calebo-waitlist-badge--active' : 'calebo-waitlist-badge--inactive' ); ?>"
                              style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;
                                     background:<?php echo esc_attr( $act_lista_espera_activa ? '#fff3e0' : '#f5f5f5' ); ?>;
                                     color:<?php echo esc_attr( $act_lista_espera_activa ? '#e65100' : '#999' ); ?>;
                                     border:1px solid <?php echo esc_attr( $act_lista_espera_activa ? '#ffb74d' : '#e0e0e0' ); ?>;">
                            <?php echo $act_lista_espera_activa ? esc_html__('✓ Activada', 'calendar-booking') : esc_html__('Desactivada', 'calendar-booking'); ?>
                        </span>
                    </h2>
                    <?php if (!$act_lista_espera_activa && $act_info_espera): ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&editar=' . $act_id_espera . '&tab=configuracion'), 'calebo_actividades_page')); ?>"
                           class="button button-small">
                            <?php esc_html_e('Activar en configuración', 'calendar-booking'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (!$act_lista_espera_activa && $espera_count === 0): ?>
                    <div style="background:#f9f9f9;border:1px dashed #ddd;border-radius:8px;padding:20px 24px;color:#999;font-size:13px;">
                        <?php esc_html_e('La lista de espera no está activada para esta actividad. Ve a la configuración de la actividad para activarla.', 'calendar-booking'); ?>
                    </div>
                <?php elseif ($espera_count === 0): ?>
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px 24px;color:#166534;font-size:13px;display:flex;align-items:center;gap:10px;">
                        <span style="font-size:20px;">✅</span>
                        <?php esc_html_e('La lista de espera está vacía. No hay personas pendientes.', 'calendar-booking'); ?>
                    </div>
                <?php else: ?>
                    <!-- Barra de progreso visual de espera -->
                    <div class="calebo-waitlist-stats" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 20px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:200px;">
                                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                    <span style="font-size:12px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:.5px;"><?php esc_html_e('Personas en espera', 'calendar-booking'); ?></span>
                                    <span style="font-size:13px;font-weight:800;color:#e65100;"><?php echo esc_html($espera_count); ?></span>
                                </div>
                                <div style="background:#f0f0f0;border-radius:20px;height:8px;overflow:hidden;">
                                    <?php $bar_pct = min(100, ($espera_count / max(1, $act_info_espera->limite ?? 20)) * 100); ?>
                                    <div style="height:100%;width:<?php echo esc_attr(round($bar_pct)); ?>%;background:linear-gradient(90deg,#F7941D,#e65100);border-radius:20px;transition:width .5s;"></div>
                                </div>
                            </div>
                            <div style="text-align:center;background:#fff3e0;border-radius:8px;padding:10px 18px;">
                                <div style="font-size:2rem;font-weight:900;color:#e65100;line-height:1;"><?php echo esc_html($espera_count); ?></div>
                                <div style="font-size:11px;color:#999;font-weight:600;text-transform:uppercase;margin-top:2px;"><?php esc_html_e('en espera', 'calendar-booking'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Listado de personas en espera -->
                    <div style="display:grid;gap:10px;">
                        <?php foreach ($espera_entries as $i => $entry): ?>
                        <div class="calebo-waitlist-card" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <div style="display:flex;align-items:center;gap:14px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#F7941D,#e65100);display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:15px;flex-shrink:0;">
                                    <?php echo esc_html(mb_strtoupper(mb_substr($entry->nombre ?: $entry->email, 0, 1))); ?>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:13px;color:#1a1a1a;"><?php echo esc_html($entry->nombre ?: '—'); ?></div>
                                    <div style="font-size:12px;color:#666;margin-top:1px;">
                                        <?php echo esc_html($entry->email); ?>
                                        <?php if ($entry->telefono): ?>
                                            <span style="margin:0 6px;opacity:.4;">·</span><?php echo esc_html($entry->telefono); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($entry->fecha): ?>
                                    <div style="font-size:11px;color:#999;margin-top:2px;">
                                         <?php echo esc_html(date_i18n('d/m/Y', strtotime($entry->fecha))); ?>
                                        &nbsp;·&nbsp;  <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($entry->creada_en))); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:11px;background:#f0f0f1;border-radius:12px;padding:3px 10px;color:#666;font-weight:700;">#<?php echo esc_html($i + 1); ?></span>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-reservas&calebo_tab=' . $tab_activo . '&action=eliminar_espera&espera_id=' . $entry->id), 'calebo_eliminar_espera_' . $entry->id)); ?>"
                                   class="button button-small"
                                   style="color:#c62828;border-color:#ef9a9a;"
                                   onclick="return confirm('<?php esc_attr_e('¿Eliminar a esta persona de la lista de espera?', 'calendar-booking'); ?>')">
                                   &times; <?php esc_html_e('Quitar', 'calendar-booking'); ?>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }

    public static function pagina_informes() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes para acceder a esta página.', 'calendar-booking'));
        }
        // Nonce: obligatorio cuando hay parámetros de navegación/acción. El menú admin
        // (URL plana sin parámetros) no requiere nonce.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Entrada verificada con check_admin_referer inmediatamente después.
        if ( isset( $_GET['calendario_id'] ) || isset( $_GET['calebo_tab'] ) ) {
            check_admin_referer('calebo_informes_page');
        }
        // phpcs:enable
        global $wpdb;

        $calendarios = CALEBO_Database::get_calendarios();
        $cal_id      = intval(wp_unslash($_GET['calendario_id'] ?? ($calendarios[0]->id ?? 0)));
        $calendario        = CALEBO_Database::get_calendario($cal_id);
        $todas_actividades = CALEBO_Database::get_actividades_by_calendario($cal_id);
        $hoy               = gmdate('Y-m-d');
        $primary           = $calendario->color_primary   ?? '#F7941D';
        $secondary         = $calendario->color_secondary ?? '#1E3FA3';

        // Pestaña activa: 'general' o el id de una actividad
        $tab      = sanitize_key(wp_unslash($_GET['calebo_tab'] ?? 'general'));
        $base_url = admin_url('admin.php?page=calebo-informes&calendario_id=' . $cal_id);
        $url_base = wp_nonce_url($base_url, 'calebo_informes_page');

        // Si el tab es un número, es el id de una actividad
        $act_id_tab = ctype_digit($tab) ? intval($tab) : null;

        // ── Rangos de fechas ──────────────────────────────────────────────
        $rangos = [
            'semana' => [
                'label'  => __('Esta semana', 'calendar-booking'),
                'tipo'   => 'semana',
                'inicio' => gmdate('Y-m-d', strtotime('monday this week')),
                'fin'    => gmdate('Y-m-d', strtotime('sunday this week')),
            ],
            'mes' => [
                'label'  => __('Este mes', 'calendar-booking'),
                'tipo'   => 'mes',
                'inicio' => gmdate('Y-m-01'),
                'fin'    => gmdate('Y-m-t'),
            ],
            'anio' => [
                'label'  => __('Este año', 'calendar-booking'),
                'tipo'   => 'anio',
                'inicio' => gmdate('Y-01-01'),
                'fin'    => gmdate('Y-12-31'),
            ],
        ];

        // ── Helper: tabla de períodos (diseño original) ───────────────────
        $render_tabla_periodos = function($act_id_filtro) use ($cal_id, $rangos, $wpdb) {
            $filas = [];
            foreach ($rangos as $rango) {
                if ($act_id_filtro !== null) {
                    $total_menores = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(num_menores),0) FROM {$wpdb->prefix}calebo_reservas
                         WHERE calendario_id = %d AND actividad_id = %d
                           AND fecha BETWEEN %s AND %s AND estado = 'activa'",
                        $cal_id, $act_id_filtro, $rango['inicio'], $rango['fin']
                    ));
                    $total_reservas = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}calebo_reservas
                         WHERE calendario_id = %d AND actividad_id = %d
                           AND fecha BETWEEN %s AND %s AND estado = 'activa'",
                        $cal_id, $act_id_filtro, $rango['inicio'], $rango['fin']
                    ));
                } else {
                    $reservas_arr   = CALEBO_Database::get_reservas_rango($cal_id, $rango['inicio'], $rango['fin']);
                    $total_menores  = array_sum(array_map(fn($r) => (int) $r->num_menores, $reservas_arr));
                    $total_reservas = count($reservas_arr);
                }
                $nonce   = wp_create_nonce('calebo_informe_rango_' . $rango['tipo'] . '_' . $cal_id);
                $params  = 'calendario_id=' . $cal_id . '&tipo=' . $rango['tipo'] . '&_wpnonce=' . $nonce;
                if ($act_id_filtro !== null) {
                    $params .= '&actividad_id=' . $act_id_filtro;
                }
                $filas[] = [
                    'label'          => $rango['label'],
                    'fecha_fmt'      => gmdate('d/m/Y', strtotime($rango['inicio'])) . ' – ' . gmdate('d/m/Y', strtotime($rango['fin'])),
                    'total_menores'  => $total_menores,
                    'total_reservas' => $total_reservas,
                    'pdf_url'        => admin_url('admin-post.php?action=calebo_pdf_rango&' . $params),
                ];
            }
            ?>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom:32px;">
                <thead><tr>
                    <th><?php esc_html_e('Período', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Fechas', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Total asistentes', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Reservas', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Descargar', 'calendar-booking'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($filas as $f): ?>
                <tr>
                    <td><strong><?php echo esc_html($f['label']); ?></strong></td>
                    <td><?php echo esc_html($f['fecha_fmt']); ?></td>
                    <td><?php echo esc_html($f['total_menores']); ?></td>
                    <td><?php echo esc_html($f['total_reservas']); ?></td>
                    <td><a href="<?php echo esc_url($f['pdf_url']); ?>" class="button button-primary" target="_blank">📄 PDF</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        };

        // ── Helper: tabla de días (diseño original) ───────────────────────
        // $pagina se lee aquí (método con nonce verificado) y se pasa al closure.
        $pagina = max(1, intval(wp_unslash($_GET['paged'] ?? 1)));
        $render_tabla_dias = function($act_id_filtro) use ($cal_id, $wpdb, $pagina) {
            $por_pagina = 20;
            $offset     = ($pagina - 1) * $por_pagina;
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_act es vacío o una cláusula preparada con %d.
            $where_act  = $act_id_filtro !== null
                ? $wpdb->prepare(' AND actividad_id = %d', $act_id_filtro)
                : '';

            $total_fechas = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT fecha) FROM {$wpdb->prefix}calebo_reservas
                 WHERE calendario_id = %d AND estado = 'activa'" . $where_act,
                $cal_id
            ));
            $num_paginas = ceil($total_fechas / $por_pagina);

            $fechas = $wpdb->get_results($wpdb->prepare(
                "SELECT fecha, SUM(num_menores) as total_menores, COUNT(*) as total_reservas
                 FROM {$wpdb->prefix}calebo_reservas
                 WHERE calendario_id = %d AND estado = 'activa'" . $where_act . "
                 GROUP BY fecha ORDER BY fecha DESC LIMIT %d OFFSET %d",
                $cal_id, $por_pagina, $offset
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th><?php esc_html_e('Fecha', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Total asistentes', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Reservas', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Descargar', 'calendar-booking'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($fechas as $f):
                    $nonce   = wp_create_nonce('calebo_informe_' . $f->fecha);
                    $pdf_url = admin_url('admin-post.php?action=calebo_pdf&fecha=' . $f->fecha . '&calendario_id=' . $cal_id . '&_wpnonce=' . $nonce);
                    if ($act_id_filtro !== null) {
                        $pdf_url .= '&actividad_id=' . $act_id_filtro;
                    }
                ?>
                <tr>
                    <td><strong><?php echo esc_html(date_i18n('d/m/Y', strtotime($f->fecha))); ?></strong></td>
                    <td><?php echo esc_html($f->total_menores); ?></td>
                    <td><?php echo esc_html($f->total_reservas); ?></td>
                    <td><a href="<?php echo esc_url($pdf_url); ?>" class="button button-primary" target="_blank">📄 PDF</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($fechas)): ?>
                <tr><td colspan="4" style="color:#888;font-style:italic;"><?php esc_html_e('Sin datos.', 'calendar-booking'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($num_paginas > 1): ?>
            <div class="tablenav bottom"><div class="tablenav-pages">
                <span class="displaying-num"><?php
                /* translators: %s: número de días mostrados. */
                echo esc_html( sprintf( _n( '%s día', '%s días', $total_fechas, 'calendar-booking' ), number_format_i18n( $total_fechas ) ) );
                ?></span>
                <span class="pagination-links"><?php echo wp_kses_post(paginate_links([
                    'base'      => add_query_arg(['paged' => '%#%', '_wpnonce' => wp_create_nonce('calebo_informes_page')]),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $num_paginas,
                    'current'   => $pagina,
                ])); ?></span>
            </div></div>
            <?php endif;
        };

        // ── Helper: bloque completo de una pestaña ────────────────────────
        $render_panel = function($act_id_filtro) use ($cal_id, $hoy, $wpdb, $render_tabla_periodos, $render_tabla_dias) {
            // Datos de hoy
            if ($act_id_filtro !== null) {
                $hoy_menores  = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(num_menores),0) FROM {$wpdb->prefix}calebo_reservas
                     WHERE calendario_id = %d AND actividad_id = %d AND fecha = %s AND estado = 'activa'",
                    $cal_id, $act_id_filtro, $hoy
                ));
                $hoy_reservas = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}calebo_reservas
                     WHERE calendario_id = %d AND actividad_id = %d AND fecha = %s AND estado = 'activa'",
                    $cal_id, $act_id_filtro, $hoy
                ));
            } else {
                $reservas_hoy = CALEBO_Database::get_reservas_fecha($cal_id, $hoy);
                $hoy_menores  = array_sum(array_map(fn($r) => (int) $r->num_menores, $reservas_hoy));
                $hoy_reservas = count($reservas_hoy);
            }
            $nonce_hoy = wp_create_nonce('calebo_informe_' . $hoy);
            $pdf_hoy   = admin_url('admin-post.php?action=calebo_pdf&fecha=' . $hoy . '&calendario_id=' . $cal_id . '&_wpnonce=' . $nonce_hoy);
            if ($act_id_filtro !== null) {
                $pdf_hoy .= '&actividad_id=' . $act_id_filtro;
            }
            ?>
            <h2><?php esc_html_e('Informes por período', 'calendar-booking'); ?></h2>

            <h3><?php esc_html_e('Informe diario (hoy)', 'calendar-booking'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom:32px;">
                <thead><tr>
                    <th><?php esc_html_e('Fecha', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Total asistentes', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Reservas', 'calendar-booking'); ?></th>
                    <th><?php esc_html_e('Descargar', 'calendar-booking'); ?></th>
                </tr></thead>
                <tbody>
                <tr>
                    <td><strong><?php echo esc_html(date_i18n('d/m/Y')); ?></strong></td>
                    <td><?php echo esc_html($hoy_menores); ?></td>
                    <td><?php echo esc_html($hoy_reservas); ?></td>
                    <td><a href="<?php echo esc_url($pdf_hoy); ?>" class="button button-primary" target="_blank">📄 PDF</a></td>
                </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e('Informes semanales, mensuales y anuales', 'calendar-booking'); ?></h3>
            <?php $render_tabla_periodos($act_id_filtro); ?>

            <h2><?php esc_html_e('Informes por día', 'calendar-booking'); ?></h2>
            <?php $render_tabla_dias($act_id_filtro);
        };
        ?>

        <div class="wrap">
            <h1><?php esc_html_e('Informes', 'calendar-booking'); ?></h1>

            <?php if (count($calendarios) > 1): ?>
            <form method="get" style="margin-bottom:20px;">
                <input type="hidden" name="page" value="calebo-informes">
                <input type="hidden" name="calebo_tab" value="<?php echo esc_attr($tab); ?>">
                <?php wp_nonce_field('calebo_informes_page'); ?>
                <label><strong><?php esc_html_e('Calendario:', 'calendar-booking'); ?></strong></label>
                <select name="calendario_id" onchange="this.form.submit()">
                    <?php foreach ($calendarios as $cal): ?>
                    <option value="<?php echo esc_attr($cal->id); ?>" <?php selected($cal->id, $cal_id); ?>>
                        <?php echo esc_html($cal->nombre); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>

            <!-- ── PESTAÑAS ───────────────────────────────────────────────── -->
            <nav class="nav-tab-wrapper">
                <?php foreach ($todas_actividades as $act): ?>
                <a href="<?php echo esc_url($url_base . '&calebo_tab=' . $act->id); ?>"
                   class="nav-tab <?php echo esc_attr( $tab === (string) $act->id ? 'nav-tab-active' : '' ); ?>">
                    <?php echo esc_html($act->nombre); ?>
                </a>
                <?php endforeach; ?>
                <a href="<?php echo esc_url($url_base . '&calebo_tab=general'); ?>"
                   class="nav-tab <?php echo esc_attr( $tab === 'general' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('General', 'calendar-booking'); ?>
                </a>
            </nav>

            <!-- ── CONTENIDO DE LA PESTAÑA ACTIVA ────────────────────────── -->
            <?php
            if ($act_id_tab !== null) {
                $render_panel($act_id_tab);
            } else {
                $render_panel(null);
            }
            ?>

        </div><!-- /.wrap -->
        <?php
    }
}