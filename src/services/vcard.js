function escapeVCard(value) {
  return String(value == null ? '' : value)
    .replace(/\\/g, '\\\\')
    .replace(/;/g, '\\;')
    .replace(/,/g, '\\,')
    .replace(/\r?\n/g, '\\n')
}

function joinLines(parts, eol) {
  return parts.filter(Boolean).join(eol) + eol
}

function foldValue(value, eol) {
  const limit = 75
  const parts = []
  let rest = value
  while (rest.length > limit) {
    parts.push(rest.slice(0, limit))
    rest = rest.slice(limit)
  }
  parts.push(rest)
  return parts.join(eol + ' ')
}

function photoLine(bc, eol) {
  const dataUrl = bc.avatar_data
  if (!dataUrl || typeof dataUrl !== 'string') return null
  const m = /^data:([^;,]+);base64,(.*)$/s.exec(dataUrl)
  if (!m || !m[2]) return null
  const b64 = m[2].replace(/\s+/g, '')
  if (!b64) return null
  const type = (m[1].split('/')[1] || 'jpeg').toUpperCase()
  return `PHOTO;ENCODING=b;TYPE=${type}:` + foldValue(b64, eol)
}

export function buildVCard(bc, eol = '\r\n', opts = {}) {
  const includePhoto = opts.includePhoto !== false
  const lines = [
    'BEGIN:VCARD',
    'VERSION:3.0',
  ]

  const lastName = escapeVCard(bc.last_name)
  const firstName = escapeVCard(bc.first_name)
  lines.push(`N:${lastName};${firstName};;;`)
  const fullName = escapeVCard(bc.fullName) || [firstName, lastName].filter(Boolean).join(' ')
  lines.push(`FN:${fullName}`)

  if (includePhoto) {
    const photo = photoLine(bc, eol)
    if (photo) lines.push(photo)
  }

  if (bc.org) lines.push(`ORG:${escapeVCard(bc.org)}`)
  if (bc.role) lines.push(`TITLE:${escapeVCard(bc.role)}`)

  if (bc.phone_personal) lines.push(`TEL;TYPE=CELL:${escapeVCard(bc.phone_personal)}`)
  if (bc.phone_business) lines.push(`TEL;TYPE=WORK:${escapeVCard(bc.phone_business)}`)

  if (bc.email) lines.push(`EMAIL;TYPE=WORK,INTERNET:${escapeVCard(bc.email)}`)

  if (bc.website) {
    const url = bc.website.trim()
    const normalized = /^https?:\/\//i.test(url) ? url : 'https://' + url
    lines.push(`URL:${escapeVCard(normalized)}`)
  }

  if (bc.address || bc.city || bc.postal_code || bc.country || bc.province) {
    const adr = [
      escapeVCard(bc.address || ''),
      '',
      '',
      escapeVCard(bc.city || ''),
      escapeVCard(bc.province || ''),
      escapeVCard(bc.postal_code || ''),
      escapeVCard(bc.country || ''),
    ]
    lines.push(`ADR;TYPE=WORK:${adr.join(';')}`)
  }

  if (bc.notes) lines.push(`NOTE:${escapeVCard(bc.notes)}`)

  lines.push('END:VCARD')

  return joinLines(lines, eol)
}

function loadImage(src) {
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.onload = () => resolve(img)
    img.onerror = (e) => reject(e)
    img.src = src
  })
}

function downscaleAvatar(dataUrl, maxDim, quality) {
  return loadImage(dataUrl).then((img) => {
    const scale = Math.min(1, maxDim / Math.max(img.width, img.height))
    const w = Math.max(1, Math.round(img.width * scale))
    const h = Math.max(1, Math.round(img.height * scale))
    const canvas = document.createElement('canvas')
    canvas.width = w
    canvas.height = h
    const ctx = canvas.getContext('2d')
    ctx.drawImage(img, 0, 0, w, h)
    return canvas.toDataURL('image/jpeg', quality)
  })
}

export async function buildVCardForNfc(bc, eol = '\r\n') {
  const notes = [bc.notes, VCF_FOOTER_VCARD].filter(Boolean).join(' | ')
  const patched = notes !== (bc.notes || '') ? { ...bc, notes } : bc
  let card = patched
  if (bc.avatar_data && /^data:image\//.test(bc.avatar_data)) {
    try {
      card = { ...patched, avatar_data: await downscaleAvatar(bc.avatar_data, 256, 0.7) }
    } catch {}
  }
  return buildVCard(card, eol)
}

export const VCF_SHARE_MESSAGE_KEY = 'vcf_share_message'

export const VCF_FOOTER_VCARD = 'Biglietto da Visita condiviso tramite FidAPPti.web - https://fidappti.altervista.org/webapp'

export const VCF_FOOTER =
  '\n\n---\nBiglietto da Visita condiviso con FidAPPti\nhttps://fidappti.altervista.org'

export const VCF_SHARE_DEFAULT_MESSAGE =
  'Il file allegato a questo messaggio contiene il Biglietto da Visita di {nome}. Aprilo per aggiungere il contatto alla tua rubrica.'

export function buildVcfShareText(bc, customMessage) {
  const values = {
    cognome: bc.last_name || '',
    nome: bc.first_name || '',
    azienda: bc.org || '',
  }
  const full = [values.cognome, values.nome].filter(Boolean).join(' ')
  const text = String(customMessage || '').trim() || VCF_SHARE_DEFAULT_MESSAGE.replace('{nome}', full)
  return text
    .replace(/<cognome>/g, values.cognome)
    .replace(/<nome>/g, values.nome)
    .replace(/<azienda>/g, values.azienda)
    + VCF_FOOTER
}
