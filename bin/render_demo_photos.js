/**
 * Генератор демо-фото чемоданов (студийный рендер в SVG → PNG через Chromium).
 * Нужен только разработчику: готовые файлы лежат в bin/demo-photos/ и используются установщиком.
 *   php -r '...' > products.json   (см. bin/export_demo_products.php)
 *   node bin/render_demo_photos.js products.json bin/demo-photos
 * Требуется playwright (Chromium).
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const [, , jsonFile, outDir] = process.argv;
const products = JSON.parse(fs.readFileSync(jsonFile, 'utf8'));
fs.mkdirSync(outDir, { recursive: true });

// ---------- цвета ----------
const hex2rgb = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16));
const shade = (h, k) => {
  const [r, g, b] = hex2rgb(h);
  const f = c => Math.round(k >= 1 ? c + (255 - c) * (k - 1) : c * k);
  return '#' + [r, g, b].map(c => Math.max(0, Math.min(255, f(c))).toString(16).padStart(2, '0')).join('');
};
const lum = h => { const [r, g, b] = hex2rgb(h); return (0.299 * r + 0.587 * g + 0.114 * b) / 255; };

let uid = 0;
const id = p => p + (++uid);

// ---------- общие определения ----------
function defs() {
  return `
  <linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f7f8f9"/><stop offset="0.72" stop-color="#eceef0"/><stop offset="1" stop-color="#e2e5e8"/></linearGradient>
  <radialGradient id="floorGlow" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="#ffffff" stop-opacity=".9"/><stop offset="1" stop-color="#ffffff" stop-opacity="0"/></radialGradient>
  <filter id="blur30" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="30"/></filter>
  <filter id="blur10" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="10"/></filter>
  <filter id="blur4" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="4"/></filter>
  <filter id="blur2"><feGaussianBlur stdDeviation="1.6"/></filter>
  <filter id="fabric" x="0" y="0" width="100%" height="100%">
    <feTurbulence type="fractalNoise" baseFrequency="1.6 0.5" numOctaves="2" seed="4"/>
    <feColorMatrix type="saturate" values="0"/>
    <feComponentTransfer><feFuncA type="table" tableValues="0 0.55"/></feComponentTransfer>
  </filter>
  <filter id="grain" x="0" y="0" width="100%" height="100%">
    <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="3" seed="9"/>
    <feColorMatrix type="saturate" values="0"/>
    <feComponentTransfer><feFuncA type="table" tableValues="0 0.25"/></feComponentTransfer>
  </filter>
  <linearGradient id="alu" x1="0" x2="1"><stop offset="0" stop-color="#8d949a"/><stop offset=".35" stop-color="#e9edf0"/><stop offset=".55" stop-color="#b9c0c6"/><stop offset="1" stop-color="#7d848a"/></linearGradient>
  <linearGradient id="rubber" x1="0" x2="1"><stop offset="0" stop-color="#15181b"/><stop offset=".45" stop-color="#3b4046"/><stop offset="1" stop-color="#121417"/></linearGradient>
  <linearGradient id="grip" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4a5057"/><stop offset=".5" stop-color="#25292e"/><stop offset="1" stop-color="#121417"/></linearGradient>`;
}

// Горизонтальный градиент корпуса: объём за счёт затемнения краёв и блика
function bodyGrad(hex, kind) {
  const g = id('g');
  const hi = kind === 'alu' ? 1.35 : lum(hex) < 0.25 ? 1.45 : 1.18;
  return {
    id: g,
    svg: `<linearGradient id="${g}" x1="0" x2="1">
      <stop offset="0" stop-color="${shade(hex, 0.62)}"/><stop offset=".06" stop-color="${shade(hex, 0.9)}"/>
      <stop offset=".22" stop-color="${shade(hex, hi)}"/><stop offset=".42" stop-color="${shade(hex, 1.02)}"/>
      <stop offset=".8" stop-color="${shade(hex, 0.88)}"/><stop offset=".95" stop-color="${shade(hex, 0.7)}"/><stop offset="1" stop-color="${shade(hex, 0.55)}"/>
    </linearGradient>`,
  };
}
function sideGrad(hex) {
  const g = id('s');
  return { id: g, svg: `<linearGradient id="${g}" x1="0" x2="1"><stop offset="0" stop-color="${shade(hex, 0.62)}"/><stop offset=".5" stop-color="${shade(hex, 0.78)}"/><stop offset="1" stop-color="${shade(hex, 0.55)}"/></linearGradient>` };
}
const vertShade = () => {
  const g = id('v');
  return { id: g, svg: `<linearGradient id="${g}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".22"/><stop offset=".25" stop-color="#fff" stop-opacity="0"/><stop offset=".8" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".28"/></linearGradient>` };
};

function kindOf(material) {
  if (material.startsWith('Ткань')) return 'soft';
  if (material === 'Алюминий') return 'alu';
  return 'hard';
}

// ---------- детали ----------
function wheel(cx, groundY, s) {
  // поворотное колесо: кронштейн + сдвоенное колесо
  const w = 46 * s, h = 34 * s;
  return `<g>
    <path d="M${cx - w / 2} ${groundY - h - 26 * s} h${w} l${-6 * s} ${22 * s} h${-(w - 12 * s)} z" fill="url(#rubber)"/>
    <rect x="${cx - 26 * s}" y="${groundY - h - 6 * s}" width="${52 * s}" height="${h + 6 * s}" rx="${15 * s}" fill="#16191c"/>
    <rect x="${cx - 22 * s}" y="${groundY - h - 2 * s}" width="${18 * s}" height="${h - 2 * s}" rx="${8 * s}" fill="#2c3136"/>
    <rect x="${cx + 4 * s}" y="${groundY - h - 2 * s}" width="${18 * s}" height="${h - 2 * s}" rx="${8 * s}" fill="#2c3136"/>
    <circle cx="${cx - 13 * s}" cy="${groundY - h / 2 - 2 * s}" r="${4 * s}" fill="#8c949b"/>
    <circle cx="${cx + 13 * s}" cy="${groundY - h / 2 - 2 * s}" r="${4 * s}" fill="#8c949b"/>
  </g>`;
}

function teleHandle(x1, x2, topY, baseY, s) {
  const bw = 15 * s;
  const bar = x => `<rect x="${x - bw / 2}" y="${topY + 30 * s}" width="${bw}" height="${baseY - topY - 30 * s}" fill="url(#alu)"/>
    <rect x="${x - bw / 2 - 3 * s}" y="${topY + (baseY - topY) * 0.55}" width="${bw + 6 * s}" height="${8 * s}" rx="${2 * s}" fill="#6f777e"/>`;
  return `<g>${bar(x1)}${bar(x2)}
    <rect x="${x1 - 24 * s}" y="${topY}" width="${x2 - x1 + 48 * s}" height="${44 * s}" rx="${16 * s}" fill="url(#grip)"/>
    <rect x="${x1 - 16 * s}" y="${topY + 6 * s}" width="${x2 - x1 + 32 * s}" height="${8 * s}" rx="${4 * s}" fill="#fff" opacity=".12"/>
  </g>`;
}

function topHandle(cx, y, w, s) {
  return `<g>
    <rect x="${cx - w / 2 - 14 * s}" y="${y - 6 * s}" width="${22 * s}" height="${16 * s}" rx="${5 * s}" fill="#1b1e22"/>
    <rect x="${cx + w / 2 - 8 * s}" y="${y - 6 * s}" width="${22 * s}" height="${16 * s}" rx="${5 * s}" fill="#1b1e22"/>
    <path d="M${cx - w / 2} ${y} q0 ${-34 * s} ${24 * s} ${-34 * s} h${w - 48 * s} q${24 * s} 0 ${24 * s} ${34 * s}" fill="none" stroke="url(#grip)" stroke-width="${17 * s}" stroke-linecap="round"/>
  </g>`;
}

function badge(x, y, brand, s, dark) {
  return `<g><rect x="${x}" y="${y}" width="${96 * s}" height="${26 * s}" rx="${5 * s}" fill="url(#alu)" opacity="${dark ? 0.95 : 0.9}"/>
    <text x="${x + 48 * s}" y="${y + 18.5 * s}" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="${14 * s}" letter-spacing="${2.5 * s}" fill="#2a2f34">${brand.toUpperCase()}</text></g>`;
}

// Передняя панель чемодана (корпус) — общая для вида спереди и 3/4
function frontFace(x, y, w, h, hex, kind, brand) {
  const s = h / 760;
  const r = Math.min(w, h) * 0.13;
  const bg = bodyGrad(kind === 'alu' ? '#c3cad0' : hex, kind);
  const vs = vertShade();
  const clip = id('c');
  let out = `<defs>${bg.svg}${vs.svg}<clipPath id="${clip}"><rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${r}"/></clipPath></defs>`;
  out += `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${r}" fill="url(#${bg.id})"/>`;
  out += `<g clip-path="url(#${clip})">`;
  if (kind === 'hard' || kind === 'alu') {
    // рёбра жёсткости: пары тёмная/светлая линия
    const n = kind === 'alu' ? 9 : 4;
    for (let i = 1; i <= n; i++) {
      const lx = x + (w * i) / (n + 1);
      const gw = (kind === 'alu' ? 7 : 12) * s;
      out += `<rect x="${lx - gw}" y="${y + 50 * s}" width="${gw}" height="${h - 100 * s}" rx="${gw / 2}" fill="#000" opacity="${kind === 'alu' ? 0.16 : 0.22}" filter="url(#blur2)"/>`;
      out += `<rect x="${lx}" y="${y + 50 * s}" width="${gw * 0.8}" height="${h - 100 * s}" rx="${gw / 2}" fill="#fff" opacity="${kind === 'alu' ? 0.35 : 0.16}" filter="url(#blur2)"/>`;
    }
    out += `<rect x="${x}" y="${y}" width="${w}" height="${h}" filter="url(#grain)" opacity=".5"/>`;
  } else {
    // мягкий чемодан: ткань, передний карман на молнии
    out += `<rect x="${x}" y="${y}" width="${w}" height="${h}" filter="url(#fabric)" opacity=".55"/>`;
    const px = x + w * 0.12, py = y + h * 0.4, pw = w * 0.76, ph = h * 0.5;
    out += `<rect x="${px}" y="${py}" width="${pw}" height="${ph}" rx="${r * 0.6}" fill="${shade(hex, 0.82)}" stroke="${shade(hex, 0.6)}" stroke-width="${4 * s}"/>`;
    out += `<rect x="${px}" y="${py}" width="${pw}" height="${ph}" rx="${r * 0.6}" filter="url(#fabric)" opacity=".5"/>`;
    out += `<path d="M${px + 24 * s} ${py + 34 * s} H${px + pw - 24 * s}" stroke="#15181b" stroke-width="${7 * s}" stroke-dasharray="${3 * s} ${2 * s}"/>`;
    out += `<rect x="${px + pw - 70 * s}" y="${py + 30 * s}" width="${14 * s}" height="${38 * s}" rx="${4 * s}" fill="url(#alu)"/>`;
    out += `<path d="M${px + 14 * s} ${py - 30 * s} H${px + pw - 14 * s}" stroke="#15181b" stroke-width="${6 * s}" stroke-dasharray="${3 * s} ${2 * s}"/>`;
  }
  // блик (мягкий отражённый свет слева) и общий вертикальный свет
  out += `<ellipse cx="${x + w * 0.26}" cy="${y + h * 0.42}" rx="${w * 0.08}" ry="${h * 0.4}" fill="#fff" opacity="${kind === 'soft' ? 0.06 : 0.22}" filter="url(#blur30)"/>`;
  out += `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="url(#${vs.id})"/>`;
  out += `</g>`;
  // окантовка-кант и защитные уголки
  out += `<rect x="${x + 2 * s}" y="${y + 2 * s}" width="${w - 4 * s}" height="${h - 4 * s}" rx="${r}" fill="none" stroke="${shade(kind === 'alu' ? '#c3cad0' : hex, 1.4)}" stroke-opacity=".35" stroke-width="${3 * s}"/>`;
  if (kind !== 'soft') {
    const cg = kind === 'alu' ? 'url(#alu)' : '#1d2125';
    out += `<path d="M${x} ${y + h - r - 30 * s} v${30 * s} a${r} ${r} 0 0 0 ${r} ${r} h${30 * s} v${-14 * s} h${-26 * s} a${r - 14 * s} ${r - 14 * s} 0 0 1 ${-(r - 14 * s)} ${-(r - 14 * s)} v${-30 * s} z" fill="${cg}" opacity=".9"/>`;
    out += `<path d="M${x + w} ${y + h - r - 30 * s} v${30 * s} a${r} ${r} 0 0 1 ${-r} ${r} h${-30 * s} v${-14 * s} h${26 * s} a${r - 14 * s} ${r - 14 * s} 0 0 0 ${r - 14 * s} ${-(r - 14 * s)} v${-30 * s} z" fill="${cg}" opacity=".9"/>`;
  }
  out += badge(x + w / 2 - 48 * s, y + 70 * s, brand, s, lum(hex) < 0.3);
  return out;
}

// Боковая стенка (видна в 3/4 и сбоку): молния посередине, боковая ручка, замок TSA
function sideFace(x, y, d, h, hex, kind, s, skew = 0, noBg = false) {
  const r = Math.min(d, h) * 0.2;
  const sg = sideGrad(kind === 'alu' ? '#c3cad0' : hex);
  const zx = d * 0.5;
  let inner = noBg ? '' : `<defs>${sg.svg}</defs><rect x="0" y="0" width="${d}" height="${h}" rx="${r}" fill="url(#${sg.id})"/>`;
  if (kind === 'soft' && !noBg) inner += `<rect x="0" y="0" width="${d}" height="${h}" rx="${r}" filter="url(#fabric)" opacity=".5"/>`;
  if (kind === 'alu') {
    inner += `<rect x="${zx - 10 * s}" y="${18 * s}" width="${20 * s}" height="${h - 36 * s}" rx="${6 * s}" fill="#6c737a"/>`;
    inner += `<rect x="${zx - 22 * s}" y="${h * 0.3}" width="${44 * s}" height="${56 * s}" rx="${6 * s}" fill="url(#alu)" stroke="#555c62" stroke-width="${2 * s}"/>`;
    inner += `<rect x="${zx - 22 * s}" y="${h * 0.62}" width="${44 * s}" height="${56 * s}" rx="${6 * s}" fill="url(#alu)" stroke="#555c62" stroke-width="${2 * s}"/>`;
  } else {
    inner += `<rect x="${zx - 13 * s}" y="${10 * s}" width="${26 * s}" height="${h - 20 * s}" rx="${10 * s}" fill="#16191c"/>`;
    inner += `<line x1="${zx}" y1="${22 * s}" x2="${zx}" y2="${h - 22 * s}" stroke="#3d4349" stroke-width="${6 * s}" stroke-dasharray="${2.5 * s} ${2.5 * s}"/>`;
    inner += `<rect x="${zx - 9 * s}" y="${h * 0.18}" width="${18 * s}" height="${40 * s}" rx="${5 * s}" fill="url(#alu)"/>`;
    // кодовый замок TSA
    inner += `<rect x="${zx - 30 * s}" y="${h * 0.24}" width="${60 * s}" height="${80 * s}" rx="${10 * s}" fill="#1c2024"/>`;
    for (let i = 0; i < 3; i++) inner += `<rect x="${zx - 21 * s + i * 15 * s}" y="${h * 0.24 + 14 * s}" width="${12 * s}" height="${24 * s}" rx="${2 * s}" fill="#8b939a"/>`;
    inner += `<rect x="${zx - 12 * s}" y="${h * 0.24 + 50 * s}" width="${24 * s}" height="${12 * s}" rx="${3 * s}" fill="#b02a2a"/>`;
  }
  // боковая ручка
  inner += `<rect x="${zx - d * 0.3}" y="${h * 0.48}" width="${d * 0.6}" height="${24 * s}" rx="${12 * s}" fill="url(#grip)"/>`;
  inner += `<rect x="0" y="0" width="${d}" height="${h}" rx="${r}" fill="url(#${vertShade().id})" opacity="0"/>`;
  return `<g transform="translate(${x} ${y}) skewY(${skew})">${inner}</g>`;
}

// ---------- сцены ----------
function sizeH(size) { return { S: 520, M: 610, L: 680, XL: 710 }[size] || 610; }

function suitcase34(cx, groundY, H, p, kind, s0 = 1) {
  const [hh, ww, dd] = p.dims && p.dims.length ? p.dims : [66, 45, 27];
  const h = H, w = h * (ww / hh), d = h * (dd / hh) * 0.6;
  const s = (h / 760) * s0;
  const wheelH = 62 * s;
  const y = groundY - wheelH - h;
  const x = cx - (w + d) / 2;
  const skew = -16;
  const rise = Math.tan((skew * Math.PI) / 180) * d; // < 0: дальний край выше
  const body = kind === 'alu' ? '#c3cad0' : p.hex;
  const rr = Math.min(w, h) * 0.1;
  const P = pts => pts.map(q => q.map(v => v.toFixed(1)).join(',')).join(' ');
  const shell = (pts, fill) => `<polygon points="${P(pts)}" fill="${fill}" stroke="${fill}" stroke-width="${2 * rr}" stroke-linejoin="round"/>`;
  const sg = id('sd'), tg = id('tp');
  let g = `<defs>
    <linearGradient id="${sg}" x1="0" x2="1"><stop offset="0" stop-color="${shade(body, 0.6)}"/><stop offset=".35" stop-color="${shade(body, 0.74)}"/><stop offset="1" stop-color="${shade(body, 0.5)}"/></linearGradient>
    <linearGradient id="${tg}" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="${shade(body, 1.2)}"/><stop offset="1" stop-color="${shade(body, 0.95)}"/></linearGradient></defs>`;
  // тени на полу
  g += `<ellipse cx="${cx + d * 0.25}" cy="${groundY + 2}" rx="${(w + d) * 0.62}" ry="${36 * s}" fill="#000" opacity=".26" filter="url(#blur30)"/>`;
  g += `<ellipse cx="${cx + d * 0.1}" cy="${groundY - 4}" rx="${(w + d) * 0.46}" ry="${12 * s}" fill="#000" opacity=".32" filter="url(#blur10)"/>`;
  // заднее колесо
  g += wheel(x + w + d - 30 * s, groundY + rise * 0.9 - 4 * s, s * 0.88);
  // верхняя грань и боковая стенка — одна оболочка со скруглёнными рёбрами
  const top = [[x + rr, y + rr], [x + rr + d, y + rise + rr], [x + w + d - rr, y + rise + rr], [x + w - rr, y + rr]];
  const side = [[x + w - rr, y + rr], [x + w + d - rr, y + rise + rr], [x + w + d - rr, y + h + rise - rr], [x + w - rr, y + h - rr]];
  g += shell(side, `url(#${sg})`);
  if (kind === 'soft') g += `<g opacity=".45" filter="url(#blur2)">${shell(side, 'transparent')}</g>`;
  g += shell(top, `url(#${tg})`);
  // детали боковой стенки: молния, замок, ручка — в той же перспективе
  g += sideFace(x + w, y + rr * 0.3, d - rr * 0.6, h - rr * 0.6, p.hex, kind, s * 0.95, skew, true);
  // телескопическая ручка выходит из верхней грани, ближе к задней стороне
  const hx = x + w / 2 + d * 0.62, hy = y + rise * 0.62 + rr * 0.6;
  g += teleHandle(hx - w * 0.2, hx + w * 0.2, hy - 250 * s, hy + 4 * s, s);
  // верхняя ручка для переноски
  g += topHandle(x + w / 2 + d * 0.28, y + rise * 0.28 + rr * 0.9, w * 0.34, s);
  // передняя панель
  g += frontFace(x, y, w, h, p.hex, kind, p.brand);
  // световая кромка на стыке фасада и боковины
  g += `<line x1="${x + w - 3 * s}" y1="${y + rr * 1.2}" x2="${x + w - 3 * s}" y2="${y + h - rr * 1.2}" stroke="#fff" stroke-opacity=".18" stroke-width="${3 * s}"/>`;
  g += wheel(x + 44 * s, groundY, s);
  g += wheel(x + w - 40 * s, groundY, s);
  return g;
}

function suitcaseFront(cx, groundY, H, p, kind) {
  const [hh, ww] = p.dims && p.dims.length ? p.dims : [66, 45];
  const h = H, w = h * (ww / hh), s = h / 760;
  const y = groundY - 62 * s - h, x = cx - w / 2;
  let g = `<ellipse cx="${cx}" cy="${groundY + 4}" rx="${w * 0.7}" ry="${30 * s}" fill="#000" opacity=".26" filter="url(#blur30)"/>`;
  g += `<ellipse cx="${cx}" cy="${groundY - 2}" rx="${w * 0.5}" ry="${10 * s}" fill="#000" opacity=".35" filter="url(#blur10)"/>`;
  g += teleHandle(x + w * 0.3, x + w * 0.7, y - 250 * s, y + 30 * s, s);
  g += topHandle(cx, y + 4 * s, w * 0.36, s);
  g += frontFace(x, y, w, h, p.hex, kind, p.brand);
  g += wheel(x + 44 * s, groundY, s) + wheel(x + w - 44 * s, groundY, s);
  return g;
}

function suitcaseSide(cx, groundY, H, p, kind) {
  const [hh, , dd] = p.dims && p.dims.length ? p.dims : [66, 45, 27];
  const h = H, s = h / 760, d = h * (dd / hh) * 1.15;
  const y = groundY - 62 * s - h, x = cx - d / 2;
  const body = kind === 'alu' ? '#c3cad0' : p.hex;
  const bg = bodyGrad(body, kind);
  let g = `<defs>${bg.svg}</defs>`;
  g += `<ellipse cx="${cx}" cy="${groundY + 4}" rx="${d * 0.9}" ry="${30 * s}" fill="#000" opacity=".26" filter="url(#blur30)"/>`;
  g += teleHandle(x + d * 0.78, x + d * 0.78 + 1, y - 250 * s, y + 30 * s, s).replace(/width="([\d.]+)" height="([\d.]+)" rx="([\d.]+)" fill="url\(#grip\)"/, (m) => m);
  // профиль: две выпуклые половинки корпуса и молния между ними
  const r = d * 0.22;
  g += `<rect x="${x}" y="${y}" width="${d}" height="${h}" rx="${r}" fill="url(#${bg.id})"/>`;
  if (kind === 'soft') g += `<rect x="${x}" y="${y}" width="${d}" height="${h}" rx="${r}" filter="url(#fabric)" opacity=".5"/>`;
  g += sideFace(x + d * 0.18, y + 6 * s, d * 0.64, h - 12 * s, p.hex, kind, s, 0);
  g += `<ellipse cx="${x + d * 0.12}" cy="${y + h * 0.45}" rx="${d * 0.05}" ry="${h * 0.38}" fill="#fff" opacity=".18" filter="url(#blur10)"/>`;
  g += wheel(x + d * 0.22, groundY, s) + wheel(x + d * 0.78, groundY, s);
  return g;
}

function scene(p, view) {
  uid = 0;
  const kind = kindOf(p.material);
  const ground = 1050;
  let body = '';
  if (p.size === 'SET') {
    const set = [['L', [76, 50, 30]], ['M', [66, 45, 27]], ['S', [55, 40, 20]]];
    if (view === 'side') {
      set.forEach(([sz, dims], i) => { body += suitcaseSide(330 + i * 270, ground, sizeH(sz) * 0.78, { ...p, dims }, kind); });
    } else {
      const xs = view === 'angle' ? [340, 640, 900] : [300, 640, 930];
      set.forEach(([sz, dims], i) => {
        const H = sizeH(sz) * 0.8;
        body += view === 'angle' ? suitcase34(xs[i], ground - i * 0, H, { ...p, dims }, kind) : suitcaseFront(xs[i], ground, H, { ...p, dims }, kind);
      });
    }
  } else {
    const H = sizeH(p.size);
    body = view === 'angle' ? suitcase34(600, ground, H, p, kind) : view === 'front' ? suitcaseFront(600, ground, H, p, kind) : suitcaseSide(600, ground, H, p, kind);
  }
  return `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 1200 1200">
    <defs>${defs()}</defs>
    <rect width="1200" height="1200" fill="url(#bg)"/>
    <ellipse cx="600" cy="1060" rx="620" ry="120" fill="url(#floorGlow)"/>
    ${body}
  </svg>`;
}

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1200, height: 1200 }, deviceScaleFactor: 1 });
  const only = process.env.ONLY;
  for (const p of products) {
    if (only && !only.split(',').includes(p.sku)) continue;
    for (const view of ['angle', 'front', 'side']) {
      await page.setContent(`<html><body style="margin:0">${scene(p, view)}</body></html>`);
      await page.screenshot({ path: path.join(outDir, `${p.sku}-${view}.png`), clip: { x: 0, y: 0, width: 1200, height: 1200 } });
    }
    console.log('rendered', p.sku);
  }
  await browser.close();
})();
