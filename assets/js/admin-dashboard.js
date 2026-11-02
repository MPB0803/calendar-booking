/* CalendiFlow – Dashboard: copy-to-clipboard */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.calebo-copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (!target) return;
            var text = target.textContent || target.innerText;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    var orig = btn.innerHTML;
                    btn.innerHTML = '✓';
                    btn.style.color = '#2e7d32';
                    setTimeout(function() { btn.innerHTML = orig; btn.style.color = ''; }, 1500);
                });
            } else {
                var range = document.createRange();
                range.selectNodeContents(target);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
                document.execCommand('copy');
                sel.removeAllRanges();
                var orig = btn.innerHTML;
                btn.innerHTML = '✓';
                setTimeout(function() { btn.innerHTML = orig; }, 1500);
            }
        });
    });
});
