/* CalendiFlow – Admin Actividades: toggle + festivos/vacaciones AJAX */
(function() {
    /* Toggle switch de lista de espera */
    var cb = document.getElementById("calebo-toggle-espera");
    var sl = cb ? cb.nextElementSibling : null;
    if (cb && sl) {
        cb.addEventListener("change", function() {
            var dot = sl.querySelector("span");
            if (this.checked) {
                sl.style.background = "#F7941D";
                if (dot) dot.style.left = "24px";
            } else {
                sl.style.background = "#ccc";
                if (dot) dot.style.left = "3px";
            }
        });
    }
})();

/* Filtro de calendario, toggle de configuración avanzada y confirmar eliminación */
(function() {
    var filtro = document.getElementById("calebo-filtro-calendario");
    if (filtro) filtro.addEventListener("change", function() { this.form.submit(); });

    var chkConfig = document.getElementById("calebo-toggle-config");
    if (chkConfig) {
        chkConfig.addEventListener("change", function() {
            var sec = document.getElementById("calebo-config-avanzada");
            if (sec) sec.style.display = this.checked ? "" : "none";
        });
    }

    document.querySelectorAll(".calebo-confirm-eliminar").forEach(function(el) {
        el.addEventListener("click", function(e) {
            var msg = el.getAttribute("data-confirm") || "¿Eliminar?";
            if (!window.confirm(msg)) e.preventDefault();
        });
    });
})();

