export const predefinedLogos = {
  conad:     { name: 'Conad', color: '#e31b23' },
  coop:      { name: 'Coop', color: '#0066a1' },
  esselunga: { name: 'Esselunga', color: '#d52b1e' },
  carrefour: { name: 'Carrefour', color: '#004990' },
  auchan:    { name: 'Auchan', color: '#e2001a' },
  decathlon: { name: 'Decathlon', color: '#003a70' },
  mediaworld: { name: 'MediaWorld', color: '#d71921' },
  unieuro:   { name: 'Unieuro', color: '#e30613' },
  euronics:  { name: 'Euronics', color: '#ed1c24' },
  pam:       { name: 'PAM', color: '#00843d' },
  lidl:      { name: 'Lidl', color: '#0050aa' },
  aldi:      { name: 'Aldi', color: '#003d7a' },
  tigota:    { name: 'Tigotà', color: '#e6007e' },
  acquaesapone: { name: 'Acqua & Sapone', color: '#0088ce' },
  bennet:    { name: 'Bennet', color: '#e30613' },
  iper:      { name: 'Iper', color: '#e31b23' },
  famila:    { name: 'Famila', color: '#00843d' },
  interspar: { name: 'Interspar', color: '#e30613' },
  despar:    { name: 'Despar', color: '#e30613' },
  simply:    { name: 'Simply', color: '#00ae41' },
  eurospin:  { name: 'Eurospin', color: '#e30613' },
  penny:     { name: 'Penny Market', color: '#ffd100' },
  tod:       { name: 'Todis', color: '#ed1c24' },
  md:        { name: 'MD', color: '#e30613' },
  ikea:      { name: 'IKEA', color: '#003399' },
  amazon:    { name: 'Amazon', color: '#ff9900' },
  tesserasanitaria: { name: 'Tessera Sanitaria', color: '#008080' },
}

export const barcodeTypeDefaultLogo = {
  FISCALCODE: 'tesserasanitaria',
}

function hashColor(str) {
  let hash = 0
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash)
  }
  const hue = Math.abs(hash) % 360
  return `hsl(${hue}, 55%, 45%)`
}

export function placeholderSvg(color, letter) {
  return `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" rx="8" fill="${color}"/><text x="20" y="28" text-anchor="middle" font-size="18" fill="#fff" font-family="sans-serif">${letter}</text></svg>`)}`
}

export function textPlaceholderSvg(storeName) {
  const name = (storeName || '?').trim()
  const color = hashColor(name)
  const W = 200, H = 136
  let fontSize = 28
  while (fontSize > 10 && name.length * fontSize * 0.6 > W - 20) fontSize -= 2
  const lines = []
  if (name.length * fontSize * 0.6 > W - 20) {
    const mid = Math.ceil(name.length / 2)
    const l1 = name.slice(0, mid), l2 = name.slice(mid)
    let fs2 = fontSize
    while (fs2 > 10 && (l1.length + l2.length) * fs2 * 0.6 > (W - 20) * 2) fs2 -= 2
    lines.push(`<text x="${W/2}" y="${H/2 - fs2*0.3}" text-anchor="middle" font-size="${fs2}" fill="#fff" font-family="sans-serif" font-weight="bold">${escSvg(l1)}</text>`)
    lines.push(`<text x="${W/2}" y="${H/2 + fs2*1.1}" text-anchor="middle" font-size="${fs2}" fill="#fff" font-family="sans-serif" font-weight="bold">${escSvg(l2)}</text>`)
  } else {
    lines.push(`<text x="${W/2}" y="${H/2 + fontSize*0.35}" text-anchor="middle" font-size="${fontSize}" fill="#fff" font-family="sans-serif" font-weight="bold">${escSvg(name)}</text>`)
  }
  return `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}"><rect width="${W}" height="${H}" rx="12" fill="${color}"/>${lines.join('')}</svg>`)}`
}

function escSvg(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') }
