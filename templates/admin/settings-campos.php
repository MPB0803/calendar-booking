<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1><?php esc_html_e('📝 Campos del formulario', 'calendar-booking'); ?></h1>
    <p style="color:#888;font-size:13px;margin-bottom:20px;">
        <?php esc_html_e('Configura los campos personalizados que aparecerán en el formulario de reserva para cada actividad.', 'calendar-booking'); ?>
    </p>

    <?php
    // ── Selector de calendario ─────────────────────────────────────────────
    if (count($calendarios) > 1): ?>
    <form method="get" style="margin-bottom:16px;">
        <input type="hidden" name="page" value="calebo-campos">
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

    <?php if (empty($actividades_cb)): ?>
    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:32px;text-align:center;color:#888;margin-top:20px;">
        <div style="font-size:32px;margin-bottom:12px;">📋</div>
        <p style="font-size:14px;font-weight:600;margin:0 0 8px;">
            <?php esc_html_e('No hay actividades creadas todavía.', 'calendar-booking'); ?>
        </p>
        <p style="font-size:13px;margin:0 0 16px;">
            <?php esc_html_e('Primero crea actividades desde el menú Actividades, luego podrás configurar los campos del formulario de cada una.', 'calendar-booking'); ?>
        </p>
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-actividades&calendario_id=' . $cal_id), 'calebo_actividades_page')); ?>" class="button button-primary">
            + <?php esc_html_e('Crear actividad', 'calendar-booking'); ?>
        </a>
    </div>
    <?php else: ?>

    <?php
    // Si no hay actividad seleccionada y hay actividades, seleccionar la primera
    if ($act_id === 0 && !empty($actividades_cb)) {
        $act_id = (int) $actividades_cb[0]->id;
    }
    ?>

    <!-- PESTAÑAS DE ACTIVIDADES -->
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:24px;border-bottom:2px solid #e0e0e0;padding-bottom:0;">
        <?php foreach ($actividades_cb as $act_tab): ?>
        <?php $is_active = ($act_id === (int)$act_tab->id); ?>
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-campos&calendario_id=' . $cal_id . '&actividad_id=' . $act_tab->id), 'calebo_campos_page')); ?>"
           style="display:inline-block;padding:10px 16px;border-radius:6px 6px 0 0;border:1px solid <?php echo esc_attr( $is_active ? '#F7941D' : '#e0e0e0' ); ?>;
                  border-bottom:none;background:<?php echo esc_attr( $is_active ? '#FFF7F0' : '#f9f9f9' ); ?>;
                  color:<?php echo esc_attr( $is_active ? '#F7941D' : '#444' ); ?>;
                  font-weight:<?php echo esc_attr( $is_active ? '700' : '400' ); ?>;
                  font-size:13px;text-decoration:none;margin-bottom:-2px;">
            📅 <?php echo esc_html($act_tab->nombre); ?>
            <span style="font-size:10px;color:#888;margin-left:4px;">
                <?php echo esc_html(date_i18n('d/m', strtotime($act_tab->fecha_inicio))); ?> — <?php echo esc_html(date_i18n('d/m', strtotime($act_tab->fecha_fin))); ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php
    $actividad_editando = CALEBO_Database::get_actividad($act_id);
    $campos_act_arr_tpl = [];
    if ($actividad_editando && !empty($actividad_editando->campos_extra)) {
        $campos_act_arr_tpl = json_decode($actividad_editando->campos_extra, true) ?: [];
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verificado en CALEBO_Settings::pagina_campos() antes de incluir este template.
        $editar_act_idx_tpl = isset($_GET['editar_campo_actividad']) ? intval(wp_unslash($_GET['editar_campo_actividad'])) : -1;
    $editar_act_campo   = ($editar_act_idx_tpl >= 0 && isset($campos_act_arr_tpl[$editar_act_idx_tpl]))
                          ? $campos_act_arr_tpl[$editar_act_idx_tpl] : null;
    ?>

    <!-- INFO BAR -->
    <div style="background:#FFF7F0;border:1px solid #F7941D33;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
        <div>
            <strong>📅 <?php echo esc_html($actividad_editando->nombre ?? ''); ?></strong>
        </div>
        <div style="color:#888;">
            <?php echo esc_html(date_i18n('d/m/Y', strtotime($actividad_editando->fecha_inicio ?? ''))); ?>
            — <?php echo esc_html(date_i18n('d/m/Y', strtotime($actividad_editando->fecha_fin ?? ''))); ?>
        </div>
        <div style="color:#888;font-size:12px;">
            <?php esc_html_e('Los campos configurados aquí aparecerán cuando el usuario seleccione esta actividad.', 'calendar-booking'); ?>
        </div>
    </div>

    <!-- PLANTILLAS RÁPIDAS -->
    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:24px;">
        <h3 style="margin:0 0 8px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">
            📋 <?php esc_html_e('Usar una plantilla predeterminada', 'calendar-booking'); ?>
        </h3>
        <p style="color:#888;font-size:13px;margin:0 0 16px;">
            <strong style="color:#c62828;"><?php esc_html_e('Atención: esto reemplazará los campos actuales de esta actividad.', 'calendar-booking'); ?></strong>
        </p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <?php foreach ($plantillas as $key => $plantilla): ?>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field('calebo_aplicar_plantilla_actividad_' . $act_id); ?>
                <input type="hidden" name="plantilla" value="<?php echo esc_attr($key); ?>">
                <button type="submit" name="calebo_aplicar_plantilla_actividad"
                        style="background:#fff;border:2px solid #e0e0e0;border-radius:8px;
                               padding:12px 16px;cursor:pointer;font-size:13px;font-weight:600;
                               transition:all .2s;text-align:left;min-width:180px;"
                        onmouseover="this.style.borderColor='#F7941D';this.style.color='#F7941D'"
                        onmouseout="this.style.borderColor='#e0e0e0';this.style.color='inherit'"
                        onclick="return confirm('<?php echo esc_js('¿Aplicar la plantilla ' . $plantilla['label'] . '?'); ?>')">
                    <div style="font-size:16px;margin-bottom:6px;"><?php echo esc_html($plantilla['label']); ?></div>
                    <div style="font-size:11px;color:#888;"><?php echo esc_html(implode(', ', array_column($plantilla['campos'], 'etiqueta'))); ?></div>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- LISTA DE CAMPOS + FORMULARIO -->
    <div style="display:grid;grid-template-columns:1fr 400px;gap:24px;align-items:start;">
        <!-- LISTA DE CAMPOS -->
        <div>
            <h2 style="font-size:15px;margin-bottom:12px;">
                <?php esc_html_e('Campos de esta actividad', 'calendar-booking'); ?>
                <span style="font-size:12px;color:#888;font-weight:400;margin-left:8px;">
                    <?php esc_html_e('Arrastra para reordenar', 'calendar-booking'); ?>
                </span>
            </h2>
            <ul id="calebo-campos-act-list" style="list-style:none;margin:0;padding:0;">
                <?php foreach ($campos_act_arr_tpl as $ci => $ce): ?>
                <li data-idx="<?php echo esc_attr($ci); ?>"
                    style="background:#fff;border:1px solid #e0e0e0;border-radius:6px;
                           padding:12px 16px;margin-bottom:8px;display:flex;align-items:center;gap:12px;">
                    <span style="color:#ccc;font-size:18px;cursor:grab;">⠿</span>
                    <div style="flex:1;">
                        <strong style="font-size:13px;"><?php echo esc_html($ce['label'] ?? $ce['etiqueta'] ?? ''); ?></strong>
                        <span style="font-size:11px;color:#888;margin-left:8px;">
                            <?php echo esc_html($tipos[$ce['tipo'] ?? 'text'] ?? ($ce['tipo'] ?? 'text')); ?>
                            <?php if (!empty($ce['requerido'])): ?>
                            · <span style="color:#c62828;"><?php esc_html_e('obligatorio', 'calendar-booking'); ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($ce['nombre'])): ?>
                        <code style="font-size:10px;color:#aaa;margin-left:6px;"><?php echo esc_html($ce['nombre']); ?></code>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-campos&calendario_id=' . $cal_id . '&actividad_id=' . $act_id . '&editar_campo_actividad=' . $ci), 'calebo_campos_page')); ?>"
                           class="button button-small">✏️ <?php esc_html_e('Editar', 'calendar-booking'); ?></a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-campos&calendario_id=' . $cal_id . '&actividad_id=' . $act_id . '&eliminar_campo_actividad=' . $ci), 'calebo_eliminar_campo_actividad_' . $ci . '_' . $act_id)); ?>"
                           class="button button-small"
                           onclick="return confirm('<?php esc_attr_e('¿Eliminar este campo?', 'calendar-booking'); ?>')"
                           style="color:#c62828;border-color:#ef9a9a;">🗑️</a>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php if (empty($campos_act_arr_tpl)): ?>
                <li style="padding:24px;text-align:center;color:#888;background:#f9f9f9;border-radius:6px;border:2px dashed #e0e0e0;">
                    <div style="font-size:24px;margin-bottom:8px;">📋</div>
                    <?php esc_html_e('Esta actividad no tiene campos. Añade los que necesites o usa una plantilla.', 'calendar-booking'); ?>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- FORMULARIO AÑADIR/EDITAR CAMPO -->
        <div style="position:sticky;top:32px;">
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;">
                <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">
                    <?php echo $editar_act_campo !== null
                        ? '✏️ ' . esc_html__( 'Editar campo', 'calendar-booking' )
                        : '➕ ' . esc_html__( 'Añadir campo', 'calendar-booking' ); ?>
                </h3>
                <form method="post">
                    <?php wp_nonce_field('calebo_guardar_campo_actividad_' . $act_id); ?>
                    <input type="hidden" name="campo_idx" value="<?php echo esc_attr($editar_act_idx_tpl); ?>">
                    <?php
                    $editar = $editar_act_campo ? (object)[
                        'id'          => $editar_act_idx_tpl,
                        'etiqueta'    => $editar_act_campo['label'] ?? $editar_act_campo['etiqueta'] ?? '',
                        'nombre'      => $editar_act_campo['nombre'] ?? '',
                        'tipo'        => $editar_act_campo['tipo'] ?? 'text',
                        'placeholder' => $editar_act_campo['placeholder'] ?? '',
                        'opciones'    => $editar_act_campo['opciones'] ?? '',
                        'obligatorio' => $editar_act_campo['requerido'] ?? 0,
                    ] : null;
                    ?>
                    <?php include __DIR__ . '/partials/campo-form.php'; ?>
                    <div style="margin-top:16px;display:flex;gap:8px;">
                        <input type="submit" name="calebo_guardar_campo_actividad" class="button button-primary"
                               value="<?php echo esc_attr($editar_act_campo !== null ? __('Actualizar campo', 'calendar-booking') : __('Añadir campo', 'calendar-booking')); ?>">
                        <?php if ($editar_act_campo !== null): ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=calebo-campos&calendario_id=' . $cal_id . '&actividad_id=' . $act_id), 'calebo_campos_page')); ?>" class="button">
                            <?php esc_html_e('Cancelar', 'calendar-booking'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php endif; /* fin empty actividades */ ?>
</div>

<?php
wp_add_inline_script(
    'sortablejs',
    '(function(){
    var nonceCampos = ' . wp_json_encode( wp_create_nonce( 'calebo_orden_campos_actividad_' . $act_id ) ) . ';
    var actId       = ' . intval( $act_id ) . ';

    var listaAct = document.getElementById("calebo-campos-act-list");
    if (listaAct) {
        Sortable.create(listaAct, {
            animation: 150, handle: "span",
            onEnd: function() {
                var indices = Array.from(listaAct.querySelectorAll("li")).map(function(li) {
                    return li.dataset.idx;
                }).filter(function(v) { return v !== undefined; });
                fetch(ajaxurl, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "action=calebo_orden_campos&calebo_orden_campos_actividad=1" +
                          "&_ajax_nonce=" + nonceCampos +
                          "&actividad_id=" + actId + "&" +
                          indices.map(function(idx, i) { return "indices[" + i + "]=" + idx; }).join("&")
                });
            }
        });
    }

    function caleboToggleOpciones(tipo) {
        var row = document.getElementById("calebo-opciones-row");
        if (row) row.style.display = tipo === "select" || tipo === "checkbox" ? "" : "none";
    }
    window.caleboToggleOpciones = caleboToggleOpciones;
    var tipoSel = document.querySelector("select[name=\'tipo\']");
    if (tipoSel) tipoSel.addEventListener("change", function() { caleboToggleOpciones(this.value); });
})();'
);
