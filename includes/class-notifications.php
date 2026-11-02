<?php
if (!defined('ABSPATH')) exit;

class CALEBO_Notifications {

    // ── Iconos por clave de campo ──
    private static function icono_campo($clave) {
        $iconos = [
            'nombre'      => '👤',
            'email'       => '📧',
            'telefono'    => '📞',
            'num_menores' => '👦',
            'edades'      => '🎂',
            'direccion'   => '🏠',
            'dni'         => '🪪',
            'empresa'     => '🏢',
            'notas'       => '📝',
            'alergias'    => '⚠️',
            'edad'        => '🎂',
        ];
        return $iconos[$clave] ?? '📋';
    }

    // ── HELPER: cabecera HTML del email ──
    private static function email_cabecera($cal, $titulo) {
        // FIX 1: Escapar todas las variables interpoladas en HTML de email (XSS)
        $primary   = sanitize_hex_color($cal->color_primary   ?? '') ?: '#F7941D';
        $secondary = sanitize_hex_color($cal->color_secondary ?? '') ?: '#1E3FA3';
        $nombre    = esc_html($cal->nombre ?? 'Calendar Booking');
        $titulo    = esc_html($titulo);
        $logo      = esc_url($cal->logo_url ?? '');

        $logo_html = $logo
            ? "<img src='{$logo}' height='40' style='max-height:40px;width:auto;vertical-align:middle;margin-right:12px;'>"
            : '';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;'>
        <div style='max-width:600px;margin:20px auto;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);'>
            <div style='background:{$primary};padding:24px 32px;'>
                <div>
                    {$logo_html}
                    <div style='color:rgba(255,255,255,0.8);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:2px;'>{$nombre}</div>
                    <div style='color:#ffffff;font-size:20px;font-weight:800;'>{$titulo}</div>
                </div>
            </div>
            <div style='height:4px;background:{$secondary};'></div>
        ";
    }

    // ── HELPER: pie HTML del email ──
    private static function email_pie($cal) {
        // FIX 1: Escapar nombre del calendario en el pie del email
        $nombre = esc_html($cal->nombre ?? 'Calendar Booking');
        $anio   = gmdate('Y');

        return "
            <div style='background:#2D2D2D;padding:16px 32px;text-align:center;'>
                <p style='color:#888;font-size:11px;margin:0;'>
                    &copy; {$anio} {$nombre} &middot; " . __('Todos los derechos reservados', 'calendar-booking') . "
                </p>
            </div>
        </div>
        </body>
        </html>
        ";
    }

    // ── HELPER: reemplazar etiquetas {nombre}, {calendario} ──
    private static function reemplazar_etiquetas($texto, $nombre, $calendario_nombre) {
        $texto = str_replace('{nombre}', esc_html($nombre), $texto);
        $texto = str_replace('{calendario}', esc_html($calendario_nombre), $texto);
        return $texto;
    }

    // ── HELPER: fila de detalle (formato apilado: etiqueta arriba, valor abajo) ──
    private static function email_fila($icono, $etiqueta, $valor) {
        return "
        <tr>
            <td style='padding:12px 16px;border-bottom:1px solid #f0f0f0;'>
                <div style='color:#888;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;'>{$icono} {$etiqueta}</div>
                <div style='color:#2D2D2D;font-size:14px;font-weight:700;'>" . esc_html($valor) . "</div>
            </td>
        </tr>
        ";
    }

    // ── HELPER: generar filas dinámicas de campos ──
    private static function email_filas_campos($campos_config, $datos) {
        $html = '';
        if (empty($campos_config) || !is_array($campos_config)) {
            // Sin configuración de campos, mostrar todos los datos_extra
            if (!empty($datos) && is_array($datos)) {
                foreach ($datos as $clave => $valor) {
                    if (trim($valor) !== '') {
                        $label = ucfirst(str_replace('_', ' ', $clave));
                        $html .= self::email_fila(self::icono_campo($clave), $label, $valor);
                    }
                }
            }
            return $html;
        }

        foreach ($campos_config as $campo) {
            $clave = $campo['nombre'] ?? '';
            if (!$clave) continue;
            $valor = $datos[$clave] ?? '';
            if (trim($valor) === '') continue;

            $label = $campo['label'] ?? $campo['etiqueta'] ?? ucfirst(str_replace('_', ' ', $clave));
            $icono = self::icono_campo($clave);
            $html .= self::email_fila($icono, $label, $valor);
        }
        return $html;
    }

