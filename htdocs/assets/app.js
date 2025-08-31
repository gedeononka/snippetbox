// Copier le code dans le presse-papiers avec fallback
document.addEventListener('DOMContentLoaded', function() {
    var buttons = document.querySelectorAll('[data-copy-target]');
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].addEventListener('click', function() {
            var target = document.querySelector(this.getAttribute('data-copy-target'));
            if (!target) return;

            var text = target.innerText;

            // Essaie navigator.clipboard si HTTPS
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    var original = this.innerText;
                    this.innerText = 'Copié !';
                    var btn = this;
                    setTimeout(function() { btn.innerText = original; }, 1500);
                }).catch(function(err) {
                    alert('Erreur lors de la copie : ' + err);
                });
            } else {
                // Fallback : textarea temporaire
                var textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try {
                    document.execCommand('copy');
                    var original = this.innerText;
                    this.innerText = 'Copié !';
                    var btn = this;
                    setTimeout(function() { btn.innerText = original; }, 1500);
                } catch (err) {
                    alert('Erreur lors de la copie : ' + err);
                }
                document.body.removeChild(textarea);
            }
        });
    }
});
