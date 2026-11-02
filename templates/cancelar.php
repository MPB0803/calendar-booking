<?php if (!defined('ABSPATH')) exit;

// El token solo se usa para LEER la reserva y mostrar el formulario de confirmación.
// La ACCIÓN de cancelar se ejecuta únicamente via POST (con nonce wp_nonce_field).
// Nunca cancelar por GET para evitar que herramientas (crawlers, proxies, prefetch)
// ejecuten la cancelación al visitar el enlace.
$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
// Validar formato del token (hex de 32 chars) para rechazar valores inesperados
if ( $token && ! preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
    $token = '';
}
$mensaje = '';
$error   = '';
$reserva = null;

if ($token) {
    $reserva = CALEBO_Database::get_reserva_por_token($token);
    if (!$reserva || $reserva->estado !== 'activa') {
        // Mensaje genérico e idéntico para token inexistente y reserva ya cancelada:
        // no revela si el token existe ni su estado (oráculo de validez, hallazgo 3.2).
        $mensaje = __('No hemos encontrado una reserva pendiente con este enlace. Si tu reserva ya estaba procesada, no se necesita ninguna acción.', 'calendar-booking');
        $reserva = null;
    }
}

if (isset($_POST['calebo_confirmar_cancelacion']) && $reserva && $reserva->estado === 'activa') {
    $_POST = wp_unslash($_POST);
    check_admin_referer('calebo_cancelar_' . $token);
    CALEBO_Database::cancelar_por_token($token);
    CALEBO_Notifications::enviar_cancelacion_cliente($reserva);
    $mensaje = __('✅ Tu reserva ha sido cancelada correctamente. Te hemos enviado un correo de confirmación.', 'calendar-booking');
    $reserva = null;
}
?>

<div style="font-family:'Montserrat',Arial,sans-serif;max-width:560px;margin:40px auto;padding:20px;">

    <?php if ($error): ?>
        <div style="background:#ffebee;border:1px solid #ef9a9a;border-radius:8px;
                    padding:16px;color:#c62828;font-weight:700;text-align:center;">
            <?php echo esc_html($error); ?>
        </div>

    <?php elseif ($mensaje): ?>
        <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;
                    padding:16px;color:#2e7d32;font-weight:700;text-align:center;">
            <?php echo esc_html($mensaje); ?>
        </div>
        <p style="text-align:center;margin-top:16px;">
            <a href="<?php echo esc_url(home_url()); ?>"
               style="color:#F7941D;font-weight:700;text-decoration:none;">
                ← <?php esc_html_e('Volver al inicio', 'calendar-booking'); ?>
            </a>
        </p>

    <?php elseif ($reserva): ?>
        <h2 style="font-size:1.3rem;font-weight:800;color:#2D2D2D;
                   text-transform:uppercase;margin-bottom:20px;">
            <?php esc_html_e('Cancelar reserva', 'calendar-booking'); ?>
        </h2>

        <div style="background:#fff;border:1px solid #f0f0f0;border-radius:8px;
                    overflow:hidden;margin-bottom:24px;box-shadow:0 2px 8px rgba(0,0,0,.05);">
            <div style="background:#2D2D2D;padding:10px 16px;">
                <span style="color:#F7941D;font-size:11px;font-weight:800;
                             text-transform:uppercase;letter-spacing:1.5px;">
                    <?php esc_html_e('Detalles de tu reserva', 'calendar-booking'); ?>
                </span>
            </div>
            <div style="padding:16px;">
                <p style="margin:6px 0;font-size:13px;">
                    📅 <strong><?php esc_html_e('Fecha:', 'calendar-booking'); ?></strong> <?php echo esc_html(date_i18n('d/m/Y', strtotime($reserva->fecha))); ?>
                </p>
                <p style="margin:6px 0;font-size:13px;">
                    🕐 <strong><?php esc_html_e('Horario:', 'calendar-booking'); ?></strong> <?php echo esc_html($reserva->horario); ?>
                </p>
                <p style="margin:6px 0;font-size:13px;">
                    👤 <strong><?php esc_html_e('Nombre:', 'calendar-booking'); ?></strong> <?php echo esc_html($reserva->nombre); ?>
                </p>
                <p style="margin:6px 0;font-size:13px;">
                    👦 <strong><?php esc_html_e('Menores:', 'calendar-booking'); ?></strong> <?php echo esc_html($reserva->num_menores); ?>
                </p>
            </div>
        </div>

        <p style="color:#666;font-size:13px;margin-bottom:20px;">
            <?php esc_html_e('¿Estás seguro de que quieres cancelar esta reserva? Esta acción no se puede deshacer.', 'calendar-booking'); ?>
        </p>

        <form method="post">
            <?php wp_nonce_field('calebo_cancelar_' . $token); ?>
            <input type="hidden" name="calebo_confirmar_cancelacion" value="1">
            <button type="submit"
                    style="width:100%;padding:13px;background:#c62828;color:#fff;
                           border:none;border-radius:6px;font-family:inherit;
                           font-size:.95rem;font-weight:800;cursor:pointer;
                           text-transform:uppercase;letter-spacing:.5px;">
                <?php esc_html_e('Sí, cancelar mi reserva', 'calendar-booking'); ?>
            </button>
        </form>

        <p style="text-align:center;margin-top:12px;">
            <a href="<?php echo esc_url(home_url()); ?>"
               style="color:#F7941D;font-size:13px;font-weight:700;text-decoration:none;">
                ← <?php esc_html_e('Volver al inicio', 'calendar-booking'); ?>
            </a>
        </p>

    <?php else: ?>
        <div style="text-align:center;padding:40px 20px;color:#888;">
            <p style="font-size:13px;">
                <?php esc_html_e('Para cancelar una reserva necesitas el enlace que te enviamos por correo.', 'calendar-booking'); ?>
            </p>
        </div>
    <?php endif; ?>

</div>