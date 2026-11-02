<?php
if (!defined('ABSPATH')) exit;

class CALEBO_Reports {

    /**
     * Sirve el informe como HTML a pantalla completa (inline).
     *
     * La generación del PDF es 100% client-side: la barra de acción fija usa
     * window.print() y html2canvas + jsPDF cargados desde cdnjs. No existe endpoint
     * server-side de PDF: ajax_download_pdf y CALEBO_PDF_Generator se eliminaron como
     * código muerto en la re-auditoría 2026-09-08.
     *
     * El parámetro $filename se conserva por compatibilidad (nombre propuesto).
     */
    private static function output_pdf( string $html, string $filename ): void {
        // El PDF se genera en el cliente (html2canvas + jsPDF), por lo que simplemente
        // servimos el HTML del informe. El $filename se conserva por compatibilidad.
        header( 'Content-Type: text/html; charset=utf-8' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $html;
        exit;
    }

    public static function init() {
        add_action('admin_post_calebo_pdf',       [__CLASS__, 'descargar_pdf']);
        add_action('admin_post_calebo_pdf_rango', [__CLASS__, 'descargar_pdf_rango']);
        add_action('calebo_email_diario',         [__CLASS__, 'enviar_email_diario']);

        if (!wp_next_scheduled('calebo_email_diario')) {
            $timestamp = strtotime('today 08:00:00');
            if ($timestamp < time()) $timestamp = strtotime('tomorrow 08:00:00');
            wp_schedule_event($timestamp, 'daily', 'calebo_email_diario');
        }
    }

    private static function get_datos($calendario_id, $fecha) {
        $reservas  = CALEBO_Database::get_reservas_fecha($calendario_id, $fecha);
        $resultado = [];
        foreach ($reservas as $r) {
            $resultado[] = [
                'nombre'       => $r->nombre,
                'email'        => $r->email,
                'telefono'     => $r->telefono,
                'num_menores'  => $r->num_menores,
                'edades'       => $r->edades,
                'horario'      => $r->horario,
                'datos_extra'  => $r->datos_extra ?? '',
            ];
        }
        return $resultado;
    }

    private static function get_rangos() {
        return [
            'diario' => [
                'label'  => __('Hoy', 'calendar-booking'),
                'inicio' => gmdate('Y-m-d'),
                'fin'    => gmdate('Y-m-d'),
            ],
            'semana' => [
                'label'  => __('Esta semana', 'calendar-booking'),
                'inicio' => gmdate('Y-m-d', strtotime('monday this week')),
                'fin'    => gmdate('Y-m-d', strtotime('sunday this week')),
            ],
            'mes' => [
                'label'  => __('Este mes', 'calendar-booking'),
                'inicio' => gmdate('Y-m-01'),
                'fin'    => gmdate('Y-m-t'),
            ],
            'anio' => [
                'label'  => __('Este año', 'calendar-booking'),
                'inicio' => gmdate('Y-01-01'),
                'fin'    => gmdate('Y-12-31'),
            ],
        ];
    }

    private static function pdf_cabecera($titulo, $fecha_fmt, $primary, $secondary, $color_barra, $porcentaje, $logo, $calendario_id_for_js = 0, $fecha_for_js = '', $actividad_id_for_js = null) {
        $primary     = sanitize_hex_color($primary)     ?: '#F7941D';
        $secondary   = sanitize_hex_color($secondary)   ?: '#1E3FA3';
        $color_barra = sanitize_hex_color($color_barra) ?: '#4caf50';
        $porcentaje  = min(100, max(0, intval($porcentaje)));
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($titulo); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( CALEBO_PLUGIN_URL . 'assets/css/reports-standalone.css' ); ?>">
        </head>
        <body style="--primary:<?php echo esc_attr( $primary ); ?>;--secondary:<?php echo esc_attr( $secondary ); ?>;--barra:<?php echo esc_attr( $color_barra ); ?>">

        <!-- ── Barra de acciones fija ── -->
        <div class="calebo-action-bar">
            <div class="calebo-bar-title">
                <?php echo esc_html($titulo); ?>
                <span><?php echo esc_html($fecha_fmt); ?></span>
            </div>
            <div class="calebo-bar-actions">
                <button id="calebo-btn-back" class="calebo-btn calebo-btn-back">
                    ← <?php esc_html_e('Volver', 'calendar-booking'); ?>
                </button>
                <button id="calebo-btn-print" class="calebo-btn calebo-btn-print">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    <?php esc_html_e('Imprimir', 'calendar-booking'); ?>
                </button>
                <button id="calebo-btn-pdf" class="calebo-btn calebo-btn-pdf">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?php esc_html_e('Descargar PDF', 'calendar-booking'); ?>
                </button>
            </div>
        </div>
        <!-- Librerías para generación de PDF idéntico al HTML (cdnjs; uso admin-only) -->
        <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- CDN cdnjs, solo admin, no vendorizable sin subir tamaño del plugin. ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha384-ZZ1pncU3bQe8y31yfZdMFdSpttDoPmOZg2wguVK9almUodir1PghgT0eY7Mrty8H" crossorigin="anonymous"></script>
        <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, PluginCheck.CodeAnalysis.Offloading.OffloadedContent ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha384-JcnsjUPPylna1s1fvi1u12X5qjY5OL56iySh75FdtrwhO/SWXgMjoVqcKyIIWOLk" crossorigin="anonymous"></script>
<?php // Datos dinámicos para la lógica de PDF externalizada en assets/js/admin-reports.js (página standalone). ?>
        <script>
        var caleboReportData = <?php echo wp_json_encode( [
            'titulo'   => sanitize_text_field( $titulo ),
            'fechaFmt' => sanitize_text_field( $fecha_fmt ),
        ] ); ?>;
        </script>
        <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- script propio externalizado; página standalone sin admin_enqueue. ?>
        <script src="<?php echo esc_url( CALEBO_PLUGIN_URL . 'assets/js/admin-reports.js' ); ?>"></script>

        <div class="calebo-report-wrap">
            <div class="header">
                <div style="display:flex;align-items:center;gap:14px;">
                    <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" style="height:44px;width:auto;vertical-align:middle;" alt="">
                    <?php endif; ?>
                    <h1><?php echo esc_html($titulo); ?></h1>
                </div>
                <span class="fecha-badge">📅 <?php echo esc_html($fecha_fmt); ?></span>
            </div>
            <div class="stripe"></div>
        <?php
    }

    private static function pdf_pie($nombre_cal) {
        ?>
            <div class="calebo-footer">
                <span><?php echo esc_html__('Generado el', 'calendar-booking'); ?> <?php echo esc_html(date_i18n('d/m/Y H:i')); ?> · <?php echo esc_html($nombre_cal); ?></span>
                <span>CalendiFlow</span>
            </div>
        </div><!-- /.calebo-report-wrap -->
        </body>
        </html>
        <?php
    }

    public static function descargar_pdf() {
        if (!current_user_can('manage_options')) wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'calendar-booking' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce se verifica en la línea siguiente con el valor leído.
        $fecha = sanitize_text_field( wp_unslash( $_GET['fecha'] ?? '' ) );
        check_admin_referer('calebo_informe_' . $fecha);

        $calendario_id = intval($_GET['calendario_id'] ?? 0);
        $actividad_id  = isset($_GET['actividad_id']) && ctype_digit((string)$_GET['actividad_id'])
                         ? intval($_GET['actividad_id']) : null;

        global $wpdb;
        $calendario    = CALEBO_Database::get_calendario($calendario_id);

        // Si viene actividad_id filtramos solo sus reservas; si no, todas las del calendario
        if ($actividad_id !== null) {
            $reservas_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT nombre, email, telefono, num_menores, edades, horario, datos_extra
                 FROM {$wpdb->prefix}calebo_reservas
                 WHERE calendario_id = %d AND actividad_id = %d AND fecha = %s AND estado = 'activa'",
                $calendario_id, $actividad_id, $fecha
            ));
            $datos = [];
            foreach ($reservas_raw as $r) {
                $datos[] = [
                    'nombre'       => $r->nombre,
                    'email'        => $r->email,
                    'telefono'     => $r->telefono,
                    'num_menores'  => $r->num_menores,
                    'edades'       => $r->edades,
                    'horario'      => $r->horario,
                    'datos_extra'  => $r->datos_extra ?? '',
                ];
            }
            // Título: nombre de la actividad (y su límite para la barra de ocupación, N4)
            $actividad  = $wpdb->get_row($wpdb->prepare(
                "SELECT nombre, limite FROM {$wpdb->prefix}calebo_actividades WHERE id = %d", $actividad_id
            ));
            $titulo_pdf = $actividad ? $actividad->nombre : ($calendario->nombre ?? 'Calendar Booking');
        } else {
            $datos      = self::get_datos($calendario_id, $fecha);
            $titulo_pdf = $calendario->nombre ?? 'Calendar Booking';
        }