(function() {
    if (typeof caleboActividadData === "undefined") return;

    var actividadId    = caleboActividadData.actividadId;
    var nonceF         = caleboActividadData.nonceF;
    var nonceV         = caleboActividadData.nonceV;
    var s              = caleboActividadData.strings;
    var festivosList   = document.getElementById("calebo-festivos-list");
    var vacacionesList = document.getElementById("calebo-vacaciones-list");

    if (!festivosList || !vacacionesList) return;

    festivosList.addEventListener("click", function(e) {
        if (e.target.classList.contains("calebo-remove-festivo")) {
            e.target.closest(".calebo-festivo-item").remove();
            saveFestivos();
        }
    });

    document.getElementById("calebo-add-festivo").addEventListener("click", function() {
        var label = document.getElementById("festivo-label").value.trim();
        var fecha = document.getElementById("festivo-fecha").value;
        if (!label || !fecha) { alert(s.rellenaNombreFecha); return; }
        var fechaFmt = new Date(fecha + "T00:00:00");
        var fechaStr = fechaFmt.toLocaleDateString("es-ES", {day:"2-digit",month:"2-digit",year:"numeric"});
        var item = document.createElement("div");
        item.className = "calebo-festivo-item";
        item.setAttribute("data-fecha", fecha);
        item.setAttribute("data-label", label);
        item.style.cssText = "display:flex;align-items:center;gap:12px;padding:12px;background:#f9f9f9;border-radius:6px;margin-bottom:8px;";
        item.innerHTML =
            "<span style=\"font-size:18px;color:#888;\">📅</span>" +
            "<span style=\"flex:1;font-weight:500;\">" + label + "</span>" +
            "<span style=\"color:#888;font-size:13px;\">" + fechaStr + "</span>" +
            "<button type=\"button\" class=\"button button-small calebo-remove-festivo\" style=\"color:#c62828;border-color:#ef9a9a;\">✕</button>";
        festivosList.appendChild(item);
        document.getElementById("festivo-label").value = "";
        document.getElementById("festivo-fecha").value = "";
        saveFestivos();
    });

    function saveFestivos() {
        var festivos = [];
        festivosList.querySelectorAll(".calebo-festivo-item").forEach(function(item) {
            var fecha = item.getAttribute("data-fecha");
            var label = item.getAttribute("data-label");
            if (fecha) festivos.push({ label: label || "", fecha: fecha });
        });
        document.getElementById("calebo-festivos-guardando").style.display = "block";
        document.getElementById("calebo-festivos-guardado").style.display  = "none";
        var formData = new FormData();
        formData.append("action", "calebo_guardar_festivos_actividad");
        formData.append("_ajax_nonce", nonceF);
        formData.append("actividad_id", actividadId);
        festivos.forEach(function(f, i) {
            formData.append("festivos[" + i + "][label]", f.label);
            formData.append("festivos[" + i + "][fecha]", f.fecha);
        });
        fetch(ajaxurl, { method: "POST", body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById("calebo-festivos-guardando").style.display = "none";
            if (data.success) {
                document.getElementById("calebo-festivos-guardado").style.display = "block";
                setTimeout(function() { document.getElementById("calebo-festivos-guardado").style.display = "none"; }, 3000);
            }
        });
    }

    vacacionesList.addEventListener("click", function(e) {
        if (e.target.classList.contains("calebo-remove-vacaciones")) {
            e.target.closest(".calebo-vacaciones-item").remove();
            saveVacaciones();
        }
    });

    document.getElementById("calebo-add-vacaciones").addEventListener("click", function() {
        var label  = document.getElementById("vacaciones-label").value.trim();
        var inicio = document.getElementById("vacaciones-inicio").value;
        var fin    = document.getElementById("vacaciones-fin").value;
        var tipo   = document.getElementById("vacaciones-tipo").value;
        if (!label || !inicio || !fin) { alert(s.rellenaCamposVac); return; }
        if (inicio > fin) { alert(s.fechaInicioAnterior); return; }
        var inicioStr = new Date(inicio + "T00:00:00").toLocaleDateString("es-ES", {day:"2-digit",month:"2-digit",year:"numeric"});
        var finStr    = new Date(fin    + "T00:00:00").toLocaleDateString("es-ES", {day:"2-digit",month:"2-digit",year:"numeric"});
        var tipoBg    = tipo === "bloqueado" ? "#ffebee" : "#FFF3E0";
        var tipoColor = tipo === "bloqueado" ? "#c62828" : "#F7941D";
        var tipoLabel = tipo === "bloqueado" ? "🚫 " + s.bloqueado : "🕐 " + s.horarioEspecial;
        var item = document.createElement("div");
        item.className = "calebo-vacaciones-item";
        item.setAttribute("data-inicio", inicio);
        item.setAttribute("data-fin",    fin);
        item.setAttribute("data-label",  label);
        item.setAttribute("data-tipo",   tipo);
        item.style.cssText = "display:flex;align-items:center;gap:12px;padding:12px;background:#f9f9f9;border-radius:6px;margin-bottom:8px;flex-wrap:wrap;";
        item.innerHTML =
            "<span style=\"font-size:18px;color:#888;\">🏖️</span>" +
            "<span style=\"flex:1;font-weight:500;min-width:120px;\">" + label + "</span>" +
            "<span style=\"color:#888;font-size:12px;\">" + inicioStr + " — " + finStr + "</span>" +
            "<span style=\"font-size:11px;padding:2px 8px;background:" + tipoBg + ";color:" + tipoColor + ";border-radius:4px;\">" + tipoLabel + "</span>" +
            "<button type=\"button\" class=\"button button-small calebo-remove-vacaciones\" style=\"color:#c62828;border-color:#ef9a9a;\">✕</button>";
        vacacionesList.appendChild(item);
        document.getElementById("vacaciones-label").value  = "";
        document.getElementById("vacaciones-inicio").value = "";
        document.getElementById("vacaciones-fin").value    = "";
        saveVacaciones();
    });

    function saveVacaciones() {
        var vacaciones = [];
        vacacionesList.querySelectorAll(".calebo-vacaciones-item").forEach(function(item) {
            var inicio = item.getAttribute("data-inicio");
            var fin    = item.getAttribute("data-fin");
            var label  = item.getAttribute("data-label");
            var tipo   = item.getAttribute("data-tipo") || "bloqueado";
            if (inicio && fin) vacaciones.push({ label: label || "", inicio: inicio, fin: fin, tipo: tipo });
        });
        document.getElementById("calebo-vacaciones-guardando").style.display = "block";
        document.getElementById("calebo-vacaciones-guardado").style.display  = "none";
        var formData = new FormData();
        formData.append("action", "calebo_guardar_vacaciones_actividad");
        formData.append("_ajax_nonce", nonceV);
        formData.append("actividad_id", actividadId);
        vacaciones.forEach(function(v, i) {
            formData.append("vacaciones[" + i + "][label]",  v.label);
            formData.append("vacaciones[" + i + "][inicio]", v.inicio);
            formData.append("vacaciones[" + i + "][fin]",    v.fin);
            formData.append("vacaciones[" + i + "][tipo]",   v.tipo);
        });
        fetch(ajaxurl, { method: "POST", body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById("calebo-vacaciones-guardando").style.display = "none";
            if (data.success) {
                document.getElementById("calebo-vacaciones-guardado").style.display = "block";
                setTimeout(function() { document.getElementById("calebo-vacaciones-guardado").style.display = "none"; }, 3000);
            }
        });
    }
})();
