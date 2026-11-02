/* CalendiFlow – Admin Diseño: preview + font/background */
(function() {
    if (typeof caleboDisenoData === "undefined") return;

    var cbFuentesLocales = caleboDisenoData.fuentesLocales;
    var cbFuentesSistema = ["Arial","Helvetica","Tahoma","Verdana","Trebuchet MS","Georgia","Times New Roman"];

    window.caleboActualizarFuente = function(nombre) {
        var aviso   = document.getElementById("calebo-fuente-aviso");
        var info    = document.getElementById("calebo-fuente-info-local");
        var muestra = document.getElementById("calebo-fuente-muestra");
        if (cbFuentesSistema.indexOf(nombre) !== -1) {
            aviso.style.display = "none";
            info.style.display  = "none";
        } else if (cbFuentesLocales.indexOf(nombre) !== -1) {
            aviso.style.display = "none";
            info.style.display  = "block";
        } else {
            aviso.style.display = "block";
            info.style.display  = "none";
        }
        if (muestra) { muestra.style.fontFamily = "'" + nombre + "', Arial, sans-serif"; }
        caleboPreview();
    };

    window.caleboPreview = function() {
        var primary       = document.querySelector("[name='color_primary']").value;
        var fuente        = document.querySelector("[name='fuente']").value;
        var radio         = document.querySelector("[name='celda_radio']").value;
        var ancho         = document.querySelector("[name='celda_ancho']").value;
        var cBg           = document.querySelector("[name='completo_bg']").value;
        var cColor        = document.querySelector("[name='completo_color']").value;
        var cTexto        = document.querySelector("[name='completo_texto']").value;
        var cRot          = document.querySelector("[name='completo_rotacion']").value;
        var btnRadio      = document.querySelector("[name='btn_radio']").value;
        var btnTexto      = document.querySelector("[name='btn_texto']").value;
        var btnUpper      = document.querySelector("[name='btn_uppercase']").checked;
        var fondoTipo     = document.querySelector("[name='fondo_tipo']").value;
        var fondoColor    = document.querySelector("[name='fondo_color']").value;
        var fondoImg      = document.getElementById("calebo-fondo-imagen").value;
        var semanaTexto   = document.querySelector("[name='color_semana_texto']").value;
        var numerosColor  = document.querySelector("[name='color_numeros_mes']").value;
        var secondary     = document.querySelector("[name='color_secondary']").value;
        var multiColor    = document.querySelector("[name='multi_color']").value;
        var multiSize     = document.querySelector("[name='multi_size']").value + 'px';
        var multiForma    = document.querySelector("[name='multi_forma']:checked")
                            ? document.querySelector("[name='multi_forma']:checked").value
                            : 'circulo';

        var fontStack = "'" + fuente + "', Arial, sans-serif";
        var preview   = document.getElementById("calebo-preview");
        preview.style.fontFamily = fontStack;
        preview.querySelectorAll("*").forEach(function(el) { el.style.fontFamily = fontStack; });

        var muestra = document.getElementById("calebo-fuente-muestra");
        if (muestra) { muestra.style.fontFamily = fontStack; }

        document.getElementById("prev-header").style.background = primary;

        var semanaRow = document.getElementById("prev-semana-row");
        if (semanaRow) {
            semanaRow.style.background = secondary;
            semanaRow.querySelectorAll(".prev-dia-label").forEach(function(el) {
                el.style.color = semanaTexto;
            });
        }

        var multiDot = document.getElementById("prev-multi-dot");
        if (multiDot) {
            multiDot.style.background = multiColor;
            multiDot.style.width  = multiSize;
            multiDot.style.height = multiSize;
            multiDot.style.fontSize = (parseInt(multiSize) * 0.55) + 'px';
            var formaMap = {
                'circulo':    'border-radius:50%;clip-path:none',
                'cuadrado':   'border-radius:0;clip-path:none',
                'redondeado': 'border-radius:4px;clip-path:none',
                'diamante':   'border-radius:0;clip-path:polygon(50% 0%,100% 50%,50% 100%,0% 50%)',
                'estrella':   'border-radius:0;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%)',
            };
            var fCSS = (formaMap[multiForma] || formaMap['circulo']).split(';');
            fCSS.forEach(function(rule) {
                var parts = rule.split(':');
                if (parts.length >= 2) {
                    var prop = parts[0].trim().replace(/-([a-z])/g, function(m, l) { return l.toUpperCase(); });
                    var val  = parts.slice(1).join(':').trim();
                    multiDot.style[prop] = val;
                }
            });
        }

        document.querySelectorAll(".calebo-forma-preview").forEach(function(el) {
            el.style.background = multiColor;
            el.style.outline = el.dataset.forma === multiForma ? '2px solid #F7941D' : '2px solid transparent';
        });

        document.querySelectorAll("#prev-grid .prev-celda").forEach(function(c) {
            c.style.borderRadius = radio + "px";
            c.style.margin       = "auto";
            c.style.width        = ancho + "%";
            if (c.dataset.disponible === "1") {
                c.style.color = numerosColor;
            }
        });

        var grid = document.getElementById("prev-grid");
        if (fondoTipo === "imagen" && fondoImg) {
            grid.style.backgroundImage = "url(" + fondoImg + ")";
            grid.style.backgroundSize  = "cover";
            grid.style.backgroundColor = "";
        } else {
            grid.style.backgroundImage  = "";
            grid.style.backgroundColor = fondoColor;
        }

        var comp = document.getElementById("prev-completo");
        if (comp) {
            comp.style.background = cBg;
            comp.style.color      = cColor;
            comp.style.transform  = "translate(-50%,-50%) rotate(" + cRot + "deg)";
            comp.textContent      = cTexto;
        }

        var btn = document.getElementById("prev-btn");
        btn.style.background    = primary;
        btn.style.borderRadius  = btnRadio + "px";
        btn.style.textTransform = btnUpper ? "uppercase" : "none";
        btn.textContent         = btnTexto;
    };

    window.caleboToggleFondo = function(tipo) {
        document.getElementById("calebo-fondo-color-row").style.display  = tipo === "color"  ? "" : "none";
        document.getElementById("calebo-fondo-imagen-row").style.display = tipo === "imagen" ? "" : "none";
    };

    window.cbSeleccionarImagen = function() {
        var frame = wp.media({ title: "Seleccionar imagen de fondo", multiple: false });
        frame.on("select", function() {
            document.getElementById("calebo-fondo-imagen").value =
                frame.state().get("selection").first().toJSON().url;
            caleboPreview();
        });
        frame.open();
    };

    window.addEventListener("load", function() {
        caleboActualizarFuente(document.getElementById("calebo-fuente-select").value);
        document.querySelectorAll(".calebo-forma-preview").forEach(function(el) {
            el.style.cursor = "pointer";
            el.addEventListener("click", function() {
                var forma = el.dataset.forma;
                var radio = document.querySelector("[name='multi_forma'][value='" + forma + "']");
                if (radio) { radio.checked = true; caleboPreview(); }
            });
        });
    });
})();
