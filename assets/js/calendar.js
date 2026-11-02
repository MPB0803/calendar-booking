(function($) {
    'use strict';

    var mesesES  = (caleboAjax && caleboAjax.months)  ? caleboAjax.months  : ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    var caleboStrings = (caleboAjax && caleboAjax.strings) ? caleboAjax.strings : {};

    function CBCalendar(wrap) {
        this.$wrap             = $(wrap);
        this.calendarioId      = this.$wrap.data('id');
        this.limite            = this.$wrap.data('limite');
        this.mesesMax          = parseInt(this.$wrap.data('meses-max')) || 3;
        this.mesActual         = new Date().getMonth() + 1;
        this.anioActual        = new Date().getFullYear();
        this.hoy               = new Date().toISOString().split('T')[0];
        this.fechaSeleccionada     = null;
        this.actividadSeleccionada = null;
        this.disponibilidad    = {};

        var rawDias = String(this.$wrap.data('dias-disponibles') || '1,2,3,4,5');
        this.diasDisponibles = rawDias.split(',').map(function(d) { return parseInt(d, 10); }).filter(function(d) { return !isNaN(d); });
        this.diasDisponibles.sort(function(a, b) { return a - b; });

        this.calcularLimites();
        this.inicializar();
    }

    CBCalendar.prototype.calcularLimites = function() {
        var hoy      = new Date();
        this.mesMin  = hoy.getMonth() + 1;
        this.anioMin = hoy.getFullYear();
        // Navegación libre hacia el futuro — la disponibilidad de cada actividad
        // la controla el servidor según su propia configuración de meses_max.
        this.mesMax  = this.mesMin;   // no se usa para bloquear "siguiente"
        this.anioMax = this.anioMin + 5;
    };

    CBCalendar.prototype.inicializar = function() {
        var self = this;

        this.$wrap.find('.calebo-prev').on('click', function() {
            if ($(this).prop('disabled')) return;
            self.mesActual--;
            if (self.mesActual < 1) { self.mesActual = 12; self.anioActual--; }
            self.actualizarNavegacion();
            self.cargarMes();
        });

        this.$wrap.find('.calebo-next').on('click', function() {
            if ($(this).prop('disabled')) return;
            self.mesActual++;
            if (self.mesActual > 12) { self.mesActual = 1; self.anioActual++; }
            self.actualizarNavegacion();
            self.cargarMes();
        });

        this.$wrap.on('click', '.calebo-day--disponible, .calebo-day--casi_lleno', function() {
            self.seleccionarDia($(this));
        });

        this.$wrap.on('keydown', '.calebo-day--disponible, .calebo-day--casi_lleno', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                self.seleccionarDia($(this));
            }
        });

        this.$wrap.find('.calebo-btn-reservar').on('click', function() {
            self.enviarReserva();
        });

        this.$wrap.on('click', '.calebo-btn-volver-actividades', function() {
            self.$wrap.find('.calebo-form-wrap').hide();
            self.actividadSeleccionada = null;
            self.mostrarActividades(self.fechaSeleccionada);
        });

        this.$wrap.find('.calebo-btn-nueva').on('click', function() {
            self.$wrap.find('.calebo-confirmacion').hide();
            self.$wrap.find('.calebo-actividades-wrap').hide();
            self.$wrap.find('.calebo-form-wrap').hide();
            self.fechaSeleccionada     = null;
            self.actividadSeleccionada = null;
            self.cargarMes();
        });

        this.actualizarNavegacion();
        this.cargarMes();
    };

    CBCalendar.prototype.actualizarNavegacion = function() {
        var $prev = this.$wrap.find('.calebo-prev');
        var $next = this.$wrap.find('.calebo-next');

        var puedeAnterior  = !(this.mesActual === this.mesMin && this.anioActual === this.anioMin);
        var puedeSiguiente = true; // Navegación libre hacia el futuro

        $prev.prop('disabled', !puedeAnterior).css({'opacity': puedeAnterior  ? '1' : '0.3', 'cursor': puedeAnterior  ? 'pointer' : 'not-allowed'});
        $next.prop('disabled', false).css({'opacity': '1', 'cursor': 'pointer'});
    };

    CBCalendar.prototype.mostrarOverlay = function() {
        var $overlay = this.$wrap.find('.calebo-loading-overlay');
        $overlay.css('display', 'flex');

        var cells = $overlay.find('.calebo-loader-cell').toArray();
        var idx = 0;
        var self = this;

        function lightNext() {
            if (!self._loaderActive) return;
            if (idx < cells.length) {
                $(cells[idx]).addClass('calebo-loader-cell--on');
                idx++;
                self._loaderTimer = setTimeout(lightNext, 110);
            } else {
                self._loaderTimer = setTimeout(function() {
                    if (!self._loaderActive) return;
                    $(cells).removeClass('calebo-loader-cell--on');
                    idx = 0;
                    self._loaderTimer = setTimeout(lightNext, 250);
                }, 500);
            }
        }

        this._loaderActive = true;
        clearTimeout(this._loaderTimer);
        $(cells).removeClass('calebo-loader-cell--on');
        lightNext();
    };

    CBCalendar.prototype.ocultarOverlay = function() {
        this._loaderActive = false;
        clearTimeout(this._loaderTimer);
        this.$wrap.find('.calebo-loading-overlay').hide();
        this.$wrap.find('.calebo-loader-cell').removeClass('calebo-loader-cell--on');
    };

    CBCalendar.prototype.cargarMes = function() {
        var self  = this;
        var $wrap = this.$wrap;
        var $grid = $wrap.find('.calebo-cal-grid');
        var dias  = this.diasDisponibles;

        $wrap.find('.calebo-titulo-mes').text(mesesES[this.mesActual] + ' ' + this.anioActual);
        $wrap.find('.calebo-form-wrap').hide();
        $wrap.find('.calebo-confirmacion').hide();
        $wrap.find('.calebo-actividades-wrap').hide();
        $wrap.find('.calebo-form-grid').empty();
        this.fechaSeleccionada     = null;
        this.actividadSeleccionada = null;

        this.mostrarOverlay();
        $grid.find('.calebo-day').remove();

        var diasEnMes = new Date(this.anioActual, this.mesActual, 0).getDate();
        var primerDow = new Date(this.anioActual, this.mesActual - 1, 1).getDay();
        if (primerDow === 0) primerDow = 7;

        var offsetCols = 0;
        var encontrado = false;
        for (var i = 0; i < dias.length; i++) {
            if (dias[i] >= primerDow) { offsetCols = i; encontrado = true; break; }
        }
        if (!encontrado) offsetCols = 0;

        for (var v = 0; v < offsetCols; v++) {
            $grid.append('<div class="calebo-day calebo-day--vacio"></div>');
        }

        for (var d = 1; d <= diasEnMes; d++) {
            var dowDia = new Date(this.anioActual, this.mesActual - 1, d).getDay();
            if (dowDia === 0) dowDia = 7;
            if (dias.indexOf(dowDia) === -1) continue;

            var fecha = this.anioActual + '-' +
                        String(this.mesActual).padStart(2, '0') + '-' +
                        String(d).padStart(2, '0');
            $grid.append(
                '<div class="calebo-day calebo-day--skeleton" data-fecha="' + fecha + '">' +
                '<span class="calebo-day-num">' + d + '</span></div>'
            );
        }

        $.post(caleboAjax.url, {
            action:        'calebo_disponibilidad',
            nonce:         caleboAjax.nonce,
            calendario_id: self.calendarioId,
            mes:           self.mesActual,
            anio:          self.anioActual
        }, function(resp) {
            self.ocultarOverlay();
            if (!resp.success) return;
            self.disponibilidad = resp.data;

            $.each(resp.data, function(fecha, info) {
                var $dia = $grid.find('[data-fecha="' + fecha + '"]');
                if (!$dia.length) return;

                var d        = parseInt(fecha.split('-')[2]);
                var sub      = '';
                var hoyLabel = '';

                $dia.removeClass('calebo-day--skeleton');

                if (fecha === self.hoy) {
                    $dia.addClass('calebo-day--hoy');
                    hoyLabel = '<span class="calebo-day-hoy-label">' + (caleboStrings.today || 'HOY') + '</span>';
                }

                if (info.estado === 'disponible') {
                    $dia.addClass('calebo-day--disponible');
                    if (info.libres && info.num_eventos <= 1) {
                        sub = (caleboStrings.slots_available || '%d libres').replace('%d', info.libres);
                    }
                } else if (info.estado === 'casi_lleno') {
                    $dia.addClass('calebo-day--casi_lleno');
                    sub = (caleboStrings.remaining_slots || '\u00a1%d plaza(s)!').replace('%d', info.libres);
                } else if (info.estado === 'lleno') {
                    $dia.addClass('calebo-day--lleno');
                } else if (info.estado === 'cerrado') {
                    $dia.addClass('calebo-day--cerrado');
                } else if (info.estado === 'no_disponible') {
                    $dia.addClass('calebo-day--no-disponible');
                } else if (info.estado === 'vacaciones') {
                    $dia.addClass('calebo-day--cerrado calebo-day--vacaciones');
                }

                // Indicador visual de múltiples actividades
                var multiDot = '';
                var numEv = info.num_eventos || 0;
                if (numEv > 1) {
                    $dia.addClass('calebo-day--multi-evento');
                    multiDot = '<span class="calebo-day-multi-dot" title="' + numEv + ' actividades">' + numEv + '</span>';
                }

                $dia.html(
                    hoyLabel +
                    '<span class="calebo-day-num">' + d + '</span>' +
                    (sub ? '<span class="calebo-day-sub">' + sub + '</span>' : '') +
                    multiDot
                );
            });

            // Accessibility: add ARIA attributes
            $grid.find('.calebo-day--disponible, .calebo-day--casi_lleno').each(function() {
                var $d = $(this);
                var f = $d.data('fecha');
                var inf = self.disponibilidad[f] || {};
                $d.attr({
                    'role': 'button',
                    'tabindex': '0',
                    'aria-label': f + ', ' + (inf.libres || 0) + ' ' + (caleboStrings.slots_available || '%d libres').replace('%d', '')
                });
            });
            $grid.find('.calebo-day--lleno').attr('aria-label', caleboStrings.activity_full || 'Completo');
            $grid.find('.calebo-day--cerrado, .calebo-day--no-disponible').attr('aria-hidden', 'true');
        });
    };

    // ── Seleccionar día: siempre carga actividades ──
    CBCalendar.prototype.seleccionarDia = function($dia) {
        this.$wrap.find('.calebo-day').removeClass('calebo-day--seleccionado');
        $dia.addClass('calebo-day--seleccionado');
        this.fechaSeleccionada = $dia.data('fecha');

        this.$wrap.find('.calebo-form-wrap').hide();
        this.$wrap.find('.calebo-confirmacion').hide();
        this.mostrarActividades(this.fechaSeleccionada);
    };

    // ── Mostrar actividades del día ──
    CBCalendar.prototype.mostrarActividades = function(fecha) {
        var self  = this;
        var $wrap = this.$wrap.find('.calebo-actividades-wrap');

        $wrap.show();
        $wrap.find('.calebo-actividades-lista').html(
            '<div class="calebo-loading-actividades">' +
                '<span class="calebo-spinner-actividades"></span>' +
                '<span>' + (caleboStrings.loading_activities || 'Cargando actividades...') + '</span>' +
            '</div>'
        );

        var partes     = fecha.split('-');
        var fechaLabel = partes[2] + '/' + partes[1] + '/' + partes[0];
        $wrap.find('.calebo-actividades-fecha').text(fechaLabel);

        $('html, body').animate({ scrollTop: $wrap.offset().top - 20 }, 400);

        $.post(caleboAjax.url, {
            action:        'calebo_get_actividades',
            nonce:         caleboAjax.nonce,
            calendario_id: self.calendarioId,
            fecha:         fecha
        }, function(resp) {
            if (!resp.success || !resp.data.length) {
                $wrap.find('.calebo-actividades-lista').html(
                    '<p style="color:#888;padding:16px 0;">Sin actividades disponibles.</p>'
                );
                return;
            }

            var actividades = resp.data;

            // Helper: devuelve el horario efectivo para una actividad en una fecha dada,
            // teniendo en cuenta períodos de vacaciones con horario especial
            function horarioEfectivo(act, fecha) {
                if (!act.vacaciones || !act.vacaciones.length) return act.horario;
                for (var i = 0; i < act.vacaciones.length; i++) {
                    var v = act.vacaciones[i];
                    if (v.tipo === 'horario_especial' && fecha >= v.inicio && fecha <= v.fin) {
                        return act.horario_vacaciones || act.horario;
                    }
                }
                return act.horario;
            }

            // Si solo hay 1 actividad
            if (actividades.length === 1) {
                var unica = actividades[0];
                // Si está llena y tiene lista de espera, mostrar botón de espera directamente
                if (unica.lleno && unica.lista_espera) {
                    var html1 = '<div class="calebo-act-card calebo-act-card--lleno">' +
                        '<div class="calebo-act-header"><div class="calebo-act-info">' +
                        '<strong class="calebo-act-nombre">' + $('<span>').text(unica.nombre).html() + '</strong>' +
                        '<span class="calebo-act-horario">' + $('<span>').text(unica.horario).html() + '</span>' +
                        '</div><div class="calebo-act-estado"><span class="calebo-act-badge calebo-act-badge--lleno">' + (caleboStrings.activity_full || 'Completo') + '</span></div>' +
                        '</div><div class="calebo-act-footer"><button class="calebo-act-btn calebo-act-btn--espera" data-actividad-id="' + unica.id + '">' +
                        '<span style="font-size:1.1em;">⏳</span> ' + (caleboStrings.waitlist_join || 'Lista de espera') + '</button></div></div>';
                    $wrap.find('.calebo-actividades-lista').html(html1);
                    // reutilizar handler de espera (se bindea más abajo)
                    actividades = [unica]; // para que el handler funcione
                } else if (!unica.lleno) {
                    $wrap.hide();
                    self.seleccionarActividad(unica.id, unica);
                    return;
                } else {
                    // Llena sin lista de espera
                    $wrap.find('.calebo-actividades-lista').html(
                        '<div class="calebo-act-card calebo-act-card--lleno"><div class="calebo-act-header"><div class="calebo-act-info">' +
                        '<strong class="calebo-act-nombre">' + $('<span>').text(unica.nombre).html() + '</strong>' +
                        '</div><div class="calebo-act-estado"><span class="calebo-act-badge calebo-act-badge--lleno">' + (caleboStrings.activity_full || 'Completo') + '</span></div></div></div>'
                    );
                    return;
                }
            }

            // Mostrar lista de actividades (2+)
            var html = '';
            $.each(actividades, function(i, act) {
                var estadoHtml = '';
                var btnHtml    = '';

                if (act.lleno) {
                    estadoHtml = '<span class="calebo-act-badge calebo-act-badge--lleno">' + (caleboStrings.activity_full || 'Completo') + '</span>';
                    if (act.lista_espera) {
                        btnHtml = '<button class="calebo-act-btn calebo-act-btn--espera" data-actividad-id="' + act.id + '">' +
                            '<span style="font-size:1.1em;">⏳</span> ' + (caleboStrings.waitlist_join || 'Lista de espera') + '</button>';
                    } else {
                        btnHtml = '<button class="calebo-act-btn" disabled style="opacity:.5;cursor:default;">' + (caleboStrings.activity_full || 'Completo') + '</button>';
                    }
                } else {
                    var slotsTxt = (caleboStrings.activity_slots || '%d plaza(s) libres').replace('%d', act.libres);
                    estadoHtml = '<span class="calebo-act-badge calebo-act-badge--libre">' + slotsTxt + '</span>';
                    btnHtml    = '<button class="calebo-act-btn calebo-act-btn--reservar" data-actividad-id="' + act.id + '">' + (caleboStrings.book_activity || 'Reservar') + '</button>';
                }

                var salaHtml = act.sala
                    ? '<span class="calebo-act-sala">' + (caleboStrings.activity_sala || 'Sala:') + ' ' + $('<span>').text(act.sala).html() + '</span>'
                    : '';
                var descHtml = act.descripcion
                    ? '<p class="calebo-act-desc">' + $('<span>').text(act.descripcion).html() + '</p>'
                    : '';

                html += '<div class="calebo-act-card' + (act.lleno ? ' calebo-act-card--lleno' : '') + '">' +
                    '<div class="calebo-act-header">' +
                        '<div class="calebo-act-info">' +
                            '<strong class="calebo-act-nombre">' + $('<span>').text(act.nombre).html() + '</strong>' +
                            '<span class="calebo-act-horario">' + $('<span>').text(horarioEfectivo(act, fecha)).html() + '</span>' +
                            salaHtml +
                        '</div>' +
                        '<div class="calebo-act-estado">' + estadoHtml + '</div>' +
                    '</div>' +
                    descHtml +
                    '<div class="calebo-act-footer">' + btnHtml + '</div>' +
                '</div>';
            });

            $wrap.find('.calebo-actividades-lista').html(html);

            $wrap.find('.calebo-act-btn--reservar').on('click', function() {
                var actId   = parseInt($(this).data('actividad-id'), 10);
                var actData = null;
                $.each(actividades, function(i, a) { if (a.id === actId) { actData = a; return false; } });
                if (actData) {
                    self.seleccionarActividad(actId, actData);
                }
            });

            $wrap.find('.calebo-act-btn--espera').on('click', function() {
                var $btn = $(this);
                var actId = parseInt($btn.data('actividad-id'), 10);

                // Obtener datos de la actividad (ya disponibles en memoria)
                var actData = null;
                $.each(actividades, function(i, a) { if (a.id === actId) { actData = a; return false; } });

                var modalId = 'calebo-waitlist-modal-' + self.calendarioId;
                // Siempre reconstruir el modal para reflejar los campos de ESTA actividad
                $('#' + modalId).remove();

                // ── Construir campos dinámicamente (igual que seleccionarActividad) ──
                var campos = (actData && actData.campos_extra) ? actData.campos_extra : [];
                var camposHtml = '';

                if (campos.length > 0) {
                    $.each(campos, function(i, ce) {
                        var reqAttr  = ce.requerido ? ' required' : '';
                        var reqLabel = ce.requerido ? ' *' : '';
                        var inputHtml = '';

                        if (ce.tipo === 'textarea') {
                            inputHtml = '<textarea class="calebo-wl-campo" data-nombre="' + ce.nombre + '" placeholder="' +
                                $('<span>').text(ce.placeholder || '').html() + '"' + reqAttr +
                                ' style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;min-height:72px;resize:vertical;"></textarea>';
                        } else if (ce.tipo === 'select') {
                            inputHtml = '<select class="calebo-wl-campo" data-nombre="' + ce.nombre + '"' + reqAttr +
                                ' style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">' +
                                '<option value="">\u2014 Selecciona \u2014</option>';
                            if (ce.opciones) {
                                $.each(ce.opciones.split(/\n/), function(j, ln) {
                                    var v = $.trim(ln);
                                    if (v) inputHtml += '<option value="' + $('<span>').text(v).html() + '">' + $('<span>').text(v).html() + '</option>';
                                });
                            }
                            inputHtml += '</select>';
                        } else if (ce.tipo === 'checkbox') {
                            inputHtml = '<label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-top:4px;">' +
                                '<input type="checkbox" class="calebo-wl-campo" data-nombre="' + ce.nombre + '"' + reqAttr + '>' +
                                $('<span>').text(ce.placeholder || '').html() + '</label>';
                        } else {
                            inputHtml = '<input type="' + ce.tipo + '" class="calebo-wl-campo" data-nombre="' + ce.nombre + '"' +
                                ' placeholder="' + $('<span>').text(ce.placeholder || '').html() + '"' + reqAttr +
                                ' style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">';
                        }

                        camposHtml += '<div style="margin-bottom:12px;">' +
                            '<label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:4px;">' +
                            $('<span>').text(ce.label + reqLabel).html() + '</label>' +
                            inputHtml + '</div>';
                    });
                } else {
                    // Fallback: sin campos configurados, pedir nombre y email mínimos
                    camposHtml =
                        '<div style="margin-bottom:12px;">' +
                        '<label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:4px;">' + (caleboStrings.waitlist_name || 'Tu nombre:') + '</label>' +
                        '<input type="text" class="calebo-wl-campo" data-nombre="nombre" placeholder="' + (caleboStrings.waitlist_name || 'Tu nombre') + '" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">' +
                        '</div>' +
                        '<div style="margin-bottom:12px;">' +
                        '<label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:4px;">' + (caleboStrings.waitlist_email || 'Tu email:') + ' *</label>' +
                        '<input type="email" class="calebo-wl-campo" data-nombre="email" required placeholder="tu@email.com" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">' +
                        '</div>';
                }

                var $modal = $(
                    '<div id="' + modalId + '" class="calebo-modal-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">' +
                    '<div class="calebo-modal-box" style="background:#fff;border-radius:12px;padding:28px;max-width:440px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto;">' +
                        '<h3 style="margin:0 0 4px;font-size:1.1rem;">' + (caleboStrings.waitlist_join || 'Lista de espera') + '</h3>' +
                        '<p style="margin:0 0 18px;font-size:0.82rem;color:#666;">' + (caleboStrings.waitlist_info || 'Rellena tus datos. Si se libera una plaza, la reserva se confirmará automáticamente.') + '</p>' +
                        camposHtml +
                        '<p class="calebo-wl-error" style="color:#c62828;font-size:0.8rem;margin:0 0 12px;display:none;"></p>' +
                        '<div style="display:flex;gap:8px;justify-content:flex-end;">' +
                            '<button type="button" class="calebo-wl-cancel" style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;background:#f5f5f5;cursor:pointer;">' + (caleboStrings.cancel || 'Cancelar') + '</button>' +
                            '<button type="button" class="calebo-wl-submit" style="padding:8px 16px;border:none;border-radius:6px;background:var(--calebo-primary,#F7941D);color:#fff;font-weight:700;cursor:pointer;">' + (caleboStrings.send || 'Apuntarme') + '</button>' +
                        '</div>' +
                    '</div>' +
                    '</div>'
                );
                $('body').append($modal);

                $modal.find('.calebo-wl-cancel').on('click', function() { $modal.remove(); });
                $modal.on('click', function(e) { if ($(e.target).hasClass('calebo-modal-overlay')) $modal.remove(); });

                $modal.css('display', 'flex');
                $modal.find('.calebo-wl-campo').first().focus();

                $modal.find('.calebo-wl-submit').on('click', function() {
                    var camposExtra = {};
                    var emailVal    = '';
                    var hayError    = false;

                    $modal.find('.calebo-wl-campo').each(function() {
                        var $el    = $(this);
                        var nombre = $el.data('nombre');
                        var valor  = $el.is(':checkbox') ? ($el.is(':checked') ? '1' : '') : $.trim($el.val());

                        if ($el.attr('required') && valor === '') {
                            $el.css('border-color', '#c62828');
                            hayError = true;
                        } else {
                            $el.css('border-color', '#ddd');
                        }
                        camposExtra[nombre] = valor;
                        if (nombre === 'email') emailVal = valor;
                    });

                    if (hayError) {
                        $modal.find('.calebo-wl-error').text(caleboStrings.required_fields || 'Por favor, rellena todos los campos obligatorios.').show();
                        return;
                    }

                    if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                        $modal.find('.calebo-wl-error').text(caleboStrings.email_invalid || 'Introduce un email válido.').show();
                        return;
                    }

                    $modal.find('.calebo-wl-error').hide();
                    $modal.find('.calebo-wl-submit').prop('disabled', true).text(caleboStrings.sending || 'Enviando...');

                    // Construir payload con campos_extra (igual que enviarReserva)
                    var payload = {
                        action:        'calebo_lista_espera',
                        nonce:         caleboAjax.nonce,
                        calendario_id: self.calendarioId,
                        actividad_id:  actId,
                        fecha:         self.fechaSeleccionada,
                    };
                    $.each(camposExtra, function(k, v) {
                        payload['campos_extra[' + k + ']'] = v;
                    });

                    $.post(caleboAjax.url, payload, function(resp) {
                        $modal.find('.calebo-wl-submit').prop('disabled', false).text(caleboStrings.send || 'Apuntarme');
                        if (resp.success) {
                            $modal.remove();
                            $btn.text('\u2713 ' + (caleboStrings.waitlist_done || 'En lista')).css({opacity:'.7',cursor:'default'}).prop('disabled', true);
                        } else {
                            $modal.find('.calebo-wl-error').text((resp.data && resp.data.mensaje) || 'Error al procesar la solicitud.').show();
                        }
                    }).fail(function() {
                        $modal.find('.calebo-wl-submit').prop('disabled', false).text(caleboStrings.send || 'Apuntarme');
                        $modal.find('.calebo-wl-error').text('Error de conexión. Inténtalo de nuevo.').show();
                    });
                });
            });
        });
    };

    // ── Seleccionar actividad y mostrar su formulario ──
    CBCalendar.prototype.seleccionarActividad = function(actividadId, actData) {
        this.actividadSeleccionada = actividadId;
        this._actividadData = actData;

        var partes     = this.fechaSeleccionada.split('-');
        var fechaLabel = partes[2] + '/' + partes[1] + '/' + partes[0];
        var horarioMostrar = (function(act, fecha) {
            if (!act.vacaciones || !act.vacaciones.length) return act.horario;
            for (var i = 0; i < act.vacaciones.length; i++) {
                var v = act.vacaciones[i];
                if (v.tipo === 'horario_especial' && fecha >= v.inicio && fecha <= v.fin) {
                    return act.horario_vacaciones || act.horario;
                }
            }
            return act.horario;
        })(actData, this.fechaSeleccionada);
        var label      = fechaLabel + '  \u00b7  ' + horarioMostrar;

        this.$wrap.find('.calebo-fecha-label').text(label);
        this.$wrap.find('.calebo-actividad-label').text(actData.nombre);
        this.$wrap.find('.calebo-plazas-info').text(
            (caleboStrings.available_places || 'Plazas disponibles:') + ' ' +
            actData.libres + ' ' + (caleboStrings.of_total || 'de') + ' ' + actData.limite
        );
        this.$wrap.find('.calebo-msg-error').hide().text('');

        // Renderizar campos de la actividad en .calebo-form-grid
        var $formGrid = this.$wrap.find('.calebo-form-grid');
        $formGrid.empty();

        var campos = actData.campos_extra || [];
        if (campos.length > 0) {
            $.each(campos, function(i, ce) {
                var fullClass = (ce.tipo === 'textarea' || ce.tipo === 'email') ? ' calebo-full' : '';
                var reqAttr   = ce.requerido ? ' required' : '';
                var reqLabel  = ce.requerido ? ' *' : '';
                var fieldHtml = '<div class="calebo-field' + fullClass + '">';
                fieldHtml += '<label>' + $('<span>').text(ce.label + reqLabel).html() + '</label>';

                if (ce.tipo === 'textarea') {
                    fieldHtml += '<textarea class="calebo-campo-extra" data-nombre="' + ce.nombre + '" placeholder="' + $('<span>').text(ce.placeholder || '').html() + '"' + reqAttr + '></textarea>';
                } else if (ce.tipo === 'select') {
                    fieldHtml += '<select class="calebo-campo-extra" data-nombre="' + ce.nombre + '"' + reqAttr + '>';
                    fieldHtml += '<option value="">\u2014 Selecciona \u2014</option>';
                    if (ce.opciones) {
                        var lines = ce.opciones.split(/\n/);
                        $.each(lines, function(j, ln) {
                            var v = $.trim(ln);
                            if (v) fieldHtml += '<option value="' + $('<span>').text(v).html() + '">' + $('<span>').text(v).html() + '</option>';
                        });
                    }
                    fieldHtml += '</select>';
                } else if (ce.tipo === 'checkbox') {
                    fieldHtml += '<label style="display:flex;align-items:center;gap:8px;font-weight:normal;">';
                    fieldHtml += '<input type="checkbox" class="calebo-campo-extra" data-nombre="' + ce.nombre + '"' + reqAttr + '>';
                    fieldHtml += $('<span>').text(ce.placeholder || '').html() + '</label>';
                } else {
                    fieldHtml += '<input type="' + ce.tipo + '" class="calebo-campo-extra" data-nombre="' + ce.nombre + '" placeholder="' + $('<span>').text(ce.placeholder || '').html() + '"' + reqAttr + '>';
                }
                fieldHtml += '</div>';
                $formGrid.append(fieldHtml);
            });
        }

        // Mostrar/ocultar paneles
        // Si hay múltiples actividades, mostrar botón "volver"
        var numEv = (this.disponibilidad[this.fechaSeleccionada] || {}).num_eventos || 0;
        if (numEv > 1) {
            this.$wrap.find('.calebo-actividad-row').show();
        } else {
            this.$wrap.find('.calebo-actividad-row').hide();
        }

        this.$wrap.find('.calebo-actividades-wrap').hide();
        this.$wrap.find('.calebo-form-wrap').show();

        $('html, body').animate({ scrollTop: this.$wrap.find('.calebo-form-wrap').offset().top - 20 }, 400);
    };

    // ── Enviar reserva ──
    CBCalendar.prototype.enviarReserva = function() {
        var self  = this;
        var $btn  = this.$wrap.find('.calebo-btn-reservar');

        this.$wrap.find('.calebo-msg-error').hide();

        if (!self.actividadSeleccionada) {
            this.$wrap.find('.calebo-msg-error')
                .text(caleboStrings.required_fields || 'Por favor, selecciona una actividad.')
                .show();
            return;
        }

        // Validar campos obligatorios
        var camposValidos = true;
        var camposData    = {};
        this.$wrap.find('.calebo-campo-extra').each(function() {
            var $el    = $(this);
            var nombre = $el.data('nombre');
            var val;
            if ($el.attr('type') === 'checkbox') {
                val = $el.is(':checked') ? '1' : '0';
                if ($el.prop('required') && !$el.is(':checked')) camposValidos = false;
            } else {
                val = $el.val() ? $el.val().trim() : '';
                if ($el.prop('required') && !val) camposValidos = false;
            }
            camposData[nombre] = val;
        });

        if (!camposValidos) {
            this.$wrap.find('.calebo-msg-error')
                .text(caleboStrings.required_fields || 'Por favor, rellena todos los campos obligatorios.')
                .show();
            return;
        }

        $btn.prop('disabled', true).text(caleboStrings.sending || 'Enviando...');

        var payload = {
            action:        'calebo_reservar',
            nonce:         caleboAjax.nonce,
            calendario_id: self.calendarioId,
            fecha:         self.fechaSeleccionada,
            actividad_id:  self.actividadSeleccionada
        };

        // Mapeo de campos: enviar como campos_extra + mapeo a campos estándar
        var stdKeys = ['nombre', 'email', 'telefono', 'num_menores', 'edades'];
        $.each(camposData, function(k, v) {
            payload['campos_extra[' + k + ']'] = v;
            if (stdKeys.indexOf(k) !== -1) {
                payload[k] = v;
            }
        });

        $.post(caleboAjax.url, payload, function(resp) {
            $btn.prop('disabled', false).text($btn.data('texto-original') || caleboStrings.confirm_button || 'Confirmar reserva');

            if (!resp.success) {
                self.$wrap.find('.calebo-msg-error').text(resp.data.mensaje).show();
                return;
            }

            var d = resp.data;

            // Si el servidor devuelve una URL de confirmación, redirigir a ella
            if (d.redirect_url) {
                window.location.href = d.redirect_url;
                return;
            }

            // Fallback: mostrar confirmación inline si no hay página de confirmación
            self.$wrap.find('.calebo-form-wrap').hide();
            self.$wrap.find('.calebo-actividades-wrap').hide();

            // SVG animated check
            var svgCheck = '<svg class="calebo-check-svg" viewBox="0 0 72 72">' +
                '<circle cx="36" cy="36" r="36"/>' +
                '<path fill="none" d="M22 36 l10 10 l18 -20"/>' +
                '</svg>';

            var detalle = '<strong>' + $('<span>').text(d.actividad || '').html() + '</strong><br>' +
                '<strong>' + d.fecha + '</strong> \u00b7 ' + d.horario;

            if (d.num_menores) {
                detalle += '<br>' + d.num_menores + ' ' + (caleboStrings.child_label || 'menor(es) apuntado(s)');
            }
            if (d.sala) {
                detalle += '<br><span style="font-size:0.9em;opacity:0.8;">' + $('<span>').text(d.sala).html() + '</span>';
            }

            // Replace static check circle with SVG
            var $conf = self.$wrap.find('.calebo-confirmacion');
            $conf.find('.calebo-check-circle').replaceWith(svgCheck);
            self.$wrap.find('.calebo-confirmacion-detalle').html(detalle);
            self.$wrap.find('.calebo-confirmacion-email').text(
                caleboStrings.email_notice || 'Te hemos enviado un correo de confirmaci\u00f3n con el enlace para cancelar si lo necesitas.'
            );
            $conf.show();

            // Mini confetti burst
            var colors = ['#F7941D', '#1E3FA3', '#4caf50', '#f44336', '#ffeb3b'];
            var $confWrap = $conf.css('position', 'relative');
            for (var ci = 0; ci < 20; ci++) {
                var $c = $('<div class="calebo-confetti"></div>').css({
                    left: (30 + Math.random() * 40) + '%',
                    top: '10%',
                    background: colors[ci % colors.length],
                    animationDelay: (Math.random() * 0.5) + 's',
                    animationDuration: (1 + Math.random()) + 's'
                });
                $confWrap.append($c);
                (function($el) { setTimeout(function() { $el.remove(); }, 2500); })($c);
            }

            self.cargarMes();
        });
    };

    $(document).ready(function() {
        $('.calebo-btn-reservar').each(function() {
            $(this).data('texto-original', $(this).text());
        });
        $('.calebo-wrap').each(function() {
            new CBCalendar(this);
        });
    });

})(jQuery);
