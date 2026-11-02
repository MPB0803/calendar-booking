/* CalendiFlow – Admin SMTP: proveedor selection */
(function() {
    if (typeof caleboSmtpData === "undefined") return;

    var proveedores     = caleboSmtpData.proveedores;
    var proveedorActivo = caleboSmtpData.proveedorActivo;

    window.cbSeleccionarProveedor = function(proveedor, el) {
        document.querySelectorAll("[name='proveedor']").forEach(function(r) {
            r.checked = r.value === proveedor;
        });
        document.querySelectorAll(".calebo-smtp-proveedor").forEach(function(l) {
            l.style.borderColor = "#e0e0e0";
            l.style.background  = "#fff";
        });
        if (el) el.style.borderColor = "#F7941D";
        if (el) el.style.background  = "#fff8f0";

        var wrap = document.getElementById("calebo-smtp-personalizado");
        if (proveedor === "personalizado") {
            wrap.style.display = "";
        } else {
            wrap.style.display = "none";
            if (proveedores[proveedor]) {
                document.querySelector("[name='host']").value         = proveedores[proveedor].host;
                document.querySelector("[name='puerto']").value       = proveedores[proveedor].puerto;
                document.querySelector("[name='encriptacion']").value = proveedores[proveedor].encriptacion;
            }
        }
    };

    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".calebo-smtp-proveedor").forEach(function(l) {
            var radio = l.querySelector("input[type='radio']");
            if (radio && radio.value === proveedorActivo) {
                l.style.borderColor = "#F7941D";
                l.style.background  = "#fff8f0";
            }
            l.addEventListener("click", function() {
                cbSeleccionarProveedor(l.getAttribute("data-proveedor"), l);
            });
        });
    });
})();