    // ── HELPER: obtener URL de cancelación ──
    private static function get_cancel_url($token) {
        global $wpdb;

        $pagina_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type = 'page'
             AND post_content LIKE %s
             LIMIT 1",
            '%calebo_cancelar%'
        ));

        $enc_token = rawurlencode($token);
        if ($pagina_id) {
            $url = get_permalink($pagina_id) . '?token=' . $enc_token;
        } else {
            $pagina_slug = get_page_by_path('cancelar-reserva');
            if ($pagina_slug) {
                $url = get_permalink($pagina_slug->ID) . '?token=' . $enc_token;
            } else {
                $url = home_url('/?calebo_cancelar=1&token=' . $enc_token);
            }
        }

        return $url;
    }

    // ── CONFIRMACIÓN AL CLIENTE ──
    public static function enviar_confirmacion_cliente($cal, $fecha, $actividad, $datos, $campos_config, $token) {
        $fecha_fmt  = date_i18n('d/m/Y', strtotime($fecha));
        $cancel_url = self::get_cancel_url($token);
        $primary    = $cal->color_primary   ?? '#F7941D';

        // Extraer nombre y email de los datos
        $nombre = $datos['nombre'] ?? '';
        $email  = $datos['email']  ?? '';
        if (!$email) return;

        $diseno     = json_decode($cal->diseno ?? '{}', true);
        $asunto_tpl = !empty($diseno['email_asunto_conf']) ? $diseno['email_asunto_conf'] : __('✅ Reserva confirmada – {calendario}', 'calendar-booking');
        $msg_tpl    = !empty($diseno['email_msg_conf'])    ? $diseno['email_msg_conf']    : __('Hola <strong>{nombre}</strong>,<br>tu reserva en <strong>{calendario}</strong> ha sido confirmada correctamente.', 'calendar-booking');

        $asunto  = self::reemplazar_etiquetas($asunto_tpl, $nombre, $cal->nombre);
        $mensaje = self::email_cabecera($cal, __('¡Reserva confirmada!', 'calendar-booking'));

        $mensaje .= "
            <div style='padding:28px 32px;'>
                <p style='color:#2D2D2D;font-size:15px;margin:0 0 20px;'>
                    " . self::reemplazar_etiquetas($msg_tpl, $nombre, $cal->nombre) . "
                </p>

                <div style='background:#f9f9f9;border-radius:8px;overflow:hidden;margin-bottom:24px;border:1px solid #f0f0f0;'>
                    <div style='background:#2D2D2D;padding:10px 16px;'>
                        <span style='color:{$primary};font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;'>
                            " . __('Detalles de tu reserva', 'calendar-booking') . "
                        </span>
                    </div>
                    <table style='width:100%;border-collapse:collapse;'>
        ";

        // Siempre mostrar actividad y fecha
        $mensaje .= self::email_fila('🎯', __('Actividad', 'calendar-booking'), $actividad->nombre);
        if (!empty($actividad->sala)) {
            $mensaje .= self::email_fila('📍', __('Sala', 'calendar-booking'), $actividad->sala);
        }
        $mensaje .= self::email_fila('📅', __('Fecha', 'calendar-booking'), $fecha_fmt);
        $mensaje .= self::email_fila('🕐', __('Horario', 'calendar-booking'), $actividad->horario);

        // Campos dinámicos de la actividad (excluyendo los que ya se muestran arriba)
        $excluir = ['nombre', 'email']; // No repetir nombre/email en la tabla de detalles
        if (!empty($campos_config) && is_array($campos_config)) {
            foreach ($campos_config as $campo) {
                $clave = $campo['nombre'] ?? '';
                if (!$clave || in_array($clave, $excluir)) continue;
                $valor = $datos[$clave] ?? '';
                if (trim($valor) === '') continue;

                $label = $campo['label'] ?? $campo['etiqueta'] ?? ucfirst(str_replace('_', ' ', $clave));
                $mensaje .= self::email_fila(self::icono_campo($clave), $label, $valor);
            }
        }

        $mensaje .= "
                    </table>
                </div>

                <div style='background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:14px 16px;margin-bottom:24px;'>
                    <p style='color:#f57f17;font-size:13px;margin:0;font-weight:600;'>
                        " . __('ℹ️ Si necesitas cancelar tu reserva pulsa el botón de abajo.', 'calendar-booking') . "
                    </p>
                </div>

                <div style='text-align:center;margin-bottom:8px;'>
                    <a href='" . esc_url($cancel_url) . "'
                       style='display:inline-block;background:#f5f5f5;color:#888;
                              font-size:12px;font-weight:700;text-decoration:none;
                              padding:10px 24px;border-radius:6px;border:1px solid #e0e0e0;'>
                        ❌ " . __('Cancelar reserva', 'calendar-booking') . "
                    </a>
                </div>

                <p style='text-align:center;font-size:11px;color:#aaa;margin-top:12px;'>
                    " . __('O copia este enlace en tu navegador:', 'calendar-booking') . "<br>
                    <span style='color:#888;word-break:break-all;'>" . esc_html($cancel_url) . "</span>
                </p>
            </div>
        ";

        $mensaje .= self::email_pie($cal);
        wp_mail($email, $asunto, $mensaje, ['Content-Type: text/html; charset=UTF-8']);
    }

    // ── CONFIRMACIÓN AL ADMINISTRADOR ──
    public static function enviar_confirmacion_admin($cal, $fecha, $actividad, $datos, $campos_config, $ocupadas) {
        $fecha_fmt   = date_i18n('d/m/Y', strtotime($fecha));
        $primary     = $cal->color_primary ?? '#F7941D';

        // Calcular ocupación con num_menores si existe, sino contar como 1
        $num_menores = max(1, intval($datos['num_menores'] ?? 1));
        $total       = $ocupadas + $num_menores;
        $porcentaje  = $actividad->limite > 0 ? round(($total / $actividad->limite) * 100) : 0;
        $color_barra = $porcentaje >= 100 ? '#c62828' : ($porcentaje >= 75 ? '#f57f17' : '#4caf50');

        $opciones_smtp = CALEBO_SMTP::get_opciones();
        $email_admin   = !empty($opciones_smtp['email_admin']) ? $opciones_smtp['email_admin'] : get_option('admin_email');

        $asunto  = __('🔔 Nueva reserva – ', 'calendar-booking') . $cal->nombre . ' · ' . $fecha_fmt;

        $mensaje = self::email_cabecera($cal, __('Nueva reserva recibida', 'calendar-booking'));

        $mensaje .= "
            <div style='padding:28px 32px;'>
                <div style='background:#f9f9f9;border-radius:8px;overflow:hidden;margin-bottom:20px;border:1px solid #f0f0f0;'>
                    <div style='background:#2D2D2D;padding:10px 16px;'>
                        <span style='color:{$primary};font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;'>
                            " . __('Datos de la reserva', 'calendar-booking') . "
                        </span>
                    </div>
                    <table style='width:100%;border-collapse:collapse;'>
        ";

        // Actividad, fecha y horario siempre visibles
        $mensaje .= self::email_fila('🎯', __('Actividad', 'calendar-booking'), $actividad->nombre);
        if (!empty($actividad->sala)) {
            $mensaje .= self::email_fila('📍', __('Sala', 'calendar-booking'), $actividad->sala);
        }
        $mensaje .= self::email_fila('📅', __('Fecha', 'calendar-booking'), $fecha_fmt);
        $mensaje .= self::email_fila('🕐', __('Horario', 'calendar-booking'), $actividad->horario);

        // TODOS los campos de la actividad (el admin ve todo)
        if (!empty($campos_config) && is_array($campos_config)) {
            foreach ($campos_config as $campo) {
                $clave = $campo['nombre'] ?? '';
                if (!$clave) continue;
                $valor = $datos[$clave] ?? '';
                if (trim($valor) === '') continue;

                $label = $campo['label'] ?? $campo['etiqueta'] ?? ucfirst(str_replace('_', ' ', $clave));
                $mensaje .= self::email_fila(self::icono_campo($clave), $label, $valor);
            }
        }

        $mensaje .= "
                    </table>
                </div>

                <div style='background:#f9f9f9;border-radius:8px;padding:16px;border:1px solid #f0f0f0;'>
                    <div style='display:table;width:100%;margin-bottom:8px;'>
                        <span style='display:table-cell;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:1px;'>
                            " . __('Ocupación del día', 'calendar-booking') . "
                        </span>
                        <span style='display:table-cell;text-align:right;font-size:13px;font-weight:800;color:{$color_barra};'>
                            " . sprintf(/* translators: %1$d: ocupación actual, %2$d: límite de plazas, %3$d: porcentaje de ocupación. */ __('%1$d / %2$d plazas (%3$d%%)', 'calendar-booking'), $total, $actividad->limite, $porcentaje) . "
                        </span>
                    </div>
                    <div style='background:#e0e0e0;border-radius:20px;height:10px;overflow:hidden;'>
                        <div style='background:{$color_barra};height:100%;width:" . min($porcentaje, 100) . "%;border-radius:20px;'></div>
                    </div>
                </div>
            </div>
        ";

        $mensaje .= self::email_pie($cal);
        wp_mail($email_admin, $asunto, $mensaje, ['Content-Type: text/html; charset=UTF-8']);
    }

    // ── CANCELACIÓN AL CLIENTE ──
    public static function enviar_cancelacion_cliente($reserva, $cancelado_por_admin = false) {
        $fecha_fmt = date_i18n('d/m/Y', strtotime($reserva->fecha));
        $cal       = CALEBO_Database::get_calendario($reserva->calendario_id);

        $diseno     = json_decode($cal->diseno ?? '{}', true);
        $asunto_tpl = !empty($diseno['email_asunto_canc']) ? $diseno['email_asunto_canc'] : __('❌ Reserva cancelada – {calendario}', 'calendar-booking');

        if ($cancelado_por_admin) {
            $msg_tpl = !empty($diseno['email_msg_canc_admin']) ? $diseno['email_msg_canc_admin'] : __('Hola <strong>{nombre}</strong>,<br>lamentamos comunicarle que por motivos excepcionales <strong>hemos tenido que cancelar su reserva</strong>. Disculpe las molestias ocasionadas.', 'calendar-booking');
        } else {
            $msg_tpl = !empty($diseno['email_msg_canc']) ? $diseno['email_msg_canc'] : __('Hola <strong>{nombre}</strong>,<br>tu reserva ha sido <strong>cancelada correctamente</strong>.', 'calendar-booking');
        }

        $nombre = $reserva->nombre;
        $asunto = self::reemplazar_etiquetas($asunto_tpl, $nombre, $cal->nombre);
        $mensaje = self::email_cabecera($cal, __('Reserva cancelada', 'calendar-booking'));

        // Recuperar actividad y sus campos para mostrar detalles relevantes
        $actividad    = null;
        $campos_config = [];
        if (!empty($reserva->actividad_id)) {
            $actividad = CALEBO_Database::get_actividad($reserva->actividad_id);
            if ($actividad && !empty($actividad->campos_extra)) {
                $campos_config = json_decode($actividad->campos_extra, true) ?: [];
            }
        }

        $datos_extra = [];
        if (!empty($reserva->datos_extra)) {
            $datos_extra = json_decode($reserva->datos_extra, true) ?: [];
        }

        $mensaje .= "
            <div style='padding:28px 32px;'>
                <p style='color:#2D2D2D;font-size:15px;margin:0 0 20px;'>
                    " . self::reemplazar_etiquetas($msg_tpl, $nombre, $cal->nombre) . "
                </p>

                <div style='background:#f9f9f9;border-radius:8px;overflow:hidden;margin-bottom:24px;border:1px solid #f0f0f0;'>
                    <div style='background:#2D2D2D;padding:10px 16px;'>
                        <span style='color:#c62828;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;'>
                            " . __('Reserva cancelada', 'calendar-booking') . "
                        </span>
                    </div>
                    <table style='width:100%;border-collapse:collapse;'>
        ";

        // Actividad y fecha siempre
        if ($actividad) {
            $mensaje .= self::email_fila('🎯', __('Actividad', 'calendar-booking'), $actividad->nombre);
        }
        $mensaje .= self::email_fila('📅', __('Fecha', 'calendar-booking'), $fecha_fmt);
        if (!empty($reserva->horario)) {
            $mensaje .= self::email_fila('🕐', __('Horario', 'calendar-booking'), $reserva->horario);
        }

        // Campos dinámicos de la actividad
        $excluir = ['nombre', 'email'];
        if (!empty($campos_config)) {
            foreach ($campos_config as $campo) {
                $clave = $campo['nombre'] ?? '';
                if (!$clave || in_array($clave, $excluir)) continue;
                $valor = $datos_extra[$clave] ?? '';
                if (trim($valor) === '') continue;

                $label = $campo['label'] ?? $campo['etiqueta'] ?? ucfirst(str_replace('_', ' ', $clave));
                $mensaje .= self::email_fila(self::icono_campo($clave), $label, $valor);
            }
        }

        $mensaje .= "
                    </table>
                </div>

                <div style='background:#f9f9f9;border-radius:8px;padding:14px 16px;border:1px solid #f0f0f0;'>
                    <p style='color:#888;font-size:13px;margin:0;'>
                        " . __('Si fue un error puedes hacer una nueva reserva en nuestra web.', 'calendar-booking') . "
                    </p>
                </div>
            </div>
        ";

        $mensaje .= self::email_pie($cal);
        wp_mail($reserva->email, $asunto, $mensaje, ['Content-Type: text/html; charset=UTF-8']);
    }

    // ── CONFIRMACIÓN AUTOMÁTICA (LISTA DE ESPERA) ──
    public static function enviar_confirmacion_automatica_espera($cal, $actividad, $fecha, $espera, $token) {
        $fecha_fmt = date_i18n('d/m/Y', strtotime($fecha));
        $primary   = $cal->color_primary ?? '#F7941D';
        $nombre    = $espera->nombre ?: __('Usuario', 'calendar-booking');
        $cancel_url = self::get_cancel_url($token);

        $asunto = __('🎉 ¡Plaza confirmada automáticamente! – ', 'calendar-booking') . $actividad->nombre . ' · ' . $fecha_fmt;

        $mensaje = self::email_cabecera($cal, __('¡Hemos reservado tu plaza!', 'calendar-booking'));
        $mensaje .= "
            <div style='padding:28px 32px;'>
                <p style='color:#2D2D2D;font-size:15px;margin:0 0 20px;'>
                    " . sprintf(
                        /* translators: %s: nombre del usuario. */
                        __('Hola <strong>%s</strong>,<br>¡Buenas noticias! Se ha liberado una plaza en la actividad que te interesaba y <strong>te la hemos asignado automáticamente</strong>.', 'calendar-booking'),
                        esc_html($nombre)
                    ) . "
                </p>

                <div style='background:#f9f9f9;border-radius:8px;overflow:hidden;margin-bottom:24px;border:1px solid #f0f0f0;'>
                    <div style='background:#2D2D2D;padding:10px 16px;'>
                        <span style='color:{$primary};font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;'>
                            " . __('Detalles de tu reserva', 'calendar-booking') . "
                        </span>
                    </div>
                    <table style='width:100%;border-collapse:collapse;'>
        ";

        $mensaje .= self::email_fila('🎯', __('Actividad', 'calendar-booking'), $actividad->nombre);
        $mensaje .= self::email_fila('📅', __('Fecha', 'calendar-booking'), $fecha_fmt);
        $mensaje .= self::email_fila('🕐', __('Horario', 'calendar-booking'), $actividad->horario);

        $mensaje .= "
                    </table>
                </div>

                <div style='background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:14px 16px;margin-bottom:24px;'>
                    <p style='color:#f57f17;font-size:13px;margin:0;font-weight:600;'>
                        " . __('ℹ️ Si finalmente no puedes asistir, por favor cancela tu reserva para que otra persona pueda ocupar la plaza.', 'calendar-booking') . "
                    </p>
                </div>

                <div style='text-align:center;margin-bottom:8px;'>
                    <a href='" . esc_url($cancel_url) . "'
                       style='display:inline-block;background:#f5f5f5;color:#888;
                              font-size:12px;font-weight:700;text-decoration:none;
                              padding:10px 24px;border-radius:6px;border:1px solid #e0e0e0;'>
                        ❌ " . __('Cancelar reserva', 'calendar-booking') . "
                    </a>
                </div>

                <p style='text-align:center;font-size:11px;color:#aaa;margin-top:12px;'>
                    " . __('O copia este enlace en tu navegador:', 'calendar-booking') . "<br>
                    <span style='color:#888;word-break:break-all;'>" . esc_html($cancel_url) . "</span>
                </p>
            </div>
        ";

        $mensaje .= self::email_pie($cal);
        wp_mail($espera->email, $asunto, $mensaje, ['Content-Type: text/html; charset=UTF-8']);
    }
}