        $fecha_formato = date_i18n('d/m/Y', strtotime($fecha));
        $total_menores = array_sum(array_column($datos, 'num_menores'));
        // N4 (re-auditoría): cuando el informe filtra por una actividad concreta, la
        // barra de ocupación debe referirse al límite de ESA actividad, no al del calendario.
        $limite        = ( $actividad_id !== null && ! empty( $actividad->limite ) )
                         ? (int) $actividad->limite
                         : intval( $calendario->limite ?? 20 );
        $porcentaje    = $limite > 0 ? round(($total_menores / $limite) * 100) : 0;
        $color_barra   = $porcentaje >= 100 ? '#1E3FA3' : ($porcentaje >= 75 ? '#F7941D' : '#4caf50');
        $primary       = $calendario->color_primary   ?? '#F7941D';
        $secondary     = $calendario->color_secondary ?? '#1E3FA3';
        $nombre_cal    = $calendario->nombre           ?? 'Calendar Booking';
        $logo          = $calendario->logo_url         ?? '';

        // Capturar HTML completo en buffer para pasarlo a output_pdf().
        ob_start();
        self::pdf_cabecera($titulo_pdf, $fecha_formato, $primary, $secondary, $color_barra, $porcentaje, $logo, $calendario_id, $fecha, $actividad_id);
        ?>
        <div class="resumen">
            <div class="tarjeta"><div class="numero"><?php echo esc_html($total_menores); ?></div><div class="etiqueta"><?php esc_html_e('Menores totales', 'calendar-booking'); ?></div></div>
            <div class="tarjeta sec"><div class="numero"><?php echo esc_html($limite - $total_menores); ?></div><div class="etiqueta"><?php esc_html_e('Plazas libres', 'calendar-booking'); ?></div></div>
            <div class="tarjeta"><div class="numero"><?php echo esc_html(count($datos)); ?></div><div class="etiqueta"><?php esc_html_e('Reservas', 'calendar-booking'); ?></div></div>
        </div>
        <div class="ocupacion-wrap">
            <div class="ocupacion-label"><?php esc_html_e('Ocupación del día', 'calendar-booking'); ?></div>
            <div class="barra-fondo"><div class="barra-relleno" style="width:<?php echo esc_attr( $porcentaje ); ?>%;"></div></div>
            <div class="barra-texto"><?php echo esc_html($total_menores); ?> / <?php echo esc_html($limite); ?> <?php esc_html_e('menores', 'calendar-booking'); ?> (<?php echo esc_html($porcentaje); ?>%)</div>
        </div>
        <?php
        // Detectar campos extra únicos presentes en las reservas
        $campos_fijos    = ['nombre', 'email', 'telefono', 'num_menores', 'edades', 'horario', 'actividad'];
        $campos_extra_hd = [];
        foreach ($datos as $fila) {
            $extra = json_decode($fila['datos_extra'] ?? '', true) ?: [];
            foreach ($extra as $k => $v) {
                if (!in_array($k, $campos_fijos, true) && !isset($campos_extra_hd[$k])) {
                    $campos_extra_hd[$k] = ucfirst(str_replace('_', ' ', $k));
                }
            }
        }
        ?>
        <div class="tabla-wrap">
            <div class="tabla-titulo"><?php esc_html_e('Listado de reservas', 'calendar-booking'); ?></div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php esc_html_e('Nombre', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Email', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Teléfono', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Menores', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Edades', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Horario', 'calendar-booking'); ?></th>
                        <?php foreach ($campos_extra_hd as $ce_label): ?>
                        <th><?php echo esc_html($ce_label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($datos as $i => $fila):
                    $extra_fila = json_decode($fila['datos_extra'] ?? '', true) ?: [];
                ?>
                <tr>
                    <td><?php echo esc_html($i + 1); ?></td>
                    <td><strong><?php echo esc_html($fila['nombre']); ?></strong></td>
                    <td><?php echo esc_html($fila['email']); ?></td>
                    <td><?php echo esc_html($fila['telefono']); ?></td>
                    <td>
                        <span class="badge <?php echo esc_attr( $fila['num_menores'] >= 5 ? 'alto' : '' ); ?>">
                            <?php echo esc_html($fila['num_menores']); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($fila['edades']); ?></td>
                    <td><?php echo esc_html($fila['horario']); ?></td>
                    <?php foreach ($campos_extra_hd as $ce_key => $ce_label): ?>
                    <td><?php echo esc_html($extra_fila[$ce_key] ?? ''); ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        self::pdf_pie($titulo_pdf);
        $html = ob_get_clean();
        $filename = 'reservas-' . sanitize_file_name($fecha) . '-' . sanitize_file_name($titulo_pdf);
        self::output_pdf($html, $filename);
    }

    public static function descargar_pdf_rango() {
        if (!current_user_can('manage_options')) wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'calendar-booking' ) );
        $calendario_id = intval($_GET['calendario_id'] ?? 0);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce se verifica en la línea siguiente con los valores leídos.
        $tipo = sanitize_text_field( wp_unslash( $_GET['tipo'] ?? 'semana' ) );
        check_admin_referer('calebo_informe_rango_' . $tipo . '_' . $calendario_id);

