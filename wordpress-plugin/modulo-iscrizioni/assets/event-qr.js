(() => {
  'use strict';
  const loadImage = source => new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error('Il logo non può essere letto. Controlla il logo del gruppo.'));
    image.src = source;
  });
  async function png(data) {
    const qr = window.qrcode(0, 'H');
    // La modalità a byte codifica l'intero URL, compresi i caratteri non ASCII.
    const bytes = new TextEncoder().encode(data.url);
    qr.addData(Array.from(bytes, byte => String.fromCharCode(byte)).join(''), 'Byte');
    qr.make();
    const count = qr.getModuleCount(), quiet = 4;
    const scale = Math.max(6, Math.ceil(1000 / (count + 2 * quiet)));
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = (count + 2 * quiet) * scale;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#000';
    for (let y = 0; y < count; y++) for (let x = 0; x < count; x++) {
      if (qr.isDark(y, x)) ctx.fillRect((x + quiet) * scale, (y + quiet) * scale, scale, scale);
    }
    const logo = await loadImage(data.logo);
    // Mantiene la base bianca centrale sotto il 18% della larghezza e conserva la zona libera.
    const plate = Math.max(3, Math.floor(count * 0.17) | 1) * scale;
    const start = (canvas.width - plate) / 2, available = plate - scale;
    ctx.fillStyle = '#fff'; ctx.fillRect(start, start, plate, plate);
    const ratio = Math.min(available / logo.naturalWidth, available / logo.naturalHeight);
    const width = logo.naturalWidth * ratio, height = logo.naturalHeight * ratio;
    ctx.drawImage(logo, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height);
    return new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Generazione PNG non riuscita.')), 'image/png'));
  }
  function init(root) {
    if (root.dataset.miQrReady) return;
    root.dataset.miQrReady = '1';
    const status = root.querySelector('[data-mi-qr-status]');
    const button = root.querySelector('[data-mi-qr-download]');
    const preview = root.querySelector('[data-mi-qr-preview]');
    let objectUrl = '';
    async function generate(download = false) {
      button.disabled = true;
      status.textContent = 'Preparazione del PNG…';
      try {
        // Legge i dati correnti anche se la scheda è rimasta aperta durante la modifica dell'evento da parte di un altro operatore.
        const response = await fetch(root.dataset.miEventQr, {credentials: 'same-origin', cache: 'no-store'});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(typeof result.data === 'string' ? result.data : 'Ricarica la pagina e riprova.');
        const blob = await png(result.data);
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(blob);
        preview.src = objectUrl; preview.hidden = false;
        status.textContent = 'PNG pronto con il logo del gruppo. Link e logo vengono aggiornati a ogni download.';
        button.textContent = 'Scarica QR in PNG';
        if (download) {
          const link = document.createElement('a');
          link.href = objectUrl; link.download = result.data.filename;
          document.body.appendChild(link); link.click(); link.remove();
        }
      } catch (error) {
        preview.hidden = true;
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = ''; }
        status.textContent = error instanceof Error ? error.message : 'Il link è troppo lungo per il QR. Usa un link evento abbreviato.';
        button.textContent = 'Riprova a generare il PNG';
      } finally { button.disabled = false; }
    }
    button.addEventListener('click', () => generate(true));
    generate();
  }
  const scan = () => document.querySelectorAll('[data-mi-event-qr]').forEach(init);
  scan();
  new MutationObserver(scan).observe(document.body, {childList: true, subtree: true});
})();
