<?php if (!defined('ABSPATH')) exit;
/**
 * Template: Pantalla de confirmación de reserva.
 *
 * Variables disponibles (inyectadas desde shortcode_confirmacion):
 *   $reserva    – objeto de la reserva (o null)
 *   $actividad  – objeto de la actividad (o null)
 *   $calendario – objeto del calendario (o null)
 *
 * El CSS de esta página se encola desde includes/class-booking.php
 * (enqueue_assets) vía wp_enqueue_style, cuando el shortcode está presente.
 */
$tiene_datos = !empty($reserva) && !empty($actividad);

// Campos personalizados guardados en datos_extra (JSON) durante la reserva.
$datos_extra = [];
if ( $tiene_datos && ! empty( $reserva->datos_extra ) ) {
    $decoded = json_decode( $reserva->datos_extra, true );
    if ( is_array( $decoded ) ) {
        $datos_extra = $decoded;
    }
}

// Email/teléfono pueden venir de datos_extra o de columnas estándar.
$email    = $datos_extra['email']    ?? ( $reserva->email    ?? '' );
$telefono = $datos_extra['telefono'] ?? ( $reserva->telefono ?? '' );

// Campos adicionales (no estándar) para mostrarlos en el detalle.
$omitir_claves = [ 'nombre', 'email', 'telefono', 'num_menores', 'edades' ];
$campos_adic   = [];
foreach ( $datos_extra as $clave => $valor ) {
    if ( in_array( $clave, $omitir_claves, true ) ) {
        continue;
    }
    if ( $valor === '' || $valor === null ) {
        continue;
    }
    $campos_adic[ $clave ] = is_string( $valor ) ? $valor : (string) $valor;
}

$fecha_fmt = !empty($reserva->fecha)
    ? date_i18n(get_option('date_format', 'd/m/Y'), strtotime($reserva->fecha))
    : '';
?>

<div class="calebo-conf-wrap">

    <!-- Icono de check animado -->
    <div class="calebo-conf-check">
        <svg viewBox="0 0 72 72">
            <circle cx="36" cy="36" r="36" fill="#F7941D"/>
            <path fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round"
                  stroke-linejoin="round" d="M22 36 l10 10 l18 -20"
                  style="stroke-dasharray:40;stroke-dashoffset:40;animation:calebo-draw .5s .2s ease forwards;"/>
        </svg>
    </div>

    <!-- Título -->
    <h2 class="calebo-conf-title">
        <?php esc_html_e('¡Reserva confirmada!', 'calendar-booking'); ?>
    </h2>
    <p class="calebo-conf-subtitle">
        <?php esc_html_e('Te hemos enviado un correo con los detalles de tu reserva.', 'calendar-booking'); ?>
    </p>

    <?php if ($tiene_datos) : ?>

    <!-- Resumen destacado tipo ticket -->
    <div class="calebo-conf-ticket">
        <div class="calebo-conf-ticket-head">
            <span><?php esc_html_e('Detalles de tu reserva', 'calendar-booking'); ?></span>
            <span>✓</span>
        </div>
        <div class="calebo-conf-ticket-body">
            <?php if (!empty($actividad->nombre)) : ?>
            <div class="calebo-conf-ticket-item">
                <span class="calebo-conf-ticket-key"><?php esc_html_e('Actividad', 'calendar-booking'); ?></span>
                <span class="calebo-conf-ticket-val calebo-conf-ticket-val--big"><?php echo esc_html($actividad->nombre); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($fecha_fmt) : ?>
            <div class="calebo-conf-ticket-item">
                <span class="calebo-conf-ticket-key"><?php esc_html_e('Fecha', 'calendar-booking'); ?></span>
                <span class="calebo-conf-ticket-val"><?php echo esc_html($fecha_fmt); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($reserva->horario)) : ?>
            <div class="calebo-conf-ticket-item">
                <span class="calebo-conf-ticket-key"><?php esc_html_e('Horario', 'calendar-booking'); ?></span>
                <span class="calebo-conf-ticket-val"><?php echo esc_html($reserva->horario); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detalle completo -->
    <div class="calebo-conf-card">

        <?php if (!empty($reserva->nombre)) : ?>
        <div class="calebo-conf-row">
            <span class="calebo-conf-label">👤 <?php esc_html_e('Nombre', 'calendar-booking'); ?></span>
            <span class="calebo-conf-value"><?php echo esc_html($reserva->nombre); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($email)) : ?>
        <div class="calebo-conf-row">
            <span class="calebo-conf-label">📧 <?php esc_html_e('Email', 'calendar-booking'); ?></span>
            <span class="calebo-conf-value"><?php echo esc_html($email); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($telefono)) : ?>
        <div class="calebo-conf-row">
            <span class="calebo-conf-label">📱 <?php esc_html_e('Teléfono', 'calendar-booking'); ?></span>
            <span class="calebo-conf-value"><?php echo esc_html($telefono); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($reserva->num_menores) && (int)$reserva->num_menores > 0) : ?>
        <div class="calebo-conf-row">
            <span class="calebo-conf-label">🧒 <?php esc_html_e('Asistentes', 'calendar-booking'); ?></span>
            <span class="calebo-conf-value">
                <?php echo esc_html($reserva->num_menores); ?>
                <?php if (!empty($reserva->edades)) : ?>
                    &nbsp;<span style="font-weight:400;color:#888;font-size:.85em;">(<?php echo esc_html($reserva->edades); ?>)</span>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($actividad->sala)) : ?>
        <div class="calebo-conf-row">
            <span class="calebo-conf-label">🚪 <?php esc_html_e('Sala', 'calendar-booking'); ?></span>
            <span class="calebo-conf-value"><?php echo esc_html($actividad->sala); ?></span>
        </div>
        <?php endif; ?>

        <?php foreach ($campos_adic as $clave => $valor) : ?>
        <div class="calebo-conf-row">
            <span class="calebo-conf-label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $clave))); ?></span>
            <span class="calebo-conf-value"><?php echo esc_html($valor); ?></span>
        </div>
        <?php endforeach; ?>

    </div><!-- /.calebo-conf-card -->

    <?php endif; ?>

    <!-- Botón volver -->
    <div style="text-align:center;margin-top:28px;">
        <a href="<?php echo esc_url(home_url()); ?>" class="calebo-conf-btn">
            <?php esc_html_e('Volver al inicio', 'calendar-booking'); ?>
        </a>
    </div>

</div>