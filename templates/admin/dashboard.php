<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1 style="font-size:24px;margin-bottom:8px;"><?php esc_html_e('CalendiFlow – Dashboard', 'calendar-booking'); ?></h1>
    <p style="color:#888;margin-bottom:24px;">
        <?php esc_html_e('Panel de control del sistema de reservas', 'calendar-booking'); ?>
    </p>

    <!-- TARJETAS DE ESTADÍSTICAS -->
    <div class="calebo-dashboard" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;">
        <div class="calebo-card" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size:12px;color:#888;margin-bottom:8px;">📅 <?php esc_html_e('Calendarios', 'calendar-booking'); ?></div>
            <div style="font-size:32px;font-weight:700;color:#1E3FA3;"><?php echo esc_html($total_calendarios); ?></div>
        </div>
        <div class="calebo-card" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size:12px;color:#888;margin-bottom:8px;">📋 <?php esc_html_e('Reservas hoy', 'calendar-booking'); ?></div>
            <div style="font-size:32px;font-weight:700;color:#4caf50;"><?php echo esc_html($reservas_hoy); ?></div>
        </div>
        <div class="calebo-card" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size:12px;color:#888;margin-bottom:8px;">📊 <?php esc_html_e('Esta semana', 'calendar-booking'); ?></div>
            <div style="font-size:32px;font-weight:700;color:#1E3FA3;"><?php echo esc_html($reservas_semana); ?></div>
        </div>
        <div class="calebo-card" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size:12px;color:#888;margin-bottom:8px;">⏳ <?php esc_html_e('Lista de espera', 'calendar-booking'); ?></div>
            <div style="font-size:32px;font-weight:700;color:#f57f17;"><?php echo esc_html($total_espera); ?></div>
        </div>
    </div>

    <!-- SHORTCODES DISPONIBLES -->
    <h2 style="font-size:16px;margin-bottom:12px;">🔗 <?php esc_html_e('Shortcodes disponibles', 'calendar-booking'); ?></h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:32px;">
        <?php
        // Shortcodes de calendario por cada calendario activo
        foreach ($calendarios as $cal): ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <span style="font-size:20px;">📅</span>
                <strong style="color:#333;font-size:13px;"><?php echo esc_html($cal->nombre); ?></strong>
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <code id="calebo-sc-cal-<?php echo esc_attr($cal->id); ?>" style="background:#f5f5f5;padding:6px 12px;border-radius:4px;font-size:13px;flex:1;user-select:all;">[calebo_calendario id="<?php echo esc_attr($cal->id); ?>"]</code>
                <button type="button" class="button button-small calebo-copy-btn" data-target="calebo-sc-cal-<?php echo esc_attr($cal->id); ?>"
                        title="<?php esc_attr_e('Copiar', 'calendar-booking'); ?>" style="min-width:36px;text-align:center;">📋</button>
            </div>
            <p style="font-size:11px;color:#888;margin:0;">
                <?php esc_html_e('Inserta el calendario de reservas en cualquier página o entrada.', 'calendar-booking'); ?>
            </p>
        </div>
        <?php endforeach; ?>

        <?php
        // Shortcode de confirmación
        $url_confirmacion = CALEBO_Booking::get_url_confirmacion();
        $confirmacion_ok  = !empty($url_confirmacion);
        ?>
        <div style="background:#fff;border:1px solid <?php echo esc_attr($confirmacion_ok ? '#e0e0e0' : '#ffe082'); ?>;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <span style="font-size:20px;">✅</span>
                <strong style="color:#333;font-size:13px;"><?php esc_html_e('Página de confirmación', 'calendar-booking'); ?></strong>
                <?php if ($confirmacion_ok): ?>
                    <span style="font-size:10px;background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;font-weight:700;"><?php esc_html_e('Activa', 'calendar-booking'); ?></span>
                <?php else: ?>
                    <span style="font-size:10px;background:#fff3e0;color:#e65100;padding:2px 8px;border-radius:10px;font-weight:700;"><?php esc_html_e('Sin configurar', 'calendar-booking'); ?></span>
                <?php endif; ?>
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <code id="calebo-sc-confirmacion" style="background:#f5f5f5;padding:6px 12px;border-radius:4px;font-size:13px;flex:1;user-select:all;">[calebo_confirmacion]</code>
                <button type="button" class="button button-small calebo-copy-btn" data-target="calebo-sc-confirmacion"
                        title="<?php esc_attr_e('Copiar', 'calendar-booking'); ?>" style="min-width:36px;text-align:center;">📋</button>
            </div>
            <p style="font-size:11px;color:#888;margin:0;">
                <?php if ($confirmacion_ok): ?>
                    <?php printf(
                        /* translators: %s: URL de la página de confirmación. */
                        esc_html__('Configurado en: %s', 'calendar-booking'),
                        '<a href="' . esc_url($url_confirmacion) . '" target="_blank" style="color:#1E3FA3;">' . esc_html($url_confirmacion) . '</a>'
                    ); ?>
                <?php else: ?>
                    <?php esc_html_e('Crea una página con este shortcode para que tras una reserva se redirija a ella.', 'calendar-booking'); ?>
                <?php endif; ?>
            </p>
        </div>

        <?php
        // Shortcode de cancelación – buscar si existe una página con él
        global $wpdb;
        $cancelar_page = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('page','post')
               AND post_content LIKE '%[calebo_cancelar%'
             LIMIT 1"
        );
        $cancelar_ok  = !empty($cancelar_page);
        $url_cancelar = $cancelar_ok ? get_permalink($cancelar_page) : '';
        ?>
        <div style="background:#fff;border:1px solid <?php echo esc_attr($cancelar_ok ? '#e0e0e0' : '#ffe082'); ?>;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <span style="font-size:20px;">❌</span>
                <strong style="color:#333;font-size:13px;"><?php esc_html_e('Página de cancelación', 'calendar-booking'); ?></strong>
                <?php if ($cancelar_ok): ?>
                    <span style="font-size:10px;background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;font-weight:700;"><?php esc_html_e('Activa', 'calendar-booking'); ?></span>
                <?php else: ?>
                    <span style="font-size:10px;background:#fff3e0;color:#e65100;padding:2px 8px;border-radius:10px;font-weight:700;"><?php esc_html_e('Sin configurar', 'calendar-booking'); ?></span>
                <?php endif; ?>
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <code id="calebo-sc-cancelar" style="background:#f5f5f5;padding:6px 12px;border-radius:4px;font-size:13px;flex:1;user-select:all;">[calebo_cancelar]</code>
                <button type="button" class="button button-small calebo-copy-btn" data-target="calebo-sc-cancelar"
                        title="<?php esc_attr_e('Copiar', 'calendar-booking'); ?>" style="min-width:36px;text-align:center;">📋</button>
            </div>
            <p style="font-size:11px;color:#888;margin:0;">
                <?php if ($cancelar_ok): ?>
                    <?php printf(
                        /* translators: %s: URL de la página de cancelación. */
                        esc_html__('Configurado en: %s', 'calendar-booking'),
                        '<a href="' . esc_url($url_cancelar) . '" target="_blank" style="color:#1E3FA3;">' . esc_html($url_cancelar) . '</a>'
                    ); ?>
                <?php else: ?>
                    <?php esc_html_e('Crea una página con este shortcode para que los usuarios puedan cancelar sus reservas.', 'calendar-booking'); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- ACCESOS RÁPIDOS -->
    <h2 style="font-size:16px;margin-bottom:12px;"><?php esc_html_e('Accesos rápidos', 'calendar-booking'); ?></h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-bottom:32px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=calebo-calendarios')); ?>"
           style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;text-decoration:none;display:block;transition:all .2s;"
           onmouseover="this.style.borderColor='#F7941D';this.style.boxShadow='0 2px 8px rgba(247,148,29,0.15)'"
           onmouseout="this.style.borderColor='#e0e0e0';this.style.boxShadow='none'">
            <div style="font-size:24px;margin-bottom:8px;">⚙️</div>
            <div style="font-weight:600;color:#333;margin-bottom:4px;"><?php esc_html_e('Configurar Calendario', 'calendar-booking'); ?></div>
            <div style="font-size:12px;color:#888;"><?php esc_html_e('Nombre, horario, días disponibles...', 'calendar-booking'); ?></div>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=calebo-actividades')); ?>"
           style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;text-decoration:none;display:block;transition:all .2s;"
           onmouseover="this.style.borderColor='#F7941D';this.style.boxShadow='0 2px 8px rgba(247,148,29,0.15)'"
           onmouseout="this.style.borderColor='#e0e0e0';this.style.boxShadow='none'">
            <div style="font-size:24px;margin-bottom:8px;">🎯</div>
            <div style="font-weight:600;color:#333;margin-bottom:4px;"><?php esc_html_e('Gestionar Actividades', 'calendar-booking'); ?></div>
            <div style="font-size:12px;color:#888;"><?php esc_html_e('Crear, editar, festivos, vacaciones...', 'calendar-booking'); ?></div>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=calebo-campos')); ?>"
           style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;text-decoration:none;display:block;transition:all .2s;"
           onmouseover="this.style.borderColor='#F7941D';this.style.boxShadow='0 2px 8px rgba(247,148,29,0.15)'"
           onmouseout="this.style.borderColor='#e0e0e0';this.style.boxShadow='none'">
            <div style="font-size:24px;margin-bottom:8px;">📝</div>
            <div style="font-weight:600;color:#333;margin-bottom:4px;"><?php esc_html_e('Campos del Formulario', 'calendar-booking'); ?></div>
            <div style="font-size:12px;color:#888;"><?php esc_html_e('Personalizar campos por actividad', 'calendar-booking'); ?></div>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=calebo-reservas')); ?>"
           style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;text-decoration:none;display:block;transition:all .2s;"
           onmouseover="this.style.borderColor='#F7941D';this.style.boxShadow='0 2px 8px rgba(247,148,29,0.15)'"
           onmouseout="this.style.borderColor='#e0e0e0';this.style.boxShadow='none'">
            <div style="font-size:24px;margin-bottom:8px;">📋</div>
            <div style="font-weight:600;color:#333;margin-bottom:4px;"><?php esc_html_e('Ver Reservas', 'calendar-booking'); ?></div>
            <div style="font-size:12px;color:#888;"><?php esc_html_e('Listado completo de reservas', 'calendar-booking'); ?></div>
        </a>
    </div>

    <!-- PRÓXIMAS RESERVAS -->
    <?php if (!empty($proximas_reservas)): ?>
    <h2 style="font-size:16px;margin-bottom:12px;"><?php esc_html_e('Próximas reservas', 'calendar-booking'); ?></h2>
    <table class="wp-list-table widefat striped" style="max-width:800px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Fecha', 'calendar-booking'); ?></th>
                <th><?php esc_html_e('Actividad', 'calendar-booking'); ?></th>
                <th><?php esc_html_e('Nombre', 'calendar-booking'); ?></th>
                <th><?php esc_html_e('Plazas', 'calendar-booking'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($proximas_reservas as $pr): ?>
            <tr>
                <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($pr->fecha))); ?></td>
                <td><?php echo esc_html($pr->actividad_nombre ?? '—'); ?></td>
                <td><?php echo esc_html($pr->nombre); ?></td>
                <td><?php echo esc_html($pr->num_menores); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- ALERTAS DE CAPACIDAD -->
    <?php if (!empty($alertas_capacidad)): ?>
    <div style="margin-top:24px;background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:16px;">
        <h3 style="margin:0 0 8px;color:#f57f17;font-size:14px;">⚠️ <?php esc_html_e('Alertas de capacidad', 'calendar-booking'); ?></h3>
        <?php foreach ($alertas_capacidad as $alerta): ?>
        <p style="margin:4px 0;font-size:13px;color:#666;">
            <strong><?php echo esc_html($alerta['actividad']); ?></strong>
            — <?php echo esc_html($alerta['fecha']); ?>:
            <?php echo esc_html($alerta['ocupadas'] . '/' . $alerta['limite']); ?>
            (<?php echo esc_html($alerta['porcentaje']); ?>%)
        </p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
