<?php
if (!defined('ABSPATH')) exit;

class CALEBO_SMTP {

    public static function init() {
        add_action('admin_post_calebo_guardar_smtp', [__CLASS__, 'guardar_smtp']);
        add_action('admin_post_calebo_test_smtp',    [__CLASS__, 'test_smtp']);
        self::fix_remitente();
    }

    // ── CONFIGURAR PHPMAILER ──
    public static function configurar_smtp($phpmailer) {
        $opciones = self::get_opciones();

        if (empty($opciones['activo']) || !$opciones['activo']) {
            return;
        }

        if (empty($opciones['host']) || empty($opciones['usuario']) || empty($opciones['password'])) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $opciones['host'];
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = $opciones['usuario'];
        $phpmailer->Password   = self::desencriptar($opciones['password']);
        $phpmailer->SMTPSecure = $opciones['encriptacion'] ?? 'tls';
        $phpmailer->Port       = intval($opciones['puerto'] ?? 587);

        if (!empty($opciones['nombre_remitente'])) {
            $phpmailer->FromName = $opciones['nombre_remitente'];
        }
        if (!empty($opciones['email_remitente'])) {
            $phpmailer->From = $opciones['email_remitente'];
        }
    }

    // ── GUARDAR CONFIGURACIÓN ──
    public static function guardar_smtp() {
        if (!current_user_can('manage_options')) wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'calendar-booking' ) );
        check_admin_referer('calebo_guardar_smtp');

        $opciones_actuales = self::get_opciones();

        $proveedor       = sanitize_text_field(wp_unslash($_POST['proveedor'] ?? 'personalizado'));
        $configuraciones = self::get_configuraciones_proveedor();

        if ($proveedor !== 'personalizado' && isset($configuraciones[$proveedor])) {
            $config = $configuraciones[$proveedor];
            $host   = $config['host'];
            $puerto = $config['puerto'];
            $enc    = $config['encriptacion'];
        } else {
            $host   = sanitize_text_field(wp_unslash($_POST['host']         ?? ''));
            $puerto = intval(wp_unslash($_POST['puerto']                    ?? 587));
            $enc    = sanitize_text_field(wp_unslash($_POST['encriptacion'] ?? 'tls'));
        }

        $password_raw = sanitize_text_field(wp_unslash($_POST['password'] ?? ''));
        $password     = ($password_raw === '••••••••' || empty($password_raw))
            ? ($opciones_actuales['password'] ?? '')
            : self::encriptar($password_raw);

        $opciones = [
            'proveedor'        => $proveedor,
            'host'             => $host,
            'puerto'           => $puerto,
            'encriptacion'     => $enc,
            'usuario'          => sanitize_email(wp_unslash($_POST['usuario']           ?? '')),
            'password'         => $password,
            'nombre_remitente' => sanitize_text_field(wp_unslash($_POST['nombre_remitente'] ?? '')),
            'email_remitente'  => sanitize_email(wp_unslash($_POST['email_remitente']   ?? '')),
            'activo'           => isset($_POST['activo']) ? 1 : 0,
            'email_admin'      => sanitize_email(wp_unslash($_POST['email_admin']       ?? get_option('admin_email'))),
        ];

        update_option('calebo_smtp_opciones', $opciones);

        wp_safe_redirect(esc_url_raw(wp_nonce_url(admin_url('admin.php?page=calebo-smtp&guardado=1'), 'calebo_smtp_page')));
        exit;
    }

    // ── TEST DE CORREO ──
    public static function test_smtp() {
        if (!current_user_can('manage_options')) wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'calendar-booking' ) );
        check_admin_referer('calebo_test_smtp');

        $email = sanitize_email(wp_unslash($_POST['email_test'] ?? get_option('admin_email')));

        $resultado = wp_mail(
            $email,
            '✅ Test de correo — Calendar Booking',
            '<p>Este es un correo de prueba enviado desde <strong>Calendar Booking</strong>.</p>',
            ['Content-Type: text/html; charset=UTF-8']
        );

        if ($resultado) {
            wp_safe_redirect(esc_url_raw(wp_nonce_url(admin_url('admin.php?page=calebo-smtp&test=ok&email=' . rawurlencode($email)), 'calebo_smtp_page')));
        } else {
            wp_safe_redirect(esc_url_raw(wp_nonce_url(admin_url('admin.php?page=calebo-smtp&test=error'), 'calebo_smtp_page')));
        }
        exit;
    }

    // ── PÁGINA DE CONFIGURACIÓN ──
    public static function pagina_smtp() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes para acceder a esta página.', 'calendar-booking'));
        }
        // Nonce: obligatorio cuando hay parámetros de navegación/aviso. El menú admin no requiere nonce.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Entrada verificada con check_admin_referer inmediatamente después.
        if ( isset( $_GET['guardado'] ) || isset( $_GET['test'] ) || isset( $_GET['email'] ) ) {
            check_admin_referer('calebo_smtp_page');
        }
        // phpcs:enable
        $opciones        = self::get_opciones();
        $configuraciones = self::get_configuraciones_proveedor();
        $guardado        = isset($_GET['guardado']);
        $test_ok         = isset($_GET['test']) && sanitize_text_field(wp_unslash($_GET['test'])) === 'ok';
        $test_error      = isset($_GET['test']) && sanitize_text_field(wp_unslash($_GET['test'])) === 'error';
        $email_test      = isset($_GET['email']) ? sanitize_email(wp_unslash($_GET['email'])) : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración de correo SMTP', 'calendar-booking'); ?></h1>

            <?php if ($guardado): ?>
            <div class="notice notice-success"><p>✅ <?php esc_html_e('Configuración guardada correctamente.', 'calendar-booking'); ?></p></div>
            <?php endif; ?>
            <?php if ($test_ok): ?>
            <div class="notice notice-success"><p>✅ <?php esc_html_e('Correo de prueba enviado correctamente a', 'calendar-booking'); ?> <strong><?php echo esc_html($email_test); ?></strong>.</p></div>
            <?php endif; ?>
            <?php if ($test_error): ?>
            <div class="notice notice-error"><p>❌ <?php esc_html_e('Error al enviar el correo de prueba. Revisa la configuración SMTP.', 'calendar-booking'); ?></p></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;margin-top:20px;">

                <div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('calebo_guardar_smtp'); ?>
                        <input type="hidden" name="action" value="calebo_guardar_smtp">

                        <!-- ACTIVAR/DESACTIVAR -->
                        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">⚡ <?php esc_html_e('Estado', 'calendar-booking'); ?></h3>
                            <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;">
                                <input type="checkbox" name="activo" value="1"
                                       <?php checked($opciones['activo'] ?? 0, 1); ?>
                                       style="width:18px;height:18px;">
                                <span><?php esc_html_e('Activar envío SMTP personalizado', 'calendar-booking'); ?></span>
                            </label>
                            <p style="font-size:12px;color:#888;margin:8px 0 0;">
                                <?php esc_html_e('Si está desactivado, WordPress usará su método de envío por defecto.', 'calendar-booking'); ?>
                            </p>
                        </div>

                        <!-- PROVEEDOR -->
                        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">📧 <?php esc_html_e('Proveedor de correo', 'calendar-booking'); ?></h3>
                            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                                <?php foreach ($configuraciones as $key => $config): ?>
                                <label class="calebo-smtp-proveedor" data-proveedor="<?php echo esc_attr($key); ?>"
                                       style="display:flex;flex-direction:column;align-items:center;gap:6px;
                                              cursor:pointer;padding:12px 16px;border:2px solid #e0e0e0;
                                              border-radius:8px;min-width:100px;text-align:center;
                                              transition:all .2s;">
                                    <input type="radio" name="proveedor" value="<?php echo esc_attr($key); ?>"
                                           <?php checked($opciones['proveedor'] ?? 'personalizado', $key); ?>
                                           style="display:none;">
                                    <span style="font-size:24px;"><?php echo esc_html($config['icono']); ?></span>
                                    <span style="font-size:12px;font-weight:700;"><?php echo esc_html($config['label']); ?></span>
                                </label>
                                <?php endforeach; ?>

                                <label class="calebo-smtp-proveedor" data-proveedor="personalizado"
                                       style="display:flex;flex-direction:column;align-items:center;gap:6px;
                                              cursor:pointer;padding:12px 16px;border:2px solid #e0e0e0;
                                              border-radius:8px;min-width:100px;text-align:center;
                                              transition:all .2s;">
                                    <input type="radio" name="proveedor" value="personalizado"
                                           <?php checked($opciones['proveedor'] ?? 'personalizado', 'personalizado'); ?>
                                           style="display:none;">
                                    <span style="font-size:24px;">⚙️</span>
                                    <span style="font-size:12px;font-weight:700;"><?php esc_html_e('Personalizado', 'calendar-booking'); ?></span>
                                </label>
                            </div>

                            <div id="calebo-smtp-personalizado" style="<?php echo ($opciones['proveedor'] ?? 'personalizado') !== 'personalizado' ? 'display:none' : ''; ?>">
                                <table class="form-table" style="margin:0;">
                                    <tr>
                                        <th style="width:160px;"><?php esc_html_e('Servidor SMTP', 'calendar-booking'); ?></th>
                                        <td><input type="text" name="host" class="regular-text"
                                                   value="<?php echo esc_attr($opciones['host'] ?? ''); ?>"
                                                   placeholder="smtp.tudominio.com"></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e('Puerto', 'calendar-booking'); ?></th>
                                        <td>
                                            <input type="number" name="puerto" style="width:100px;"
                                                   value="<?php echo esc_attr($opciones['puerto'] ?? 587); ?>">
                                            <span style="font-size:12px;color:#888;margin-left:8px;">
                                                587 (TLS) · 465 (SSL) · 25 <?php esc_html_e('(sin cifrado)', 'calendar-booking'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e('Encriptación', 'calendar-booking'); ?></th>
                                        <td>
                                            <select name="encriptacion">
                                                <option value="tls" <?php selected($opciones['encriptacion'] ?? 'tls', 'tls'); ?>><?php esc_html_e('TLS (recomendado)', 'calendar-booking'); ?></option>
                                                <option value="ssl" <?php selected($opciones['encriptacion'] ?? 'tls', 'ssl'); ?>>SSL</option>
                                                <option value=""    <?php selected($opciones['encriptacion'] ?? 'tls', ''); ?>><?php esc_html_e('Sin cifrado', 'calendar-booking'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- CREDENCIALES -->
                        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">🔐 <?php esc_html_e('Credenciales', 'calendar-booking'); ?></h3>
                            <table class="form-table" style="margin:0;">
                                <tr>
                                    <th style="width:160px;"><?php esc_html_e('Usuario / Email', 'calendar-booking'); ?></th>
                                    <td><input type="email" name="usuario" class="regular-text"
                                               value="<?php echo esc_attr($opciones['usuario'] ?? ''); ?>"
                                               placeholder="tu@email.com"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Contraseña', 'calendar-booking'); ?></th>
                                    <td>
                                        <input type="password" name="password" class="regular-text"
                                               value="<?php echo !empty($opciones['password']) ? '••••••••' : ''; ?>"
                                               placeholder="<?php esc_attr_e('Contraseña o contraseña de aplicación', 'calendar-booking'); ?>"
                                               autocomplete="new-password">
                                        <p style="font-size:11px;color:#888;margin:4px 0 0;">
                                            <?php esc_html_e('Para Gmail usa una contraseña de aplicación, no tu contraseña normal.', 'calendar-booking'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- REMITENTE -->
                        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">✉️ <?php esc_html_e('Datos del remitente', 'calendar-booking'); ?></h3>
                            <table class="form-table" style="margin:0;">
                                <tr>
                                    <th style="width:160px;"><?php esc_html_e('Nombre del remitente', 'calendar-booking'); ?></th>
                                    <td><input type="text" name="nombre_remitente" class="regular-text"
                                               value="<?php echo esc_attr($opciones['nombre_remitente'] ?? get_bloginfo('name')); ?>"
                                               placeholder="<?php esc_attr_e('Nombre que verá el destinatario', 'calendar-booking'); ?>"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Email del remitente', 'calendar-booking'); ?></th>
                                    <td><input type="email" name="email_remitente" class="regular-text"
                                               value="<?php echo esc_attr($opciones['email_remitente'] ?? get_option('admin_email')); ?>"
                                               placeholder="email@tudominio.com"></td>
                                </tr>
                            </table>
                        </div>

                        <!-- EMAIL ADMINISTRADOR -->
                        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:20px;">
                            <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">👤 <?php esc_html_e('Email del administrador', 'calendar-booking'); ?></h3>
                            <table class="form-table" style="margin:0;">
                                <tr>
                                    <th style="width:160px;"><?php esc_html_e('Recibir reservas en', 'calendar-booking'); ?></th>
                                    <td>
                                        <input type="email" name="email_admin" class="regular-text"
                                               value="<?php echo esc_attr($opciones['email_admin'] ?? get_option('admin_email')); ?>"
                                               placeholder="admin@tudominio.com">
                                        <p style="font-size:11px;color:#888;margin:4px 0 0;">
                                            <?php esc_html_e('Este email recibirá las notificaciones de nuevas reservas y el informe diario.', 'calendar-booking'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <input type="submit" class="button button-primary button-large"
                               value="<?php esc_attr_e('💾 Guardar configuración', 'calendar-booking'); ?>">
                    </form>
                </div>

                <!-- PANEL LATERAL -->
                <div style="position:sticky;top:32px;">

                    <!-- TEST DE CORREO -->
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 12px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">🧪 <?php esc_html_e('Enviar correo de prueba', 'calendar-booking'); ?></h3>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('calebo_test_smtp'); ?>
                            <input type="hidden" name="action" value="calebo_test_smtp">
                            <input type="email" name="email_test" class="regular-text"
                                   value="<?php echo esc_attr(get_option('admin_email')); ?>"
                                   placeholder="tu@email.com"
                                   style="width:100%;margin-bottom:8px;">
                            <input type="submit" class="button button-secondary" style="width:100%;"
                                   value="<?php esc_attr_e('📨 Enviar prueba', 'calendar-booking'); ?>">
                        </form>
                    </div>

                    <!-- GUÍA GMAIL -->
                    <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:16px;">
                        <h4 style="margin:0 0 8px;font-size:13px;color:#f57f17;">💡 <?php esc_html_e('Cómo configurar Gmail', 'calendar-booking'); ?></h4>
                        <ol style="font-size:12px;color:#666;margin:0;padding-left:16px;line-height:1.8;">
                            <li><?php esc_html_e('Ve a tu cuenta de Google', 'calendar-booking'); ?></li>
                            <li><?php esc_html_e('Activa la verificación en dos pasos', 'calendar-booking'); ?></li>
                            <li><?php esc_html_e('Ve a Seguridad > Contraseñas de aplicaciones', 'calendar-booking'); ?></li>
                            <li><?php esc_html_e('Crea una contraseña para "Correo"', 'calendar-booking'); ?></li>
                            <li><?php esc_html_e('Usa esa contraseña aquí', 'calendar-booking'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Correo SMTP: JS externalizado a assets/js/admin-smtp.js (se encola con admin_enqueue_scripts).
        // Solo se inyecta el objeto de datos dinámicos ANTES del script externo (posición 'before').
        wp_add_inline_script(
            'calebo-admin-smtp',
            'var caleboSmtpData = ' . wp_json_encode( [
                'proveedores'     => $configuraciones,
                'proveedorActivo' => $opciones['proveedor'] ?? 'personalizado',
            ] ) . ';',
            'before'
        );
        ?>
        <?php
    }

    // ── HELPERS ──
    public static function get_opciones() {
        return get_option('calebo_smtp_opciones', []);
    }

    public static function get_configuraciones_proveedor() {
        return [
            'gmail' => [
                'label'        => 'Gmail',
                'icono'        => '📮',
                'host'         => 'smtp.gmail.com',
                'puerto'       => 587,
                'encriptacion' => 'tls',
            ],
            'outlook' => [
                'label'        => 'Outlook',
                'icono'        => '📧',
                'host'         => 'smtp.office365.com',
                'puerto'       => 587,
                'encriptacion' => 'tls',
            ],
            'hotmail' => [
                'label'        => 'Hotmail',
                'icono'        => '📩',
                'host'         => 'smtp.live.com',
                'puerto'       => 587,
                'encriptacion' => 'tls',
            ],
            'yahoo' => [
                'label'        => 'Yahoo',
                'icono'        => '📬',
                'host'         => 'smtp.mail.yahoo.com',
                'puerto'       => 587,
                'encriptacion' => 'tls',
            ],
        ];
    }

    public static function fix_remitente() {
        $opciones = self::get_opciones();
        if (!empty($opciones['email_remitente'])) {
            add_filter('wp_mail_from',      function() use ($opciones) { return $opciones['email_remitente']; });
            add_filter('wp_mail_from_name', function() use ($opciones) { return $opciones['nombre_remitente'] ?? get_bloginfo('name'); });
        }
    }

    private static function encriptar($texto) {
        // FIX 3: IV aleatorio por cifrado (prefijo 'v2:' para distinción de formato)
        if (empty($texto) || $texto === '••••••••') return '';
        $clave   = substr(hash('sha256', wp_salt('auth')), 0, 32);
        $iv      = random_bytes(16);
        $cifrado = openssl_encrypt($texto, 'AES-256-CBC', $clave, OPENSSL_RAW_DATA, $iv);
        return 'v2:' . base64_encode($iv . $cifrado);
    }

    private static function desencriptar($texto_cifrado) {
        // FIX 3: Soporta nuevo formato (v2: IV aleatorio) y legado (IV estático)
        if (empty($texto_cifrado)) return '';
        $clave = substr(hash('sha256', wp_salt('auth')), 0, 32);
        if (strncmp($texto_cifrado, 'v2:', 3) === 0) {
            // Formato nuevo: primeros 16 bytes = IV aleatorio
            $decoded = base64_decode(substr($texto_cifrado, 3));
            $iv      = substr($decoded, 0, 16);
            $cifrado = substr($decoded, 16);
            return openssl_decrypt($cifrado, 'AES-256-CBC', $clave, OPENSSL_RAW_DATA, $iv);
        }
        // Fallback legado: IV estático derivado de wp_salt (compatibilidad con contraseñas guardadas antes del fix)
        $clave_legacy = wp_salt('auth');
        return openssl_decrypt(base64_decode($texto_cifrado), 'AES-256-CBC', $clave_legacy, 0, substr($clave_legacy, 0, 16));
    }
}
