/* CalendiFlow – Reports: PDF download via html2canvas + jsPDF */
document.addEventListener('DOMContentLoaded', function() {
    var btnBack  = document.getElementById('calebo-btn-back');
    var btnPrint = document.getElementById('calebo-btn-print');
    var btnPdf   = document.getElementById('calebo-btn-pdf');
    if (btnBack)  btnBack.addEventListener('click', function() { history.back(); });
    if (btnPrint) btnPrint.addEventListener('click', function() { window.print(); });
    if (btnPdf)   btnPdf.addEventListener('click', caleboDescargarPdf);
});

function caleboDescargarPdf() {
    var btn = document.getElementById('calebo-btn-pdf');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Generando…';
    }

    var target = document.querySelector('.calebo-report-wrap');
    if (!target) {
        alert('No se encontró el contenido del informe.');
        if (btn) { btn.disabled = false; btn.textContent = '📥 Descargar PDF'; }
        return;
    }

    var A4_W_MM = 210;
    var A4_H_MM = 297;
    var SCALE = 2;

    html2canvas(target, {
        scale:            SCALE,
        useCORS:          true,
        allowTaint:       true,
        backgroundColor:  '#ffffff',
        scrollX:          0,
        scrollY:          0,
        windowWidth:      target.scrollWidth,
        windowHeight:     target.scrollHeight,
        ignoreElements:   function(el) {
            return el.classList && el.classList.contains('calebo-action-bar');
        }
    }).then(function(canvas) {
        var jsPDF = window.jspdf.jsPDF;

        var imgW_mm = (canvas.width  / SCALE) * 0.2646;
        var imgH_mm = (canvas.height / SCALE) * 0.2646;

        var ratio   = 1;
        var margin  = 10;
        var usable  = A4_W_MM - margin * 2;
        if (imgW_mm > usable) {
            ratio   = usable / imgW_mm;
            imgW_mm = usable;
            imgH_mm = imgH_mm * ratio;
        }

        var pageH_px  = Math.floor((A4_H_MM - margin * 2) / 0.2646 * SCALE / ratio);
        var totalH_px = canvas.height;
        var numPages  = Math.ceil(totalH_px / pageH_px);

        var pdf = new jsPDF({
            orientation: imgW_mm > (A4_H_MM - margin * 2) ? 'landscape' : 'portrait',
            unit:        'mm',
            format:      'a4'
        });

        for (var page = 0; page < numPages; page++) {
            if (page > 0) pdf.addPage();

            var srcY   = page * pageH_px;
            var srcH   = Math.min(pageH_px, totalH_px - srcY);
            var sliceH_mm = srcH / SCALE * 0.2646 * ratio;

            var sliceCanvas = document.createElement('canvas');
            sliceCanvas.width  = canvas.width;
            sliceCanvas.height = srcH;
            var ctx = sliceCanvas.getContext('2d');
            ctx.drawImage(canvas, 0, srcY, canvas.width, srcH, 0, 0, canvas.width, srcH);

            var imgData = sliceCanvas.toDataURL('image/jpeg', 0.92);
            pdf.addImage(imgData, 'JPEG', margin, margin, imgW_mm, sliceH_mm);
        }

        var titulo   = (typeof caleboReportData !== 'undefined') ? caleboReportData.titulo   : 'Informe';
        var fechaFmt = (typeof caleboReportData !== 'undefined') ? caleboReportData.fechaFmt : '';
        var filename = (titulo + '-' + fechaFmt).replace(/[\/\\:*?"<>|]/g, '-') + '.pdf';
        pdf.save(filename);
    }).catch(function(err) {
        alert('No se pudo generar el PDF: ' + err.message);
    }).finally(function() {
        if (btn) { btn.disabled = false; btn.textContent = '📥 Descargar PDF'; }
    });
}
