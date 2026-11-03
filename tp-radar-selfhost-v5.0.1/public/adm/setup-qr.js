'use strict';
(() => {
  const target = document.getElementById('qrbox');
  if (!target || !window.TPRadarQR) return;
  try {
    const value = target.dataset.otpauth;
    const qr = new window.TPRadarQR(0, 1); // Error correction level L (1)
    qr.addData(value);
    qr.make();
    const count = qr.getModuleCount();
    const pad = 4, size = count + 2 * pad;
    let d = '';
    for (let y = 0; y < count; y++) {
      for (let x = 0; x < count; x++) {
        if (qr.isDark(y, x)) d += `M${x+pad} ${y+pad}h1v1h-1z`;
      }
    }
    target.textContent = '';
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'TOTP-Einrichtungscode zum Scannen mit der Authenticator-App');
    svg.setAttribute('shape-rendering', 'crispEdges');
    const bg = document.createElementNS(ns, 'rect');
    bg.setAttribute('width', size); bg.setAttribute('height', size); bg.setAttribute('fill', '#fff');
    const fg = document.createElementNS(ns, 'path');
    fg.setAttribute('d', d);fg.setAttribute('fill', '#000');
    svg.append(bg, fg);target.append(svg);
  } catch (error) {
    target.textContent = 'QR-Code konnte nicht erzeugt werden. Verwende den manuellen Schlüssel.';
  }
})();
