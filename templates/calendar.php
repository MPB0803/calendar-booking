<?php if (!defined('ABSPATH')) exit; ?>
 
<?php
$dias_disponibles = array_map('intval', explode(',', $calendario->dias_disponibles ?? '1,2,3,4,5'));
sort($dias_disponibles);
$num_cols    = count($dias_disponibles);
$nombres_dias = [1=>'Lun', 2=>'Mar', 3=>'Mié', 4=>'Jue', 5=>'Vie', 6=>'Sáb', 7=>'Dom'];
?>
 
<div id="calebo-wrap-<?php echo esc_attr($calendario->id); ?>"
     class="calebo-wrap"
     data-id="<?php echo esc_attr($calendario->id); ?>"
     data-limite="<?php echo esc_attr($calendario->limite); ?>"
     data-meses-max="<?php echo esc_attr(intval($calendario->meses_max ?? 3)); ?>"
     data-dia-activacion="<?php echo esc_attr(intval($calendario->dia_activacion ?? 0)); ?>"
     data-mes-inicio-ciclo="<?php echo esc_attr(intval($calendario->mes_inicio_ciclo ?? 1)); ?>"
     data-dias-disponibles="<?php echo esc_attr(implode(',', $dias_disponibles)); ?>"
     data-num-cols="<?php echo esc_attr($num_cols); ?>">
 
 
    <!-- CABECERA -->
    <div class="calebo-cal-header">
        <button class="calebo-nav-btn calebo-prev">&#10094;</button>
        <h2 class="calebo-titulo-mes"><?php esc_html_e('Cargando...', 'calendar-booking'); ?></h2>
        <button class="calebo-nav-btn calebo-next">&#10095;</button>
    </div>
 
    <!-- OVERLAY DE CARGA -->
    <div class="calebo-loading-overlay">
        <div class="calebo-loader-icon">
            <div class="calebo-loader-header"></div>
            <div class="calebo-loader-grid" id="calebo-loader-grid-<?php echo esc_attr($calendario->id); ?>">
                <?php for($i=0;$i<15;$i++): ?>
                <div class="calebo-loader-cell"></div>
                <?php endfor; ?>
            </div>
        </div>
        <span class="calebo-loader-label"><?php esc_html_e('Cargando...', 'calendar-booking'); ?></span>
    </div>
 
    <!-- GRID: cabeceras de días -->
    <div class="calebo-cal-grid" id="calebo-grid-<?php echo esc_attr($calendario->id); ?>">
        <?php foreach ($dias_disponibles as $num_dia): ?>
        <div class="calebo-day-label"><?php echo esc_html($nombres_dias[$num_dia]); ?></div>
        <?php endforeach; ?>
    </div>
 
    <!-- LEYENDA -->
    <div class="calebo-leyenda">
        <div class="calebo-leyenda-item">
            <div class="calebo-leyenda-dot calebo-dot-disponible"></div>
            <?php esc_html_e('Disponible', 'calendar-booking'); ?>
        </div>
        <div class="calebo-leyenda-item">
            <div class="calebo-leyenda-dot calebo-dot-casi"></div>
            <?php esc_html_e('Quedan pocas plazas', 'calendar-booking'); ?>
        </div>
        <div class="calebo-leyenda-item">
            <div class="calebo-leyenda-dot calebo-dot-lleno"></div>
            <?php esc_html_e('Completo', 'calendar-booking'); ?>
        </div>
        <div class="calebo-leyenda-item">
            <div class="calebo-leyenda-dot calebo-dot-multi" style="background:var(--calebo-secondary);border-color:var(--calebo-secondary);"></div>
            <?php esc_html_e('Múltiples actividades', 'calendar-booking'); ?>
        </div>
    </div>
 
    <!-- LISTA DE ACTIVIDADES (cuando hay 2+ en un día) -->
    <div class="calebo-actividades-wrap" style="display:none;">
        <h3><?php esc_html_e('Actividades disponibles', 'calendar-booking'); ?> — <span class="calebo-actividades-fecha"></span></h3>
        <div class="calebo-actividades-lista"></div>
    </div>

    <!-- FORMULARIO -->
    <div class="calebo-form-wrap" style="display:none;">
        <h3><?php esc_html_e('Hacer reserva', 'calendar-booking'); ?></h3>
        <div class="calebo-fecha-label"></div>
        <div class="calebo-actividad-row" style="display:none;">
            <span class="calebo-actividad-label-prefix"><?php esc_html_e('Actividad:', 'calendar-booking'); ?></span>
            <span class="calebo-actividad-label"></span>
            <button type="button" class="calebo-btn-volver-actividades"><?php esc_html_e('← Cambiar actividad', 'calendar-booking'); ?></button>
        </div>
 
        <!-- Campos dinámicos de la actividad seleccionada (renderizados vía JS) -->
        <div class="calebo-form-grid"></div>

        <div class="calebo-plazas-info"></div>
        <button class="calebo-btn-reservar"><?php echo esc_html($diseno['btn_texto']); ?></button>
        <div class="calebo-msg calebo-msg-error" style="display:none;"></div>
    </div>
 
    <!-- CONFIRMACIÓN -->
    <div class="calebo-confirmacion" style="display:none;">
        <div class="calebo-check-circle">✓</div>
        <h3><?php esc_html_e('¡Reserva confirmada!', 'calendar-booking'); ?></h3>
        <p class="calebo-confirmacion-detalle"></p>
        <p class="calebo-confirmacion-email"></p>
        <button class="calebo-btn-nueva"><?php esc_html_e('Hacer otra reserva', 'calendar-booking'); ?></button>
    </div>
 
</div>
