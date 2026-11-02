<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
        <div class="wrap">
            <h1><?php esc_html_e('Diseño del calendario', 'calendar-booking'); ?></h1>

            <?php if (count($calendarios) > 1): ?>
            <form method="get" style="margin-bottom:20px;">
                <input type="hidden" name="page" value="calebo-diseno">
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

            <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">
                <form method="post">
                    <?php wp_nonce_field('calebo_guardar_diseno_' . $cal_id); ?>

                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">🎨 <?php esc_html_e('Colores', 'calendar-booking'); ?></h3>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Color principal', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="color" name="color_primary" value="<?php echo esc_attr($primary); ?>" onchange="caleboPreview()">
                                    <span class="description" style="margin-left:8px;"><?php esc_html_e('Cabecera y botón', 'calendar-booking'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Color secundario', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="color" name="color_secondary" value="<?php echo esc_attr($secondary); ?>" onchange="caleboPreview()">
                                    <span class="description" style="margin-left:8px;"><?php esc_html_e('Fondo de la fila LUN, MAR… y acentos', 'calendar-booking'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Letras días de semana', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="color" name="color_semana_texto" value="<?php echo esc_attr($d['color_semana_texto']); ?>" onchange="caleboPreview()">
                                    <span class="description" style="margin-left:8px;"><?php esc_html_e('Color del texto LUN, MAR, MIÉ…', 'calendar-booking'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Números del mes', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="color" name="color_numeros_mes" value="<?php echo esc_attr($d['color_numeros_mes']); ?>" onchange="caleboPreview()">
                                    <span class="description" style="margin-left:8px;"><?php esc_html_e('Color de los números de días disponibles', 'calendar-booking'); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">🔢 <?php esc_html_e('Indicador múltiples actividades', 'calendar-booking'); ?></h3>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Color del icono', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="color" name="multi_color" value="<?php echo esc_attr($d['multi_color']); ?>" onchange="caleboPreview()">
                                    <span class="description" style="margin-left:8px;"><?php esc_html_e('Badge que aparece en días con varias actividades', 'calendar-booking'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Tamaño del icono', 'calendar-booking'); ?></th>
                                <td style="display:flex;align-items:center;gap:12px;">
                                    <input type="range" name="multi_size" min="10" max="36" step="1"
                                           value="<?php echo esc_attr($d['multi_size'] ?? 16); ?>"
                                           oninput="document.getElementById('multi_size_val').textContent=this.value+'px';caleboPreview()"
                                           style="width:160px;">
                                    <span id="multi_size_val" style="font-weight:600;min-width:36px;"><?php echo esc_attr($d['multi_size'] ?? 16); ?>px</span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Forma del icono', 'calendar-booking'); ?></th>
                                <td style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                                    <?php
                                    $formas = [
                                        'circulo'    => ['label' => __('Círculo',    'calendar-booking'), 'css' => 'border-radius:50%'],
                                        'cuadrado'   => ['label' => __('Cuadrado',   'calendar-booking'), 'css' => 'border-radius:0'],
                                        'redondeado' => ['label' => __('Redondeado', 'calendar-booking'), 'css' => 'border-radius:4px'],
                                        'diamante'   => ['label' => __('Diamante',   'calendar-booking'), 'css' => 'border-radius:0;clip-path:polygon(50% 0%,100% 50%,50% 100%,0% 50%)'],
                                        'estrella'   => ['label' => __('Estrella',   'calendar-booking'), 'css' => 'border-radius:0;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%)'],
                                    ];
                                    foreach ($formas as $val => $info): ?>
                                    <label style="display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;font-size:11px;">
                                        <input type="radio" name="multi_forma" value="<?php echo esc_attr($val); ?>" <?php checked($d['multi_forma'], $val); ?> onchange="caleboPreview()" style="display:none;" class="calebo-forma-radio">
                                        <span class="calebo-forma-preview" data-forma="<?php echo esc_attr($val); ?>"
                                              style="display:inline-block;width:22px;height:22px;background:<?php echo esc_attr($d['multi_color']); ?>;<?php echo esc_attr($info['css']); ?>;
                                                     outline:2px solid <?php echo esc_attr( $d['multi_forma'] === $val ? '#F7941D' : 'transparent' ); ?>;outline-offset:2px;"></span>
                                        <?php echo esc_html($info['label']); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">✏️ <?php esc_html_e('Tipografía', 'calendar-booking'); ?></h3>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Fuente', 'calendar-booking'); ?></th>
                                <td>
                                    <select name="fuente" id="calebo-fuente-select" onchange="caleboActualizarFuente(this.value)" style="min-width:220px;">
                                        <?php foreach ($fuentes as $grupo): ?>
                                        <optgroup label="<?php echo esc_attr($grupo['label']); ?>">
                                            <?php foreach ($grupo['fuentes'] as $f): ?>
                                            <option value="<?php echo esc_attr($f); ?>" <?php selected($d['fuente'], $f); ?>><?php echo esc_html($f); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <p id="calebo-fuente-aviso" class="description" style="margin-top:6px;display:none;color:#856404;background:#fff3cd;border:1px solid #ffc107;padding:6px 10px;border-radius:4px;">
                                        ⚠️ <?php esc_html_e('Esta fuente requiere que tu tema la cargue (p.ej. via Google Fonts en el personalizador). El plugin no la incluye para cumplir con la normativa de privacidad.', 'calendar-booking'); ?>
                                    </p>
                                    <p id="calebo-fuente-info-local" class="description" style="margin-top:6px;display:none;color:#155724;background:#d4edda;border:1px solid #c3e6cb;padding:6px 10px;border-radius:4px;">
                                        ✅ <?php esc_html_e('Esta fuente está incluida en el plugin y se sirve localmente, sin llamadas a servicios externos.', 'calendar-booking'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Muestra', 'calendar-booking'); ?></th>
                                <td>
                                    <span id="calebo-fuente-muestra" style="font-size:20px;line-height:1.4;color:#2D2D2D;">
                                        CalendiFlow 0123
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">📅 <?php esc_html_e('Celdas del calendario', 'calendar-booking'); ?></h3>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Radio del borde', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="range" name="celda_radio" min="0" max="50"
                                           value="<?php echo esc_attr($d['celda_radio']); ?>"
                                           oninput="this.nextElementSibling.textContent=this.value+'px';caleboPreview()">
                                    <span><?php echo esc_html($d['celda_radio']); ?>px</span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Padding interior', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="range" name="celda_padding" min="4" max="24"
                                           value="<?php echo esc_attr($d['celda_padding']); ?>"
                                           oninput="this.nextElementSibling.textContent=this.value+'px';caleboPreview()">
                                    <span><?php echo esc_html($d['celda_padding']); ?>px</span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Tamaño de celda', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="range" name="celda_ancho" min="60" max="100"
                                           value="<?php echo esc_attr($d['celda_ancho']); ?>"
                                           oninput="this.nextElementSibling.textContent=this.value+'%';caleboPreview()">
                                    <span><?php echo esc_html($d['celda_ancho']); ?>%</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">🚫 <?php esc_html_e('Rótulo COMPLETO', 'calendar-booking'); ?></h3>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Texto', 'calendar-booking'); ?></th>
                                <td><input type="text" name="completo_texto" value="<?php echo esc_attr($d['completo_texto']); ?>" oninput="caleboPreview()"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Color de fondo', 'calendar-booking'); ?></th>
                                <td><input type="color" name="completo_bg" value="<?php echo esc_attr($d['completo_bg']); ?>" onchange="caleboPreview()"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Color del texto', 'calendar-booking'); ?></th>
                                <td><input type="color" name="completo_color" value="<?php echo esc_attr($d['completo_color']); ?>" onchange="caleboPreview()"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Rotación', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="range" name="completo_rotacion" min="-45" max="45"
                                           value="<?php echo esc_attr($d['completo_rotacion']); ?>"
                                           oninput="this.nextElementSibling.textContent=this.value+'°';caleboPreview()">
                                    <span><?php echo esc_html($d['completo_rotacion']); ?>°</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">🔘 <?php esc_html_e('Botón de reserva', 'calendar-booking'); ?></h3>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Texto del botón', 'calendar-booking'); ?></th>
                                <td><input type="text" name="btn_texto" value="<?php echo esc_attr($d['btn_texto']); ?>" oninput="caleboPreview()"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Radio del borde', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="range" name="btn_radio" min="0" max="50"
                                           value="<?php echo esc_attr($d['btn_radio']); ?>"
                                           oninput="this.nextElementSibling.textContent=this.value+'px';caleboPreview()">
                                    <span><?php echo esc_html($d['btn_radio']); ?>px</span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Texto en mayúsculas', 'calendar-booking'); ?></th>
                                <td><input type="checkbox" name="btn_uppercase" value="1" <?php checked($d['btn_uppercase'], '1'); ?> onchange="caleboPreview()"></td>
                            </tr>
                        </table>
                    </div>

                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:20px;">
                        <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">🖼️ <?php esc_html_e('Fondo del calendario', 'calendar-booking'); ?></h3>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Tipo de fondo', 'calendar-booking'); ?></th>
                                <td>
                                    <select name="fondo_tipo" onchange="caleboToggleFondo(this.value);caleboPreview()">
                                        <option value="color" <?php selected($d['fondo_tipo'], 'color'); ?>><?php esc_html_e('Color sólido', 'calendar-booking'); ?></option>
                                        <option value="imagen" <?php selected($d['fondo_tipo'], 'imagen'); ?>><?php esc_html_e('Imagen', 'calendar-booking'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="calebo-fondo-color-row" <?php echo esc_attr( $d['fondo_tipo'] === 'imagen' ? 'style="display:none"' : '' ); ?>>
                                <th><?php esc_html_e('Color de fondo', 'calendar-booking'); ?></th>
                                <td><input type="color" name="fondo_color" value="<?php echo esc_attr($d['fondo_color']); ?>" onchange="caleboPreview()"></td>
                            </tr>
                            <tr id="calebo-fondo-imagen-row" <?php echo esc_attr( $d['fondo_tipo'] !== 'imagen' ? 'style="display:none"' : '' ); ?>>
                                <th><?php esc_html_e('URL de la imagen', 'calendar-booking'); ?></th>
                                <td>
                                    <input type="url" name="fondo_imagen" id="calebo-fondo-imagen" class="regular-text"
                                           value="<?php echo esc_attr($d['fondo_imagen']); ?>" oninput="caleboPreview()">
                                    <button type="button" class="button" onclick="cbSeleccionarImagen()">
                                        <?php esc_html_e('Seleccionar imagen', 'calendar-booking'); ?>
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:20px;">
                        <h3 style="margin:0 0 16px;font-size:14px;border-left:3px solid #F7941D;padding-left:10px;">✉️ <?php esc_html_e('Personalización de correos', 'calendar-booking'); ?></h3>
                        <p style="font-size:12px;color:#666;margin-bottom:15px;">
                            <?php esc_html_e('Puedes usar las etiquetas {nombre} y {calendario} en los textos.', 'calendar-booking'); ?>
                        </p>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th colspan="2" style="padding:10px 0;border-bottom:1px solid #eee;"><strong><?php esc_html_e('Email de Confirmación', 'calendar-booking'); ?></strong></th>
                            </tr>
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Asunto', 'calendar-booking'); ?></th>
                                <td><input type="text" name="email_asunto_conf" class="regular-text" value="<?php echo esc_attr($d['email_asunto_conf'] ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Mensaje', 'calendar-booking'); ?></th>
                                <td><textarea name="email_msg_conf" rows="4" style="width:100%;"><?php echo esc_textarea($d['email_msg_conf'] ?? ''); ?></textarea></td>
                            </tr>
                            
                            <tr>
                                <th colspan="2" style="padding:20px 0 10px;border-bottom:1px solid #eee;"><strong><?php esc_html_e('Email de Cancelación (por usuario)', 'calendar-booking'); ?></strong></th>
                            </tr>
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Asunto', 'calendar-booking'); ?></th>
                                <td><input type="text" name="email_asunto_canc" class="regular-text" value="<?php echo esc_attr($d['email_asunto_canc'] ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Mensaje', 'calendar-booking'); ?></th>
                                <td><textarea name="email_msg_canc" rows="4" style="width:100%;"><?php echo esc_textarea($d['email_msg_canc'] ?? ''); ?></textarea></td>
                            </tr>

                            <tr>
                                <th colspan="2" style="padding:20px 0 10px;border-bottom:1px solid #eee;"><strong><?php esc_html_e('Email de Cancelación (por administrador)', 'calendar-booking'); ?></strong></th>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Mensaje especial', 'calendar-booking'); ?></th>
                                <td>
                                    <textarea name="email_msg_canc_admin" rows="4" style="width:100%;"><?php echo esc_textarea($d['email_msg_canc_admin'] ?? ''); ?></textarea>
                                    <p class="description"><?php esc_html_e('Este mensaje se enviará si el administrador cancela la reserva manualmente.', 'calendar-booking'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <input type="submit" name="calebo_guardar_diseno" class="button button-primary button-large"
                           value="<?php esc_attr_e('💾 Guardar diseño', 'calendar-booking'); ?>">
                </form>

                <!-- PREVIEW -->
                <div style="position:sticky;top:32px;">
                    <h3 style="margin:0 0 12px;font-size:14px;"><?php esc_html_e('Vista previa', 'calendar-booking'); ?></h3>
                    <div id="calebo-preview" style="border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;font-size:13px;">
                        <div id="prev-header" style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;color:#fff;">
                            <span style="font-weight:800;text-transform:uppercase;letter-spacing:1px;"><?php esc_html_e('Marzo 2026', 'calendar-booking'); ?></span>
                            <div style="display:flex;gap:8px;">
                                <span style="background:rgba(255,255,255,0.3);width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:900;">❮</span>
                                <span style="background:rgba(255,255,255,0.3);width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:900;">❯</span>
                            </div>
                        </div>
                        <div id="prev-semana-row" style="display:grid;grid-template-columns:repeat(7,1fr);background:<?php echo esc_attr($secondary); ?>;">
                            <?php foreach (['L','M','X','J','V','S','D'] as $dia): ?>
                            <div class="prev-dia-label" style="text-align:center;padding:6px 0;color:<?php echo esc_attr($d['color_semana_texto']); ?>;font-weight:800;font-size:10px;"><?php echo esc_html($dia); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div id="prev-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;padding:6px;">
                            <?php
                            $colorNum = esc_attr($d['color_numeros_mes']);
                            $ejemplos = [
                                ['num'=>1,  'disponible'=>false, 'lleno'=>false, 'hoy'=>false],
                                ['num'=>2,  'disponible'=>true,  'lleno'=>false, 'hoy'=>false, 'multi'=>true],
                                ['num'=>3,  'disponible'=>true,  'lleno'=>false, 'hoy'=>true],
                                ['num'=>4,  'disponible'=>true,  'lleno'=>false, 'hoy'=>false],
                                ['num'=>5,  'disponible'=>true,  'lleno'=>true,  'hoy'=>false],
                                ['num'=>6,  'disponible'=>true,  'lleno'=>false, 'hoy'=>false],
                                ['num'=>7,  'disponible'=>false, 'lleno'=>false, 'hoy'=>false],
                            ];
                            foreach ($ejemplos as $ej):
                                $bg    = $ej['disponible'] ? '#fff' : '#f5f5f5';
                                $color = $ej['disponible'] ? $colorNum : '#ccc';
                            ?>
                            <div class="prev-celda" style="text-align:center;
                                        border-radius:<?php echo esc_attr($d['celda_radio']); ?>px;
                                        background:<?php echo esc_attr($bg); ?>;
                                        color:<?php echo esc_attr($color); ?>;
                                        font-weight:700;position:relative;
                                        border:2px solid <?php echo esc_attr( $ej['hoy'] ? '#2D2D2D' : '#e0e0e0' ); ?>;"
                                 data-disponible="<?php echo esc_attr( $ej['disponible'] ? '1' : '0' ); ?>">
                                <?php if ($ej['hoy']): ?>
                                <div style="font-size:7px;font-weight:800;color:#F7941D;text-transform:uppercase;">HOY</div>
                                <?php endif; ?>
                                <?php echo esc_html($ej['num']); ?>
                                <?php if (!empty($ej['multi'])): ?>
                                <span id="prev-multi-dot"
                                      style="position:absolute;top:4px;right:4px;
                                             background:<?php echo esc_attr($d['multi_color']); ?>;
                                             color:#fff;font-size:.55rem;font-weight:800;line-height:1;
                                             width:16px;height:16px;
                                             display:flex;align-items:center;justify-content:center;
                                             pointer-events:none;box-shadow:0 1px 4px rgba(0,0,0,.2);z-index:5;
                                             border-radius:50%;">3</span>
                                <?php endif; ?>
                                <?php if ($ej['lleno']): ?>
                                <div id="prev-completo"
                                     style="position:absolute;top:50%;left:50%;
                                            transform:translate(-50%,-50%) rotate(<?php echo esc_attr($d['completo_rotacion']); ?>deg);
                                            background:<?php echo esc_attr($d['completo_bg']); ?>;
                                            color:<?php echo esc_attr($d['completo_color']); ?>;
                                            font-size:7px;font-weight:800;
                                            padding:2px 4px;border-radius:3px;white-space:nowrap;">
                                    <?php echo esc_html($d['completo_texto']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="padding:12px;border-top:1px solid #f0f0f0;">
                            <div id="prev-btn"
                                 style="text-align:center;padding:10px;
                                        border-radius:<?php echo esc_attr($d['btn_radio']); ?>px;
                                        font-weight:800;font-size:12px;color:#fff;
                                        <?php echo esc_attr( $d['btn_uppercase'] === '1' ? 'text-transform:uppercase;' : '' ); ?>">
                                <?php echo esc_html($d['btn_texto']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php
        // Diseño: JS externalizado a assets/js/admin-diseno.js (se encola con admin_enqueue_scripts).
        // Solo se inyecta el objeto de datos dinámicos ANTES del script externo (posición 'before').
        wp_add_inline_script(
            'calebo-admin-diseno',
            'var caleboDisenoData = ' . wp_json_encode( [ 'fuentesLocales' => $fuentes_locales ] ) . ';',
            'before'
        );
        ?>
