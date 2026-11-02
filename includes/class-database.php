<?php
if (!defined('ABSPATH')) exit;

class CALEBO_Database {

    public static function instalar() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}calebo_calendarios (
            id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre            VARCHAR(200) NOT NULL,
            descripcion       TEXT NULL,
            limite            INT(11) NOT NULL DEFAULT 20,
            horario_normal    VARCHAR(20) NOT NULL DEFAULT '16:00-20:00',
            horario_vacaciones VARCHAR(20) NOT NULL DEFAULT '10:00-14:00',
            vacaciones        TEXT NULL,
            festivos          TEXT NULL,
            dias_disponibles  VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
            meses_max         INT(11) NOT NULL DEFAULT 3,
            color_primary     VARCHAR(7) NOT NULL DEFAULT '#F7941D',
            color_secondary   VARCHAR(7) NOT NULL DEFAULT '#1E3FA3',
            logo_url          VARCHAR(500) NULL,
            diseno            TEXT NULL,
            activo            TINYINT(1) NOT NULL DEFAULT 1,
            dia_activacion    INT(11) NOT NULL DEFAULT 0,
            mes_inicio_ciclo  INT(11) NOT NULL DEFAULT 1,
            creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;");

        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}calebo_reservas (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            calendario_id BIGINT(20) UNSIGNED NOT NULL,
            actividad_id  BIGINT(20) UNSIGNED NULL,
            fecha         DATE NOT NULL,
            horario       VARCHAR(20) NOT NULL,
            nombre        VARCHAR(200) NOT NULL DEFAULT '',
            email         VARCHAR(200) NOT NULL DEFAULT '',
            telefono      VARCHAR(50) NOT NULL DEFAULT '',
            num_menores   INT(11) NOT NULL DEFAULT 1,
            edades        VARCHAR(200) NULL,
            estado        VARCHAR(20) NOT NULL DEFAULT 'activa',
            datos_extra   LONGTEXT NULL,
            token         VARCHAR(64) NOT NULL DEFAULT '',
            creada_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY calendario_id (calendario_id),
            KEY actividad_id (actividad_id),
            KEY fecha (fecha),
            KEY estado (estado),
            UNIQUE KEY token (token),
            KEY email (email)
        ) $charset;");

        // Migración segura: añadir actividad_id si no existe (instalaciones previas)
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}calebo_reservas LIKE 'actividad_id'");
        if (empty($cols)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_reservas
                ADD COLUMN actividad_id BIGINT(20) UNSIGNED NULL AFTER calendario_id,
                ADD KEY actividad_id (actividad_id)");
        }
        // Migración segura: añadir datos_extra si no existe
        $cols_de = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}calebo_reservas LIKE 'datos_extra'");
        if (empty($cols_de)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_reservas ADD COLUMN datos_extra LONGTEXT NULL AFTER estado");
        }

        // Migración: añadir índice email si no existe
        $idx_email_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = %s AND table_name = %s AND index_name = %s",
            DB_NAME, $wpdb->prefix . 'calebo_reservas', 'email'
        ));
        if (!$idx_email_exists) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Literal SQL con palabra clave 'email'.
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_reservas ADD INDEX email (email)");
        }

        // Mejora: índice compuesto para consultas de disponibilidad
        $idx_name = 'idx_cal_fecha_estado';
        $idx_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = %s AND table_name = %s AND index_name = %s",
            DB_NAME, $wpdb->prefix . 'calebo_reservas', $idx_name
        ));
        if (!$idx_exists) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $idx_name es un literal constante verificado por whitelist.
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_reservas ADD INDEX {$idx_name} (calendario_id, fecha, estado)");
        }

        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}calebo_actividades (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            calendario_id BIGINT(20) UNSIGNED NOT NULL,
            fecha_inicio  DATE NOT NULL,
            fecha_fin     DATE NOT NULL,
            nombre        VARCHAR(200) NOT NULL,
            horario       VARCHAR(50) NOT NULL DEFAULT '',
            sala          VARCHAR(200) NULL,
            limite        INT(11) NOT NULL DEFAULT 20,
            descripcion   TEXT NULL,
            activo        TINYINT(1) NOT NULL DEFAULT 1,
            campos_extra  LONGTEXT NULL,
            creada_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY calendario_id (calendario_id),
            KEY fecha_inicio (fecha_inicio),
            KEY fecha_fin (fecha_fin)
        ) $charset;");

        // Migración: si existe la columna 'fecha' pero no 'fecha_inicio', migrar
        $cols_fi = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}calebo_actividades LIKE 'fecha_inicio'");
        $cols_f  = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}calebo_actividades LIKE 'fecha'");
        if (empty($cols_fi) && !empty($cols_f)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN fecha_inicio DATE NOT NULL AFTER calendario_id");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN fecha_fin DATE NOT NULL AFTER fecha_inicio");
            $wpdb->query("UPDATE {$wpdb->prefix}calebo_actividades SET fecha_inicio = fecha, fecha_fin = fecha");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_actividades ADD KEY fecha_inicio (fecha_inicio)");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_actividades ADD KEY fecha_fin (fecha_fin)");
        }

        // Migración segura: añadir campos_extra a calebo_actividades si no existe
        $cols_ce = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}calebo_actividades LIKE 'campos_extra'");
        if (empty($cols_ce)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN campos_extra LONGTEXT NULL AFTER descripcion");
        }

        // Migración segura: añadir columnas de configuración por actividad.
        // Whitelist estricta de (nombre_columna => definición) para evitar inyección SQL.
        // Los nombres y definiciones son literales hardcodeados en esta clase; NUNCA usar
        // valores dinámicos externos aquí. Se usa information_schema con prepare() para
        // la comprobación de existencia, en lugar de interpolar la clave en SHOW COLUMNS.
        $config_cols_whitelist = [
            'horario_vacaciones'      => 'VARCHAR(50) NULL',
            'usar_horario_vacaciones' => 'TINYINT(1) NULL',
            'meses_max'               => 'INT(11) NULL',
            'dia_activacion'          => 'INT(11) NULL',
            'mes_inicio_ciclo'        => 'INT(11) NULL',
        ];
        foreach ($config_cols_whitelist as $col_name => $col_def) {
            // Defensa adicional: validar que el nombre solo contiene caracteres seguros
            if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $col_name ) ) {
                continue;
            }
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, $wpdb->prefix . 'calebo_actividades', $col_name
            ) );
            if ( ! $exists ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $col_name/$col_def son literales de la whitelist, validados con preg_match() arriba.
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN {$col_name} {$col_def}" );
            }
        }

        // Migración: añadir columnas festivos y vacaciones por actividad
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SHOW COLUMNS con nombre de columna literal.
        $cols_festivos = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}calebo_actividades LIKE 'festivos'");
        if (empty($cols_festivos)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ALTER con nombre de columna literal whitelist.
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN festivos LONGTEXT NULL AFTER campos_extra");
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SHOW COLUMNS con nombre de columna literal.
        $cols_vacaciones = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}calebo_actividades LIKE 'vacaciones'");
        if (empty($cols_vacaciones)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ALTER con nombre de columna literal whitelist.
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN vacaciones LONGTEXT NULL AFTER festivos");
        }

        // Migración: añadir columna lista_espera por actividad
        $cols_le = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}calebo_actividades LIKE 'lista_espera'");
        if (empty($cols_le)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN lista_espera TINYINT(1) NOT NULL DEFAULT 0");
        }

        // Mantener tabla calebo_campos para compatibilidad (no se usa en nueva versión)
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}calebo_campos (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            calendario_id BIGINT(20) UNSIGNED NOT NULL,
            nombre        VARCHAR(200) NOT NULL,
            etiqueta      VARCHAR(200) NOT NULL,
            tipo          VARCHAR(50) NOT NULL DEFAULT 'text',
            placeholder   VARCHAR(200) NULL,
            opciones      TEXT NULL,
            obligatorio   TINYINT(1) NOT NULL DEFAULT 1,
            orden         INT(11) NOT NULL DEFAULT 0,
            activo        TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY calendario_id (calendario_id)
        ) $charset;");

        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}calebo_espera (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            calendario_id BIGINT(20) UNSIGNED NOT NULL,
            actividad_id  BIGINT(20) UNSIGNED NOT NULL,
            fecha         DATE NOT NULL,
            nombre        VARCHAR(200) NOT NULL DEFAULT '',
            email         VARCHAR(200) NOT NULL DEFAULT '',
            telefono      VARCHAR(50)  NOT NULL DEFAULT '',
            num_menores   INT(11)      NOT NULL DEFAULT 1,
            edades        VARCHAR(200) NULL,
            datos_extra   LONGTEXT     NULL,
            creada_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notificado    TINYINT(1)   NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_espera_act_fecha (actividad_id, fecha),
            KEY idx_espera_email (email)
        ) $charset;");

        // Migración segura: añadir columnas de datos completos a calebo_espera si no existen.
        // Whitelist hardcodeada; se usa information_schema con prepare() para la comprobación.
        $espera_cols_whitelist = [
            'num_menores' => 'INT(11) NOT NULL DEFAULT 1',
            'edades'      => 'VARCHAR(200) NULL',
            'datos_extra' => 'LONGTEXT NULL',
        ];
        foreach ( $espera_cols_whitelist as $col_espera => $col_def_espera ) {
            if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $col_espera ) ) {
                continue;
            }
            $col_existe = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, $wpdb->prefix . 'calebo_espera', $col_espera
            ) );
            if ( ! $col_existe ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $col_espera/$col_def_espera son literales de la whitelist, validados con preg_match() arriba.
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_espera ADD COLUMN {$col_espera} {$col_def_espera}" );
            }
        }

        update_option('calebo_version', CALEBO_VERSION);
    }

    /**
     * Ejecuta SOLO las migraciones pendientes para la versión actual.
     * Cada bloque de migración se ejecuta una única vez gracias al sistema de versiones.
     * Se llama en cada plugins_loaded (calebo_init).
     *
     * Añadir una migración nueva:
     *   1. Incrementar CALEBO_VERSION en calendar-booking.php.
     *   2. Añadir un bloque `if (version_compare($stored, 'X.Y.Z', '<'))` aquí.
     */
    public static function maybe_migrate() {
        $stored = get_option( 'calebo_version', '0' );
        if ( version_compare( $stored, CALEBO_VERSION, '>=' ) ) {
            return; // Ya está actualizado — salida rápida sin tocar BD
        }

        // Bloqueo de 60 s para evitar ejecuciones simultáneas en peticiones concurrentes
        if ( get_transient( 'calebo_migrating' ) ) {
            return;
        }
        set_transient( 'calebo_migrating', 1, 60 );

        try {
            // ── Migraciones acumulativas por versión ──────────────────────────────
            // Cada bloque se ejecuta UNA vez cuando se sube de versión.
            // instalar() ya incluye dbDelta + ALTER TABLE idempotentes para instalaciones
            // nuevas; estos bloques son para lógica que solo debe correr al actualizar.

            // v1.0.0 → cualquier versión posterior: crear tablas base (idempotente).
            if ( version_compare( $stored, '1.0.0', '<' ) ) {
                self::instalar_tablas_base();
            }

            // v1.2.0: añadir actividad_id y datos_extra a calebo_reservas.
            if ( version_compare( $stored, '1.2.0', '<' ) ) {
                self::migrar_1_2_0();
            }

            // v1.3.0: modelo de actividades con rangos de fechas (fecha_inicio/fecha_fin).
            if ( version_compare( $stored, '1.3.0', '<' ) ) {
                self::migrar_1_3_0();
            }

            // v1.3.5: columnas de configuración por actividad + lista de espera.
            if ( version_compare( $stored, '1.3.5', '<' ) ) {
                self::migrar_1_3_5();
            }

            // v1.3.9: índice compuesto en calebo_actividades (calendario_id, fecha_inicio, activo).
            if ( version_compare( $stored, '1.3.9', '<' ) ) {
                self::migrar_1_3_9();
            }

            // v2.0.0: refactor MVC, CSS minificado, tests y generación PDF real.
            // No requiere cambios de BD; solo registra la versión.
            if ( version_compare( $stored, '2.0.0', '<' ) ) {
                self::migrar_2_0_0();
            }

            // v2.0.1: índice UNIQUE en token de calebo_reservas (seguridad + integridad).
            if ( version_compare( $stored, '2.0.1', '<' ) ) {
                self::migrar_2_0_1();
            }

            // ── Fin de migraciones ────────────────────────────────────────────────

        } catch ( \Throwable $e ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error de migración; se registra en log del servidor para diagnóstico.
            error_log( 'CalendiFlow: error en maybe_migrate v' . CALEBO_VERSION . ': ' . $e->getMessage() );
        } finally {
            delete_transient( 'calebo_migrating' );
            update_option( 'calebo_version', CALEBO_VERSION );
        }
    }

    // ── Helpers de migración por versión ─────────────────────────────────────

    /** Crea/actualiza las tablas base (idempotente). Alias de instalar(). */
    private static function instalar_tablas_base() {
        self::instalar();
    }

    /** v1.2.0: actividad_id, datos_extra en calebo_reservas + índice compuesto. */
    private static function migrar_1_2_0() {
        global $wpdb;
        $cols_ai = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='actividad_id'",
            DB_NAME, $wpdb->prefix . 'calebo_reservas'
        ) );
        if ( ! $cols_ai ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_reservas ADD COLUMN actividad_id BIGINT(20) UNSIGNED NULL AFTER calendario_id, ADD KEY actividad_id (actividad_id)" );
        }
        $cols_de = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='datos_extra'",
            DB_NAME, $wpdb->prefix . 'calebo_reservas'
        ) );
        if ( ! $cols_de ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_reservas ADD COLUMN datos_extra LONGTEXT NULL AFTER estado" );
        }
        $idx = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME='idx_cal_fecha_estado'",
            DB_NAME, $wpdb->prefix . 'calebo_reservas'
        ) );
        if ( ! $idx ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_reservas ADD INDEX idx_cal_fecha_estado (calendario_id, fecha, estado)" );
        }
    }

    /** v1.3.0: migrar columna 'fecha' → 'fecha_inicio'+'fecha_fin' en calebo_actividades. */
    private static function migrar_1_3_0() {
        global $wpdb;
        $has_fi = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='fecha_inicio'",
            DB_NAME, $wpdb->prefix . 'calebo_actividades'
        ) );
        $has_f  = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='fecha'",
            DB_NAME, $wpdb->prefix . 'calebo_actividades'
        ) );
        if ( ! $has_fi && $has_f ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN fecha_inicio DATE NOT NULL AFTER calendario_id" );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN fecha_fin DATE NOT NULL AFTER fecha_inicio" );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "UPDATE {$wpdb->prefix}calebo_actividades SET fecha_inicio = fecha, fecha_fin = fecha" );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_actividades ADD KEY fecha_inicio (fecha_inicio), ADD KEY fecha_fin (fecha_fin)" );
        }
        // campos_extra
        $has_ce = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='campos_extra'",
            DB_NAME, $wpdb->prefix . 'calebo_actividades'
        ) );
        if ( ! $has_ce ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN campos_extra LONGTEXT NULL AFTER descripcion" );
        }
    }

    /** v1.3.5: columnas de config por actividad, festivos, vacaciones, lista_espera + calebo_espera. */
    private static function migrar_1_3_5() {
        global $wpdb;
        $nuevas_cols = [
            'horario_vacaciones'      => 'VARCHAR(50) NULL',
            'usar_horario_vacaciones' => 'TINYINT(1) NULL',
            'meses_max'               => 'INT(11) NULL',
            'dia_activacion'          => 'INT(11) NULL',
            'mes_inicio_ciclo'        => 'INT(11) NULL',
            'festivos'                => 'LONGTEXT NULL',
            'vacaciones'              => 'LONGTEXT NULL',
            'lista_espera'            => 'TINYINT(1) NOT NULL DEFAULT 0',
        ];
        foreach ( $nuevas_cols as $col => $def ) {
            if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $col ) ) continue;
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME=%s",
                DB_NAME, $wpdb->prefix . 'calebo_actividades', $col
            ) );
            if ( ! $exists ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $col/$def son literales de la whitelist, validados con preg_match() arriba.
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_actividades ADD COLUMN {$col} {$def}" );
            }
        }
        // Columnas nuevas en calebo_espera
        $espera_cols = [
            'num_menores' => 'INT(11) NOT NULL DEFAULT 1',
            'edades'      => 'VARCHAR(200) NULL',
            'datos_extra' => 'LONGTEXT NULL',
        ];
        foreach ( $espera_cols as $col => $def ) {
            if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $col ) ) continue;
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME=%s",
                DB_NAME, $wpdb->prefix . 'calebo_espera', $col
            ) );
            if ( ! $exists ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $col/$def son literales de la whitelist, validados con preg_match() arriba.
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_espera ADD COLUMN {$col} {$def}" );
            }
        }
    }

    /**
     * v1.3.9: añadir índice compuesto en calebo_actividades (calendario_id, fecha_inicio, activo).
     * Mejora el rendimiento de get_actividades_fecha() y get_actividades_rango() en
     * instalaciones con muchas actividades.
     */
    private static function migrar_1_3_9() {
        global $wpdb;
        $idx_name = 'idx_act_cal_fechas_activo';
        $idx_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME=%s",
            DB_NAME, $wpdb->prefix . 'calebo_actividades', $idx_name
        ) );
        if ( ! $idx_exists ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $idx_name es literal constante verificado por whitelist.
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_actividades ADD INDEX {$idx_name} (calendario_id, fecha_inicio, activo)" );
        }
    }

    /**
     * v2.0.0: registro de versión — los cambios son de código (MVC, CSS minificado,
     * tests, PDF real). No se requieren alteraciones de esquema de BD.
     */
    private static function migrar_2_0_0() {
        // Sin cambios de BD en esta versión.
        // El número de versión queda registrado por maybe_migrate() en calebo_version.
    }

    /**
     * v2.0.1: índice UNIQUE en token de calebo_reservas para evitar duplicados
     * y mejorar seguridad de cancelaciones.
     */
    private static function migrar_2_0_1() {
        global $wpdb;
        $idx_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME=%s",
            DB_NAME, $wpdb->prefix . 'calebo_reservas', 'token'
        ) );
        if ( ! $idx_exists ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_reservas ADD UNIQUE KEY token (token)" );
        } else {
            // Verificar si ya es UNIQUE o solo KEY
            $is_unique = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME=%s AND NON_UNIQUE=0",
                DB_NAME, $wpdb->prefix . 'calebo_reservas', 'token'
            ) );
            if ( ! $is_unique ) {
                // Eliminar índice existente y crear UNIQUE
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}calebo_reservas DROP INDEX token, ADD UNIQUE KEY token (token)" );
            }
        }
    }

    public static function desactivar() {
        wp_clear_scheduled_hook('calebo_email_diario');
    }

    public static function get_calendarios() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}calebo_calendarios WHERE activo = 1 ORDER BY id ASC"
        );
    }

    public static function get_calendario($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_calendarios WHERE id = %d", $id
        ));
    }

    public static function get_reservas_fecha($calendario_id, $fecha) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_reservas
             WHERE calendario_id = %d AND fecha = %s AND estado = 'activa'
             ORDER BY id ASC",
            $calendario_id, $fecha
        ));
    }

    public static function get_reservas_rango($calendario_id, $fecha_inicio, $fecha_fin) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_reservas
             WHERE calendario_id = %d AND fecha BETWEEN %s AND %s AND estado = 'activa'
             ORDER BY fecha ASC, id ASC",
            $calendario_id, $fecha_inicio, $fecha_fin
        ));
    }

    public static function get_totales_rango($calendario_id, $fecha_inicio, $fecha_fin) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT fecha, SUM(num_menores) as total_menores, COUNT(*) as total_reservas
             FROM {$wpdb->prefix}calebo_reservas
             WHERE calendario_id = %d AND fecha BETWEEN %s AND %s AND estado = 'activa'
             GROUP BY fecha ORDER BY fecha ASC",
            $calendario_id, $fecha_inicio, $fecha_fin
        ));
    }

    public static function guardar_reserva($data) {
        global $wpdb;
        $data['token'] = bin2hex(random_bytes(16));
        $wpdb->insert("{$wpdb->prefix}calebo_reservas", $data);
        return $wpdb->insert_id ? $data['token'] : false;
    }

    public static function cancelar_por_token($token) {
        global $wpdb;

        // Get reservation data before cancelling
        $reserva = self::get_reserva_por_token($token);

        $result = $wpdb->update(
            "{$wpdb->prefix}calebo_reservas",
            ['estado' => 'cancelada'],
            ['token'  => $token],
            ['%s'], ['%s']
        );

        // Auto-asignación de plaza al primer usuario en lista de espera
        if ($result && $reserva && !empty($reserva->actividad_id)) {
            self::procesar_espera_automatica($reserva);
        }

        return $result;
    }

    /**
     * Promociona usuarios de la lista de espera mientras queden plazas libres.
     *
     * Recorre la cola en orden de inscripción y asigna plaza a cada usuario cuyo
     * num_menores cabe en las plazas disponibles en ese momento. Sigue iterando
     * hasta que no queden plazas o ningún candidato restante quepa.
     * Los usuarios que no caben permanecen en la lista para futuras cancelaciones.
     */
    public static function procesar_espera_automatica($reserva_cancelada) {
        global $wpdb;

        $calendario = self::get_calendario($reserva_cancelada->calendario_id);
        $actividad  = self::get_actividad($reserva_cancelada->actividad_id);

        if (!$calendario || !$actividad) return false;

        $campos_config = json_decode($actividad->campos_extra ?? '[]', true) ?: [];
        $limite        = (int) $actividad->limite;
        $hubo_asignacion = false;
        $commit_ok        = false;

        // Reservas promovidas pendientes de notificar. Los emails se envían TRAS el
        // COMMIT para que un ROLLBACK (deadlock, fallo de inserción) no deje a un
        // usuario con un email "plaza confirmada" que luego no se materializa en BD.
        $pendientes_email = [];

        // Bloqueo atómico para prevenir race conditions entre cancelaciones concurrentes
        $lock_key = 'calebo_espera_lock_' . $reserva_cancelada->actividad_id . '_' . $reserva_cancelada->fecha;
        if ( ! get_transient( $lock_key ) ) {
            set_transient( $lock_key, 1, 30 ); // 30 segundos máximo
        } else {
            // Otro proceso está manejando la cola, salir
            return false;
        }

        $wpdb->query('START TRANSACTION');

        try {
            // Bucle: seguir asignando mientras haya plazas y candidatos que quepan
            while (true) {

                // Recalcular plazas libres en cada iteración con bloqueo FOR UPDATE
                $ocupadas_actuales = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(num_menores), 0)
                     FROM {$wpdb->prefix}calebo_reservas
                     WHERE calendario_id = %d AND actividad_id = %d AND fecha = %s AND estado = 'activa'
                     FOR UPDATE",
                    $reserva_cancelada->calendario_id,
                    $reserva_cancelada->actividad_id,
                    $reserva_cancelada->fecha
                ));
                $plazas_libres = max(0, $limite - $ocupadas_actuales);

                if ($plazas_libres <= 0) break;

                // Obtener la cola pendiente en orden de inscripción (con SKIP LOCKED si está disponible)
                $skip_locked = ( $wpdb->get_var( "SELECT VERSION()" ) >= '8.0.1' ) ? 'FOR UPDATE SKIP LOCKED' : 'FOR UPDATE';
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $skip_locked es literal SQL ('FOR UPDATE SKIP LOCKED' o 'FOR UPDATE').
                $cola = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}calebo_espera
                     WHERE actividad_id = %d AND fecha = %s AND notificado = 0
                     ORDER BY creada_en ASC
                     {$skip_locked}",
                    $reserva_cancelada->actividad_id,
                    $reserva_cancelada->fecha
                ));

            if (empty($cola)) break;

            // Buscar el primer candidato que cabe
            $elegido = null;
            foreach ($cola as $candidato) {
                $necesita = max(1, (int) ($candidato->num_menores ?? 1));
                if ($necesita <= $plazas_libres) {
                    $elegido = $candidato;
                    break;
                }
            }

            // Ningún candidato cabe con las plazas actuales
            if (!$elegido) break;

            // Eliminar al elegido de la lista de espera y crear su reserva
            self::eliminar_entrada_espera($elegido->id);

            $nueva_reserva = [
                'calendario_id' => $reserva_cancelada->calendario_id,
                'actividad_id'  => $reserva_cancelada->actividad_id,
                'fecha'         => $reserva_cancelada->fecha,
                'horario'       => $reserva_cancelada->horario,
                'nombre'        => $elegido->nombre,
                'email'         => $elegido->email,
                'telefono'      => $elegido->telefono,
                'num_menores'   => max(1, (int) ($elegido->num_menores ?? 1)),
                'edades'        => $elegido->edades ?? '',
                'estado'        => 'activa',
                'datos_extra'   => !empty($elegido->datos_extra) ? $elegido->datos_extra : wp_json_encode([]),
            ];

            $token = self::guardar_reserva($nueva_reserva);

            if (!$token) {
                // Fallo de inserción (deadlock o colisión de token): revertir todo.
                // Como eliminar_entrada_espera() ocurrió dentro de la txn, el ROLLBACK
                // restaura la entrada y no se pierde al candidato.
                throw new \RuntimeException('Fallo al guardar reserva promovida en procesar_espera_automatica');
            }

            // Notificar tras el COMMIT, nunca dentro de la transacción.
            $pendientes_email[] = ['espera' => $elegido, 'token' => $token];

                $hubo_asignacion = true;
            } // end while

            if ($hubo_asignacion) {
                // Invalidar caché de disponibilidad una sola vez al final
                $mes_reserva  = intval(gmdate('n', strtotime($reserva_cancelada->fecha)));
                $anio_reserva = intval(gmdate('Y', strtotime($reserva_cancelada->fecha)));
                delete_transient('calebo_disp_' . $calendario->id . '_' . $mes_reserva . '_' . $anio_reserva);
            }

            $wpdb->query('COMMIT');
            $commit_ok = true;

        } catch ( \Throwable $e ) {
            $wpdb->query('ROLLBACK');
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error de transacción; se registra en log del servidor para diagnóstico.
            error_log( 'CalendiFlow: error en procesar_espera_automatica: ' . $e->getMessage() );
        } finally {
            delete_transient( $lock_key );
        }

        // ── Notificaciones FUERA de la transacción ──
        // La BD ya está confirmada: un fallo de envío no revierte los datos.
        if ( $commit_ok && $hubo_asignacion && class_exists( 'CALEBO_Notifications' ) ) {
            foreach ( $pendientes_email as $pend ) {
                $elegido = $pend['espera'];
                CALEBO_Notifications::enviar_confirmacion_automatica_espera( $calendario, $actividad, $reserva_cancelada->fecha, $elegido, $pend['token'] );

                // Datos completos para el correo del admin (aplanando datos_extra).
                $datos_admin = [
                    'nombre'      => $elegido->nombre,
                    'email'       => $elegido->email,
                    'telefono'    => $elegido->telefono,
                    'num_menores' => max(1, (int) ($elegido->num_menores ?? 1)),
                    'edades'      => $elegido->edades ?? '',
                ];
                if ( ! empty( $elegido->datos_extra ) ) {
                    foreach ( (array) json_decode( $elegido->datos_extra, true ) as $k => $v ) {
                        if ( ! isset( $datos_admin[ $k ] ) ) $datos_admin[ $k ] = $v;
                    }
                }
                $ocupadas_tras = (int) ( self::get_reservas_por_actividad( $calendario->id, $reserva_cancelada->fecha )[ $actividad->id ] ?? 0 );
                CALEBO_Notifications::enviar_confirmacion_admin( $calendario, $reserva_cancelada->fecha, $actividad, $datos_admin, $campos_config, $ocupadas_tras );
            }
        }

        // Solo se considera éxito si la transacción llegó a COMMIT.
        return $hubo_asignacion && $commit_ok;
    }

    public static function get_reserva_por_token($token) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_reservas WHERE token = %s", $token
        ));
    }

    public static function actualizar_diseno($calendario_id, $diseno) {
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}calebo_calendarios",
            ['diseno' => wp_json_encode($diseno)],
            ['id'     => $calendario_id]
        );
    }

    // ── ACTIVIDADES (modelo unificado con rangos de fecha) ────────────────

    /**
     * Actividades activas en una fecha concreta (fecha dentro del rango).
     */
    public static function get_actividades_fecha($calendario_id, $fecha) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_actividades
             WHERE calendario_id = %d AND fecha_inicio <= %s AND fecha_fin >= %s AND activo = 1
             ORDER BY horario ASC, id ASC",
            $calendario_id, $fecha, $fecha
        ));
    }

    /**
     * Actividades cuyo rango se solapa con el rango dado.
     */
    public static function get_actividades_rango($calendario_id, $fecha_inicio, $fecha_fin) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_actividades
             WHERE calendario_id = %d AND fecha_inicio <= %s AND fecha_fin >= %s AND activo = 1
             ORDER BY fecha_inicio ASC, horario ASC",
            $calendario_id, $fecha_fin, $fecha_inicio
        ));
    }

    public static function get_actividad($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_actividades WHERE id = %d",
            $id
        ));
    }

    /**
     * Reservas agrupadas por actividad_id para una fecha dada.
     * Retorna array [ actividad_id => total_menores ]
     */
    public static function get_reservas_por_actividad($calendario_id, $fecha) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT actividad_id, COALESCE(SUM(num_menores), 0) AS total
             FROM {$wpdb->prefix}calebo_reservas
             WHERE calendario_id = %d AND fecha = %s AND estado = 'activa'
               AND actividad_id IS NOT NULL
             GROUP BY actividad_id",
            $calendario_id, $fecha
        ));
        $mapa = [];
        foreach ($rows as $row) {
            $mapa[(int) $row->actividad_id] = (int) $row->total;
        }
        return $mapa;
    }

    /**
     * Resumen de plazas por actividad para un rango de fechas.
     * Retorna array [ fecha => [ actividad_id => total_menores ] ]
     */
    public static function get_reservas_actividades_rango($calendario_id, $fecha_inicio, $fecha_fin) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT fecha, actividad_id, COALESCE(SUM(num_menores), 0) AS total
             FROM {$wpdb->prefix}calebo_reservas
             WHERE calendario_id = %d AND fecha BETWEEN %s AND %s
               AND estado = 'activa' AND actividad_id IS NOT NULL
             GROUP BY fecha, actividad_id",
            $calendario_id, $fecha_inicio, $fecha_fin
        ));
        $mapa = [];
        foreach ($rows as $row) {
            $mapa[$row->fecha][(int) $row->actividad_id] = (int) $row->total;
        }
        return $mapa;
    }

    public static function guardar_actividad($data) {
        global $wpdb;
        if (!empty($data['id'])) {
            $id = intval($data['id']);
            unset($data['id']);
            // En UPDATE: omitir campos con valor null para no machacar datos existentes
            $data_update = array_filter($data, function($v) { return $v !== null; });
            if (!empty($data_update)) {
                // Construir array de formatos explícitos para evitar problemas con
                // valores 0 (lista_espera, activo) que podrían interpretarse como falsy
                $formats = [];
                foreach ($data_update as $key => $val) {
                    if (in_array($key, ['calendario_id','limite','activo','lista_espera',
                                        'meses_max','dia_activacion','mes_inicio_ciclo',
                                        'usar_horario_vacaciones'], true)) {
                        $formats[] = '%d';
                    } elseif (is_float($val)) {
                        $formats[] = '%f';
                    } else {
                        $formats[] = '%s';
                    }
                }
                $wpdb->update("{$wpdb->prefix}calebo_actividades", $data_update, ['id' => $id], $formats, ['%d']);
            }
            return $id;
        }
        // Formatos explícitos para INSERT
        $ins_formats = [];
        foreach ($data as $key => $val) {
            if (in_array($key, ['calendario_id','limite','activo','lista_espera',
                                'meses_max','dia_activacion','mes_inicio_ciclo',
                                'usar_horario_vacaciones'], true)) {
                $ins_formats[] = '%d';
            } elseif (is_float($val)) {
                $ins_formats[] = '%f';
            } else {
                $ins_formats[] = '%s';
            }
        }
        $wpdb->insert("{$wpdb->prefix}calebo_actividades", $data, $ins_formats);
        return $wpdb->insert_id;
    }

    public static function eliminar_actividad($id) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}calebo_actividades",
            ['activo' => 0],
            ['id'     => intval($id)]
        );
    }

    /**
     * Todas las actividades activas de un calendario (sin filtro de fecha).
     */
    public static function get_actividades_by_calendario($calendario_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_actividades
             WHERE calendario_id = %d AND activo = 1
             ORDER BY fecha_inicio ASC, id ASC",
            $calendario_id
        ));
    }

    /**
     * Actualiza solo el JSON de campos_extra de una actividad.
     */
    public static function actualizar_campos_actividad($actividad_id, $campos_json) {
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}calebo_actividades",
            ['campos_extra' => $campos_json],
            ['id'           => $actividad_id],
            ['%s'],
            ['%d']
        );
    }

    // ── LISTA DE ESPERA ──────────────────────────────────────────────

    public static function guardar_espera($data) {
        global $wpdb;
        $result = $wpdb->insert("{$wpdb->prefix}calebo_espera", $data);
        return ($result !== false) ? $wpdb->insert_id : false;
    }

    public static function get_siguiente_espera($actividad_id, $fecha) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_espera
             WHERE actividad_id = %d AND fecha = %s AND notificado = 0
             ORDER BY creada_en ASC LIMIT 1",
            $actividad_id, $fecha
        ));
    }

    public static function marcar_espera_notificado($id) {
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}calebo_espera",
            ['notificado' => 1],
            ['id' => intval($id)]
        );
    }

    public static function existe_en_espera($email, $actividad_id, $fecha) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}calebo_espera
             WHERE email = %s AND actividad_id = %d AND fecha = %s AND notificado = 0",
            $email, $actividad_id, $fecha
        )) > 0;
    }

    /**
     * Resolves a config value: uses activity's own value if set, otherwise falls back to calendar.
     */
    public static function resolver_config_actividad($actividad, $calendario, $campo) {
        $mapa = [
            'horario_vacaciones'      => 'horario_vacaciones',
            'usar_horario_vacaciones' => 'usar_horario_vacaciones',
            'meses_max'               => 'meses_max',
            'dia_activacion'          => 'dia_activacion',
            'mes_inicio_ciclo'        => 'mes_inicio_ciclo',
            'limite'                  => 'limite',
        ];

        if (isset($actividad->$campo) && $actividad->$campo !== null && $actividad->$campo !== '') {
            return $actividad->$campo;
        }

        $campo_cal = $mapa[$campo] ?? $campo;
        return $calendario->$campo_cal ?? null;
    }

    public static function get_espera_count_by_actividad($actividad_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}calebo_espera WHERE actividad_id = %d AND notificado = 0",
            $actividad_id
        ));
    }

    public static function eliminar_entrada_espera($id) {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}calebo_espera", ['id' => intval($id)]);
    }

    public static function get_espera_by_actividad($actividad_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}calebo_espera WHERE actividad_id = %d AND notificado = 0 ORDER BY creada_en ASC",
            $actividad_id
        ));
    }

    public static function get_espera_by_calendario($calendario_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, a.nombre AS actividad_nombre
             FROM {$wpdb->prefix}calebo_espera e
             LEFT JOIN {$wpdb->prefix}calebo_actividades a ON e.actividad_id = a.id
             WHERE e.calendario_id = %d AND e.notificado = 0
             ORDER BY e.fecha ASC, e.creada_en ASC",
            $calendario_id
        ));
    }

    // ── FIX 5: Helper centralizado para generar bloques @font-face ─────────
    // Evita la lógica duplicada entre CALEBO_Booking::enqueue_assets() y CALEBO_Admin::enqueue_assets()
    public static function get_font_face_css($fuente, $pesos_archivos) {
        $css = '';
        foreach ($pesos_archivos as $peso => $archivo) {
            $url  = CALEBO_PLUGIN_URL . 'assets/fonts/' . $archivo;
            $css .= "@font-face{"
                . "font-family:'" . esc_attr($fuente) . "';"
                . "src:url('" . esc_url($url) . "') format('woff');"
                . "font-weight:" . intval($peso) . ";"
                . "font-style:normal;"
                . "font-display:swap;"
                . "}\n";
        }
        return $css;
    }


}