        $actividad_id = isset($_GET['actividad_id']) && ctype_digit((string)$_GET['actividad_id'])
                        ? intval($_GET['actividad_id']) : null;

        global $wpdb;
        $calendario    = CALEBO_Database::get_calendario($calendario_id);
        $rangos        = self::get_rangos();
        $rango         = $rangos[$tipo] ?? $rangos['semana'];

        // Filtrar reservas y totales según actividad o todas
        if ($actividad_id !== null) {
            $reservas = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, a.nombre as actividad_nombre
                 FROM {$wpdb->prefix}calebo_reservas r
                 LEFT JOIN {$wpdb->prefix}calebo_actividades a ON r.actividad_id = a.id
                 WHERE r.calendario_id = %d AND r.actividad_id = %d
                   AND r.fecha BETWEEN %s AND %s AND r.estado = 'activa'
                 ORDER BY r.fecha ASC",
                $calendario_id, $actividad_id, $rango['inicio'], $rango['fin']
            ));
            $totales = $wpdb->get_results($wpdb->prepare(
                "SELECT fecha, SUM(num_menores) as total_menores, COUNT(*) as total_reservas
                 FROM {$wpdb->prefix}calebo_reservas
                 WHERE calendario_id = %d AND actividad_id = %d
                   AND fecha BETWEEN %s AND %s AND estado = 'activa'
                 GROUP BY fecha ORDER BY fecha ASC",
                $calendario_id, $actividad_id, $rango['inicio'], $rango['fin']
            ));
        } else {
            $reservas = CALEBO_Database::get_reservas_rango($calendario_id, $rango['inicio'], $rango['fin']);
            $totales  = CALEBO_Database::get_totales_rango($calendario_id, $rango['inicio'], $rango['fin']);
        }
        $total_menores = array_sum(array_map(fn($r) => (int) $r->num_menores, $reservas));
        $primary       = $calendario->color_primary   ?? '#F7941D';
        $secondary     = $calendario->color_secondary ?? '#1E3FA3';
        $nombre_cal    = $calendario->nombre           ?? 'Calendar Booking';
        $logo          = $calendario->logo_url         ?? '';
        $fecha_fmt     = $rango['inicio'] === $rango['fin']
            ? date_i18n('d/m/Y', strtotime($rango['inicio']))
            : gmdate('d/m/Y', strtotime($rango['inicio'])) . ' – ' . gmdate('d/m/Y', strtotime($rango['fin']));

        // Título del PDF
        if ($actividad_id !== null) {
            $act_obj    = $wpdb->get_row($wpdb->prepare(
                "SELECT nombre FROM {$wpdb->prefix}calebo_actividades WHERE id = %d", $actividad_id
            ));
            $titulo_pdf = ($act_obj ? $act_obj->nombre : $nombre_cal) . ' — ' . $rango['label'];
        } else {
            $titulo_pdf = $nombre_cal . ' — ' . $rango['label'];
        }

        // Actividades en el rango (solo la seleccionada o todas)
        if ($actividad_id !== null) {
            $actividades_rango = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}calebo_actividades WHERE id = %d", $actividad_id
            ));
        } else {
            $actividades_rango = CALEBO_Database::get_actividades_rango($calendario_id, $rango['inicio'], $rango['fin']);
        }
        // get_reservas_actividades_rango ya devuelve todos los datos; no necesita filtro adicional
        // porque acts_resumen itera solo sobre $actividades_rango (ya filtrado por actividad si procede).
        $reservas_acts_rango  = CALEBO_Database::get_reservas_actividades_rango($calendario_id, $rango['inicio'], $rango['fin']);

        // Calcular resumen por actividad
        $acts_resumen = [];
        foreach ($actividades_rango as $act) {
            $d_start  = max($rango['inicio'], $act->fecha_inicio);
            $d_end    = min($rango['fin'],    $act->fecha_fin);
            $total_ocp = 0;
            $cur = $d_start;
            while ($cur <= $d_end) {
                $total_ocp += $reservas_acts_rango[$cur][(int)$act->id] ?? 0;
                $cur = gmdate('Y-m-d', strtotime($cur . ' +1 day'));
            }
            $total_res = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}calebo_reservas
                 WHERE actividad_id = %d AND fecha BETWEEN %s AND %s AND estado = 'activa'",
                $act->id, $d_start, $d_end
            ));
            $dias_activa     = (int) (new DateTime($d_start))->diff(new DateTime($d_end))->days + 1;
            $capacidad_total = $act->limite * $dias_activa;
            $acts_resumen[]  = compact('act', 'total_ocp', 'total_res', 'dias_activa', 'capacidad_total', 'd_start', 'd_end');
        }

        // Mapa actividad_id => nombre para la tabla de detalle
        $acto_nombres = [];
        foreach ($actividades_rango as $act) {
            $acto_nombres[(int)$act->id] = $act->nombre;
        }

        // Calcular porcentaje real de ocupación del rango
        $capacidad_total_rango = 0;
        foreach ($actividades_rango as $act_cap) {
            $d_s  = max($rango['inicio'], $act_cap->fecha_inicio);
            $d_e  = min($rango['fin'],    $act_cap->fecha_fin);
            $dias = max(0, (int) (new DateTime($d_s))->diff(new DateTime($d_e))->days + 1);
            $capacidad_total_rango += $act_cap->limite * $dias;
        }
        $porcentaje_rango  = $capacidad_total_rango > 0 ? min(100, round(($total_menores / $capacidad_total_rango) * 100)) : 0;
        $color_barra_rango = $porcentaje_rango >= 100 ? $secondary : ($porcentaje_rango >= 75 ? '#F7941D' : '#4caf50');

        ob_start();
        self::pdf_cabecera($titulo_pdf, $fecha_fmt, $primary, $secondary, $color_barra_rango, $porcentaje_rango, $logo, $calendario_id, $rango['inicio'], $actividad_id);
        ?>
        <div class="resumen">
            <?php if ($actividad_id !== null): /* Vista de una actividad concreta */ ?>
            <div class="tarjeta"><div class="numero"><?php echo esc_html(count($totales)); ?></div><div class="etiqueta"><?php esc_html_e('Días con reservas', 'calendar-booking'); ?></div></div>
            <div class="tarjeta"><div class="numero"><?php echo esc_html($total_menores); ?></div><div class="etiqueta"><?php esc_html_e('Total asistentes', 'calendar-booking'); ?></div></div>
            <div class="tarjeta sec"><div class="numero"><?php echo esc_html(count($reservas)); ?></div><div class="etiqueta"><?php esc_html_e('Total reservas', 'calendar-booking'); ?></div></div>
            <div class="tarjeta"><div class="numero"><?php echo esc_html($porcentaje_rango); ?>%</div><div class="etiqueta"><?php esc_html_e('Ocupación media', 'calendar-booking'); ?></div></div>
            <?php else: /* Vista de todas las actividades */ ?>
            <div class="tarjeta"><div class="numero"><?php echo esc_html(count($actividades_rango)); ?></div><div class="etiqueta"><?php esc_html_e('Actividades', 'calendar-booking'); ?></div></div>
            <div class="tarjeta"><div class="numero"><?php echo esc_html($total_menores); ?></div><div class="etiqueta"><?php esc_html_e('Total asistentes', 'calendar-booking'); ?></div></div>
            <div class="tarjeta sec"><div class="numero"><?php echo esc_html(count($reservas)); ?></div><div class="etiqueta"><?php esc_html_e('Total reservas', 'calendar-booking'); ?></div></div>
            <div class="tarjeta"><div class="numero"><?php echo esc_html(count($totales)); ?></div><div class="etiqueta"><?php esc_html_e('Días con reservas', 'calendar-booking'); ?></div></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($acts_resumen)): ?>
        <div class="tabla-wrap">
            <div class="tabla-titulo"><?php esc_html_e('Resumen por actividad', 'calendar-booking'); ?></div>
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Actividad', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Período', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Horario', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Sala', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Días', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Reservas', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Asistentes', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Ocupación', 'calendar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($acts_resumen as $row):
                    $act       = $row['act'];
                    $pct       = $row['capacidad_total'] > 0 ? min(100, round(($row['total_ocp'] / $row['capacidad_total']) * 100)) : 0;
                    $c_pct     = $pct >= 100 ? sanitize_hex_color($secondary) : ($pct >= 75 ? '#F7941D' : '#4caf50');
                    $per_fmt   = date_i18n('d/m/Y', strtotime($row['d_start']));
                    if ($row['d_start'] !== $row['d_end']) {
                        $per_fmt .= ' – ' . date_i18n('d/m/Y', strtotime($row['d_end']));
                    }
                ?>
                <tr>
                    <td><strong><?php echo esc_html($act->nombre); ?></strong></td>
                    <td style="font-size:0.78rem;"><?php echo esc_html($per_fmt); ?></td>
                    <td><?php echo esc_html($act->horario ?: '—'); ?></td>
                    <td><?php echo esc_html($act->sala ?: '—'); ?></td>
                    <td><?php echo esc_html($row['dias_activa']); ?></td>
                    <td><?php echo esc_html($row['total_res']); ?></td>
                    <td><span class="badge"><?php echo esc_html($row['total_ocp']); ?></span> / <?php echo esc_html($row['capacidad_total']); ?></td>
                    <td>
                        <div style="background:#f0f0f0;border-radius:10px;height:8px;overflow:hidden;">
                            <div style="background:<?php echo esc_attr($c_pct); ?>;height:100%;width:<?php echo esc_attr($pct); ?>%;border-radius:10px;"></div>
                        </div>
                        <span style="font-size:0.7rem;color:#888;"><?php echo esc_html($pct); ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="tabla-wrap">
            <div class="tabla-titulo"><?php esc_html_e('Resumen por día', 'calendar-booking'); ?></div>
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Fecha', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Actividades', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Asistentes', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Reservas', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Ocupación', 'calendar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($totales as $t):
                    if ($actividad_id !== null) {
                        // Informe filtrado: mostrar solo la actividad seleccionada
                        $acts_hoy      = array_filter($actividades_rango, fn($a) =>
                            $a->fecha_inicio <= $t->fecha && $a->fecha_fin >= $t->fecha);
                    } else {
                        // Informe global: todas las actividades activas ese día
                        $acts_hoy      = CALEBO_Database::get_actividades_fecha($calendario_id, $t->fecha);
                    }
                    $noms          = implode(', ', array_map(fn($a) => $a->nombre, $acts_hoy));
                    // Capacidad real del día = suma de límites de actividades activas ese día
                    $capacidad_dia = array_sum(array_map(fn($a) => (int) $a->limite, $acts_hoy));
                    if ($capacidad_dia <= 0) $capacidad_dia = intval($calendario->limite ?? 20);
                    $pct           = round(($t->total_menores / $capacidad_dia) * 100);
                    $pct_width     = min($pct, 100);
                    $color_pct     = $pct >= 100
                        ? sanitize_hex_color($secondary)
                        : ($pct >= 75 ? '#F7941D' : sanitize_hex_color($primary));
                ?>
                <tr>
                    <td><strong><?php echo esc_html(date_i18n('l, d/m/Y', strtotime($t->fecha))); ?></strong></td>
                    <td style="font-size:0.78rem;color:#555;"><?php echo esc_html($noms ?: '—'); ?></td>
                    <td><?php echo esc_html($t->total_menores); ?> / <?php echo esc_html($capacidad_dia); ?></td>
                    <td><?php echo esc_html($t->total_reservas); ?></td>
                    <td>
                        <div style="background:#f0f0f0;border-radius:10px;height:8px;overflow:hidden;">
                            <div style="background:<?php echo esc_attr($color_pct); ?>;height:100%;width:<?php echo esc_attr($pct_width); ?>%;border-radius:10px;"></div>
                        </div>
                        <span style="font-size:0.7rem;color:#888;"><?php echo esc_html($pct); ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="tabla-wrap" style="margin-top:2px;">
            <div class="tabla-titulo"><?php esc_html_e('Detalle de todas las reservas', 'calendar-booking'); ?></div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php esc_html_e('Fecha', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Actividad', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Nombre', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Email', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Teléfono', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Asistentes', 'calendar-booking'); ?></th>
                        <th><?php esc_html_e('Horario', 'calendar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reservas as $i => $r): ?>
                <tr>
                    <td><?php echo esc_html($i + 1); ?></td>
                    <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($r->fecha))); ?></td>
                    <td style="font-size:0.78rem;"><?php echo esc_html($acto_nombres[(int)$r->actividad_id] ?? '—'); ?></td>
                    <td><strong><?php echo esc_html($r->nombre); ?></strong></td>
                    <td><?php echo esc_html($r->email); ?></td>
                    <td><?php echo esc_html($r->telefono); ?></td>
                    <td>
                        <span class="badge <?php echo intval($r->num_menores) >= 5 ? 'alto' : ''; ?>">
                            <?php echo esc_html($r->num_menores); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($r->horario); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        self::pdf_pie($titulo_pdf);
        $html = ob_get_clean();
        $filename = 'informe-' . sanitize_file_name($tipo) . '-' . sanitize_file_name($titulo_pdf);
        self::output_pdf($html, $filename);
    }

    public static function enviar_email_diario() {
        $calendarios = CALEBO_Database::get_calendarios();
        $hoy         = gmdate('Y-m-d');
        $fecha_fmt   = date_i18n('d/m/Y');

        foreach ($calendarios as $cal) {
            $datos = self::get_datos($cal->id, $hoy);
            if (empty($datos)) continue;

            $total   = array_sum(array_column($datos, 'num_menores'));

            // Capacidad real del día (N6): suma de límites de las actividades activas hoy,
            // no el límite genérico del calendario (puede haber varias actividades).
            $actividades_hoy = CALEBO_Database::get_actividades_fecha($cal->id, $hoy);
            $capacidad_dia   = array_sum(array_map(
                function ($a) { return max(0, (int) ($a->limite ?? 0)); },
                $actividades_hoy
            ));
            if ($capacidad_dia <= 0) {
                $capacidad_dia = max(0, (int) ($cal->limite ?? 0));
            }

            $primary = sanitize_hex_color($cal->color_primary)   ?: '#F7941D';
            $sec     = sanitize_hex_color($cal->color_secondary) ?: '#1E3FA3';

            $filas = '';
            foreach ($datos as $i => $f) {
                $bg     = $i % 2 === 0 ? '#fff' : '#fff8f0';
                $filas .= '<tr style="background:' . esc_attr($bg) . '">'
                        . '<td style="padding:8px;border-bottom:1px solid #eee">' . esc_html($i + 1) . '</td>'
                        . '<td style="padding:8px;border-bottom:1px solid #eee">' . esc_html($f['nombre']) . '</td>'
                        . '<td style="padding:8px;border-bottom:1px solid #eee">' . esc_html($f['email']) . '</td>'
                        . '<td style="padding:8px;border-bottom:1px solid #eee">' . esc_html($f['telefono']) . '</td>'
                        . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center">'
                        . '<span style="background:' . esc_attr($primary) . ';color:#fff;padding:2px 8px;border-radius:10px;font-weight:700">' . esc_html($f['num_menores']) . '</span>'
                        . '</td>'
                        . '<td style="padding:8px;border-bottom:1px solid #eee">' . esc_html($f['edades']) . '</td>'
                        . '<td style="padding:8px;border-bottom:1px solid #eee">' . esc_html($f['horario']) . '</td>'
                        . '</tr>';
            }

            $logo_html = '';
            if (!empty($cal->logo_url)) {
                $logo_html = '<img src="' . esc_url($cal->logo_url) . '" height="36" style="vertical-align:middle;margin-right:10px;" alt="">';
            }

            $mensaje = '
            <html><body style="font-family:Arial,sans-serif;color:#333;background:#f5f5f5;margin:0;padding:0">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;">
                <div style="background:' . esc_attr($primary) . ';padding:16px 24px;">
                    ' . $logo_html . '
                    <span style="color:#fff;font-weight:800;font-size:16px;text-transform:uppercase;vertical-align:middle;">
                        ' . esc_html__('Informe diario', 'calendar-booking') . ' – ' . esc_html($fecha_fmt) . '
                    </span>
                </div>
                <div style="height:4px;background:' . esc_attr($sec) . '"></div>
                <div style="padding:20px 24px;">
                    <p style="margin:0 0 16px;font-size:13px;">
                        <strong>' . esc_html__('Total menores:', 'calendar-booking') . '</strong>
                        <span style="color:' . esc_attr($primary) . ';font-weight:800;">' . esc_html($total) . ' / ' . esc_html($capacidad_dia) . '</span>
                        &nbsp;·&nbsp;
                        <strong>' . esc_html__('Reservas:', 'calendar-booking') . '</strong> ' . esc_html(count($datos)) . '
                    </p>
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead><tr style="background:#2D2D2D;color:#fff;">
                            <th style="padding:10px">#</th>
                            <th style="padding:10px">' . esc_html__('Nombre', 'calendar-booking') . '</th>
                            <th style="padding:10px">' . esc_html__('Email', 'calendar-booking') . '</th>
                            <th style="padding:10px">' . esc_html__('Teléfono', 'calendar-booking') . '</th>
                            <th style="padding:10px">' . esc_html__('Menores', 'calendar-booking') . '</th>
                            <th style="padding:10px">' . esc_html__('Edades', 'calendar-booking') . '</th>
                            <th style="padding:10px">' . esc_html__('Horario', 'calendar-booking') . '</th>
                        </tr></thead>
                        <tbody>' . $filas . '</tbody>
                    </table>
                </div>
                <div style="background:#2D2D2D;padding:12px 24px;text-align:right;">
                    <span style="color:#555;font-size:11px;">© ' . esc_html(gmdate('Y')) . ' ' . esc_html($cal->nombre) . '</span>
                </div>
            </div>
            </body></html>';

            $opciones_smtp = CALEBO_SMTP::get_opciones();
            $email_admin   = !empty($opciones_smtp['email_admin']) ? $opciones_smtp['email_admin'] : get_option('admin_email');

            wp_mail(
                $email_admin,
                esc_html__('Informe diario', 'calendar-booking') . ' – ' . $cal->nombre . ' – ' . $fecha_fmt,
                $mensaje,
                ['Content-Type: text/html; charset=UTF-8']
            );
        }
    }
}
