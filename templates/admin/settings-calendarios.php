<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1><?php echo esc_html($editar ? __('Editar calendario', 'calendar-booking') : __('Calendarios', 'calendar-booking')); ?></h1>

    <!-- FORMULARIO ÚNICO: CREAR O EDITAR -->
    <form method="post">
        <?php wp_nonce_field('calebo_guardar_calendario'); ?>
        <input type="hidden" name="id" value="<?php echo esc_attr($editar->id ?? 0); ?>">

        <table class="form-table">

            <!-- CAMPOS SIEMPRE VISIBLES -->
            <tr>
                <th><?php esc_html_e('Nombre *', 'calendar-booking'); ?></th>
                <td><input type="text" name="nombre" class="regular-text"
                           value="<?php echo esc_attr($editar->nombre ?? ''); ?>" required></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Descripción', 'calendar-booking'); ?></th>
                <td>
                    <textarea name="descripcion" class="regular-text" rows="3"><?php echo esc_textarea($editar->descripcion ?? ''); ?></textarea>
                    <p style="font-size:11px;color:#888;margin:4px 0 0;">
                        <?php esc_html_e('Descripción informativa del calendario. Visible solo en administración.', 'calendar-booking'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Días disponibles', 'calendar-booking'); ?></th>
                <td>
                    <?php foreach ($dias_semana as $num => $nombre): ?>
                    <label style="margin-right:14px;display:inline-flex;align-items:center;gap:5px;">
                        <input type="checkbox" name="dias_disponibles[]"
                               value="<?php echo esc_attr($num); ?>"
                               <?php echo in_array((string)$num, $dias_activos) ? 'checked' : ''; ?>>
                        <?php echo esc_html($nombre); ?>
                    </label>
                    <?php endforeach; ?>
                    <p style="font-size:11px;color:#888;margin:8px 0 0;">
                        <?php esc_html_e('Días de la semana en los que se permiten reservas.', 'calendar-booking'); ?>
                    </p>
                </td>
            </tr>

            <!-- APARIENCIA: siempre visible -->
            <tr><td colspan="2"><hr style="margin:16px 0 4px;"><h3 style="margin:0 0 4px;">🎨 <?php esc_html_e('Apariencia', 'calendar-booking'); ?></h3></td></tr>
            <tr>
                <th><?php esc_html_e('Color principal', 'calendar-booking'); ?></th>
                <td>
                    <input type="color" name="color_primary"
                           value="<?php echo esc_attr($editar->color_primary ?? '#F7941D'); ?>"
                           style="width:100px;height:40px;padding:0;">
                    <p style="font-size:11px;color:#888;margin:4px 0 0;">
                        <?php esc_html_e('Color usado para elementos destacados del calendario.', 'calendar-booking'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Color secundario', 'calendar-booking'); ?></th>
                <td>
                    <input type="color" name="color_secondary"
                           value="<?php echo esc_attr($editar->color_secondary ?? '#1E3FA3'); ?>"
                           style="width:100px;height:40px;padding:0;">
                    <p style="font-size:11px;color:#888;margin:4px 0 0;">
                        <?php esc_html_e('Color usado para elementos secundarios.', 'calendar-booking'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('URL del logo', 'calendar-booking'); ?></th>
                <td>
                    <input type="url" name="logo_url" class="regular-text"
                           value="<?php echo esc_attr($editar->logo_url ?? ''); ?>">
                    <?php if (!empty($editar->logo_url)): ?>
                    <div style="margin-top:8px;">
                        <img src="<?php echo esc_url($editar->logo_url); ?>"
                             alt="<?php esc_attr_e('Logo actual', 'calendar-booking'); ?>"
                             style="max-height:80px;max-width:200px;border:1px solid #e0e0e0;padding:4px;border-radius:4px;">
                    </div>
                    <?php endif; ?>
                </td>
            </tr>

        </table>

        <p class="submit">
            <input type="submit" name="calebo_guardar_calendario" class="button button-primary"
                   value="<?php echo esc_attr($editar ? __('Guardar cambios', 'calendar-booking') : __('Crear calendario', 'calendar-booking')); ?>">
            <?php if ($editar): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=calebo-calendarios')); ?>"
               class="button" style="margin-left:8px;">
                <?php esc_html_e('← Volver a la lista', 'calendar-booking'); ?>
            </a>
            <?php endif; ?>
        </p>
    </form>

    <!-- LISTA DE CALENDARIOS (solo cuando NO se está editando) -->
    <?php if (!$editar): ?>
    <h2 style="margin-top:40px;"><?php esc_html_e('Calendarios existentes', 'calendar-booking'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('ID', 'calendar-booking'); ?></th>
                <th><?php esc_html_e('Nombre', 'calendar-booking'); ?></th>
                <th><?php esc_html_e('Horario', 'calendar-booking'); ?></th>
                <th><?php esc_html_e('Días', 'calendar-booking'); ?></th>
                <th><?php esc_html_e('Shortcode', 'calendar-booking'); ?></th>
                <th><?php esc_html_e('Acciones', 'calendar-booking'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($calendarios as $cal):
            $dias_cal     = explode(',', $cal->dias_disponibles ?? '1,2,3,4,5');
            $mapa         = ['1'=>'L','2'=>'M','3'=>'X','4'=>'J','5'=>'V','6'=>'S','7'=>'D'];
            $nombres_dias = implode(' ', array_map(fn($d) => $mapa[$d] ?? '', $dias_cal));
        ?>
        <tr>
            <td><?php echo esc_html($cal->id); ?></td>
            <td><strong><?php echo esc_html($cal->nombre); ?></strong></td>
            <td><?php echo esc_html($cal->horario_normal ?? '16:00-20:00'); ?></td>
            <td style="font-size:12px;"><?php echo esc_html($nombres_dias); ?></td>
            <td><code>[calebo_calendario id="<?php echo esc_attr($cal->id); ?>"]</code></td>
            <td style="white-space:nowrap;">
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-calendarios&editar=' . $cal->id), 'calebo_calendarios_page')); ?>">
                    <?php esc_html_e('Editar', 'calendar-booking'); ?>
                </a>
                &nbsp;|&nbsp;
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-calendarios&eliminar_calendario=' . $cal->id), 'calebo_eliminar_calendario_' . $cal->id)); ?>"
                   onclick="return confirm('<?php echo esc_js(__('¿Seguro que quieres eliminar este calendario? Esta acción no se puede deshacer.', 'calendar-booking')); ?>')"
                   style="color:#a00;">
                    <?php esc_html_e('Eliminar', 'calendar-booking'); ?>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php
wp_add_inline_script(
    'calebo-admin',
    'var caleboCalStrings = ' . wp_json_encode( [
        'festivoPlaceholder'     => __( 'Descripción (Ej: Navidad)', 'calendar-booking' ),
        'vacacionPlaceholder'    => __( 'Ej: Semana Santa', 'calendar-booking' ),
        'desde'                  => __( 'Desde', 'calendar-booking' ),
        'hasta'                  => __( 'Hasta', 'calendar-booking' ),
        'tipo'                   => __( 'Tipo', 'calendar-booking' ),
        'bloqueado'              => __( '🚫 Bloqueado', 'calendar-booking' ),
        'horarioEspecial'        => __( '🕐 Horario especial', 'calendar-booking' ),
    ] )
);
wp_add_inline_script( 'calebo-admin', <<<'NOWDOC_CAL'
window.cbAddFestivo = function() {
    var wrap = document.getElementById("calebo-festivos-wrap");
    var row  = document.createElement("div");
    row.style.cssText = "display:flex;gap:8px;margin-bottom:8px;align-items:center;";
    row.innerHTML =
        "<input type=\"text\" name=\"festivos_label[]\" placeholder=\"" + caleboCalStrings.festivoPlaceholder + "\" style=\"width:200px;\">" +
        "<input type=\"date\" name=\"festivos_fecha[]\">" +
        "<button type=\"button\" onclick=\"this.parentElement.remove()\" style=\"background:#ffebee;border:1px solid #ef9a9a;border-radius:4px;color:#c62828;cursor:pointer;padding:4px 8px;\">✕</button>";
    wrap.appendChild(row);
};

window.cbAddVacacion = function() {
    var wrap  = document.getElementById("calebo-vacaciones-wrap");
    var index = wrap.children.length;
    var row   = document.createElement("div");
    row.style.cssText = "display:flex;gap:8px;margin-bottom:10px;align-items:center;flex-wrap:wrap;background:#f9f9f9;padding:10px;border-radius:6px;";
    row.innerHTML =
        "<input type=\"text\" name=\"vacaciones_label[]\" placeholder=\"" + caleboCalStrings.vacacionPlaceholder + "\" style=\"width:160px;\">" +
        "<label style=\"font-size:12px;\">" + caleboCalStrings.desde + "</label>" +
        "<input type=\"date\" name=\"vacaciones_inicio[]\">" +
        "<label style=\"font-size:12px;\">" + caleboCalStrings.hasta + "</label>" +
        "<input type=\"date\" name=\"vacaciones_fin[]\">" +
        "<label style=\"font-size:12px;\">" + caleboCalStrings.tipo + "</label>" +
        "<select name=\"vacaciones_tipo[" + index + "]\" style=\"font-size:12px;\">" +
            "<option value=\"bloqueado\">" + caleboCalStrings.bloqueado + "</option>" +
            "<option value=\"horario_especial\">" + caleboCalStrings.horarioEspecial + "</option>" +
        "</select>" +
        "<button type=\"button\" onclick=\"this.parentElement.remove()\" style=\"background:#ffebee;border:1px solid #ef9a9a;border-radius:4px;color:#c62828;cursor:pointer;padding:4px 8px;\">✕</button>";
    wrap.appendChild(row);
};
NOWDOC_CAL
);
?>
