/* Kleine Verbesserungen ohne Framework. Alles funktioniert auch ohne JavaScript. */
(function () {
  'use strict';

  function formatChf(cents) {
    var francs = Math.floor(cents / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '’');
    var rappen = String(cents % 100).padStart(2, '0');
    return francs + '.' + rappen;
  }

  function parseChf(value) {
    var clean = String(value).replace(/CHF|Fr\.|\s|'|’/g, '').replace(',', '.');
    if (!/^\d+(\.\d{1,2})?$/.test(clean)) return null;
    var parts = clean.split('.');
    return parseInt(parts[0], 10) * 100 + parseInt((parts[1] || '0').padEnd(2, '0'), 10);
  }

  // Drucken
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  // Kaufformular: Live-Vorschau, Betragsvorschläge, Zeichenzähler, Empfänger-E-Mail ein/aus
  var form = document.querySelector('[data-buy-form]');
  var preview = document.querySelector('[data-preview]');
  if (form && preview) {
    var amount = form.querySelector('#amount');
    var total = form.querySelector('[data-total]');
    var previewAmount = preview.querySelector('[data-preview-amount]');
    var previewRecipient = preview.querySelector('[data-preview-recipient]');
    var previewMessage = preview.querySelector('[data-preview-message]');
    var recipient = form.querySelector('#recipient_name');
    var message = form.querySelector('#message');
    var options = form.querySelectorAll('[data-amount-options] button');

    var updateAmount = function () {
      if (!amount) return;
      var cents = parseChf(amount.value);
      var min = parseInt(amount.dataset.min, 10);
      var shown = cents === null ? min : cents;
      previewAmount.textContent = formatChf(shown);
      total.textContent = 'CHF ' + formatChf(shown);
      options.forEach(function (b) {
        b.setAttribute('aria-pressed', String(parseChf(b.dataset.amount) === cents));
      });
    };
    options.forEach(function (b) {
      b.addEventListener('click', function () {
        amount.value = b.dataset.amount;
        updateAmount();
        amount.focus();
      });
    });
    if (amount) amount.addEventListener('input', updateAmount);

    var updateText = function () {
      var name = recipient.value.trim();
      previewRecipient.textContent = name ? 'Für ' + name : 'Für …';
      previewRecipient.hidden = false;
      var text = message.value.trim();
      previewMessage.textContent = text;
      previewMessage.hidden = text === '';
    };
    recipient.addEventListener('input', updateText);
    message.addEventListener('input', updateText);

    var counter = form.querySelector('[data-counter-for="message"]');
    var updateCounter = function () {
      if (counter) counter.textContent = message.value.length + ' / 300';
    };
    message.addEventListener('input', updateCounter);
    updateCounter();

    var emailField = form.querySelector('[data-recipient-email]');
    var radios = form.querySelectorAll('input[name="deliver_to"]');
    var updateDelivery = function () {
      var direct = form.querySelector('input[name="deliver_to"]:checked').value === 'recipient';
      emailField.hidden = !direct;
    };
    radios.forEach(function (r) { r.addEventListener('change', updateDelivery); });
    updateDelivery();
    updateAmount();
  }

  // Kasse: QR-Code mit der Kamera scannen (wo der Browser BarcodeDetector unterstützt)
  var scanButton = document.querySelector('[data-scan]');
  if (scanButton && 'BarcodeDetector' in window && navigator.mediaDevices) {
    scanButton.hidden = false;
    var video = document.querySelector('[data-scan-video]');
    var input = document.querySelector('[data-code-input]');
    var stream = null;
    var stop = function () {
      if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null;
      video.hidden = true;
      scanButton.textContent = 'Mit Kamera scannen';
    };
    scanButton.addEventListener('click', function () {
      if (stream) { stop(); return; }
      var detector = new window.BarcodeDetector({ formats: ['qr_code'] });
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (s) {
        stream = s;
        video.srcObject = s;
        video.hidden = false;
        video.play();
        scanButton.textContent = 'Kamera schliessen';
        var tick = function () {
          if (!stream) return;
          detector.detect(video).then(function (codes) {
            if (codes.length) {
              input.value = codes[0].rawValue;
              stop();
              input.form.submit();
            } else {
              requestAnimationFrame(tick);
            }
          }).catch(function () { requestAnimationFrame(tick); });
        };
        tick();
      }).catch(function () {
        scanButton.textContent = 'Kamera nicht verfügbar';
        scanButton.disabled = true;
      });
    });
  }

  // Farbwähler mit Textfeld verbinden
  document.querySelectorAll('[data-color-sync]').forEach(function (picker) {
    var text = document.getElementById(picker.dataset.colorSync);
    picker.addEventListener('input', function () { text.value = picker.value.toUpperCase(); });
    text.addEventListener('input', function () {
      if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) picker.value = text.value;
    });
  });

  // Sicherheitsabfrage für heikle Aktionen
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.dataset.confirm)) ev.preventDefault();
    });
  });
})();
