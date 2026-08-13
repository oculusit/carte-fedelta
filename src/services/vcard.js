function escapeVCard(value) {
  return String(value == null ? '' : value)
    .replace(/\\/g, '\\\\')
    .replace(/;/g, '\\;')
    .replace(/,/g, '\\,')
    .replace(/\r?\n/g, '\\n')
}

function joinLines(parts) {
  return parts.filter(Boolean).join('\r\n') + '\r\n'
}

export function buildVCard(bc) {
  const lines = [
    'BEGIN:VCARD',
    'VERSION:3.0',
  ]

  const lastName = escapeVCard(bc.last_name)
  const firstName = escapeVCard(bc.first_name)
  lines.push(`N:${lastName};${firstName};;;`)
  lines.push(`FN:${escapeVCard(bc.fullName)}`)

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

  if (bc.address || bc.city || bc.postal_code || bc.country) {
    const adr = [
      escapeVCard(bc.address || ''),
      '',
      '',
      escapeVCard(bc.city || ''),
      '',
      escapeVCard(bc.postal_code || ''),
      escapeVCard(bc.country || ''),
    ]
    lines.push(`ADR;TYPE=WORK:${adr.join(';')}`)
  }

  if (bc.notes) lines.push(`NOTE:${escapeVCard(bc.notes)}`)

  lines.push('END:VCARD')

  return joinLines(lines)
}
