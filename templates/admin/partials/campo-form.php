<?php if (!defined('ABSPATH')) exit;
/**
 * Partial reutilizable: formulario de un campo (add/edit).
 * Espera $editar (null o objeto con etiqueta, nombre, tipo, placeholder, opciones, obligatorio)
 * y $tipos (array tipo => label).
 */
?>
<table class="form-table" style="margin:0;">
    <tr>
        <th style="width:120px;padding:8px 0;"><?php esc_html_e('Etiqueta *', 'calendar-booking'); ?></th>
        <td style="padding:4px 0;">
            <input type="text" name="etiqueta" class="regular-text"
                   value="<?php echo esc_attr($editar->etiqueta ?? ''); ?>"
                   placeholder="<?php esc_attr_e('Ej: Nombre del adulto', 'calendar-booking'); ?>" required>
        </td>
    </tr>
    <tr>
        <th style="padding:8px 0;"><?php esc_html_e('Nombre interno *', 'calendar-booking'); ?></th>
        <td style="padding:4px 0;">
            <input type="text" name="nombre" class="regular-text"
                   value="<?php echo esc_attr($editar->nombre ?? ''); ?>"
                   placeholder="<?php esc_attr_e('Ej: nombre_adulto', 'calendar-booking'); ?>">
            <p class="description" style="margin:2px 0 0;font-size:11px;color:#888;">
                <?php esc_html_e('Solo letras, números y guión bajo.', 'calendar-booking'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th style="padding:8px 0;"><?php esc_html_e('Tipo', 'calendar-booking'); ?></th>
        <td style="padding:4px 0;">
            <select name="tipo" onchange="caleboToggleOpciones(this.value)">
                <?php foreach ($tipos as $val => $label): ?>
                <option value="<?php echo esc_attr($val); ?>" <?php selected($editar->tipo ?? 'text', $val); ?>>
                    <?php echo esc_html($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th style="padding:8px 0;"><?php esc_html_e('Placeholder', 'calendar-booking'); ?></th>
        <td style="padding:4px 0;">
            <input type="text" name="placeholder" class="regular-text"
                   value="<?php echo esc_attr($editar->placeholder ?? ''); ?>"
                   placeholder="<?php esc_attr_e('Texto de ayuda dentro del campo', 'calendar-booking'); ?>">
        </td>
    </tr>
    <tr id="calebo-opciones-row" style="<?php echo ($editar->tipo ?? '') !== 'select' ? 'display:none' : ''; ?>">
        <th style="padding:8px 0;"><?php esc_html_e('Opciones', 'calendar-booking'); ?></th>
        <td style="padding:4px 0;">
            <textarea name="opciones" rows="4" class="regular-text"
                      placeholder="<?php esc_attr_e('Una opción por línea', 'calendar-booking'); ?>"><?php echo esc_textarea($editar->opciones ?? ''); ?></textarea>
        </td>
    </tr>
    <tr>
        <th style="padding:8px 0;"><?php esc_html_e('Obligatorio', 'calendar-booking'); ?></th>
        <td style="padding:4px 0;">
            <input type="checkbox" name="obligatorio" value="1" <?php checked($editar->obligatorio ?? 1, 1); ?>>
        </td>
    </tr>
</table>
