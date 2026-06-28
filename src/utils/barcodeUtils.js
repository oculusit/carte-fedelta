const eanPrefixes = new Set([
  '30','31','32','33','34','35','36','37','38','39',
  '40','41','42','43','44','45','46','47','48','49',
  '50','51','52','53','54','55','56','57','58','59',
  '60','61','62','63','64','65','66','67','68','69',
  '70','71','72','73','74','75','76','77','78','79',
  '80','81','82','83','84','85','86','87','88','89',
  '90','91','92','93','94','95','96','97','98','99',
])

export function detectBarcodeType(code) {
  if (!code) return 'CODE128'
  const clean = code.replace(/[\s-]/g, '')
  const isNumeric = /^\d+$/.test(clean)
  const len = clean.length

  if (/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z0-9]{3,4}[A-Z]$/.test(clean.toUpperCase())) {
    return 'FISCALCODE'
  }

  if (isNumeric) {
    if (len === 8) return 'EAN8'
    if (len === 12) {
      if (eanPrefixes.has(clean.substring(0, 2))) return 'EAN13'
      return 'UPC'
    }
    if (len === 13) return 'EAN13'
    if (len === 14) return 'ITF'
    if (len >= 3 && len <= 6) return 'pharmacode'
    if (len > 14) return 'CODE128'
    return 'CODE128'
  }

  if (/^https?:\/\//i.test(clean) || len > 40) return 'QR'

  const isCode39 = /^[A-Z0-9\-\.\ \$\/\+\%]+$/.test(clean.toUpperCase())
  if (isCode39 && len <= 20) return 'CODE39'

  return 'CODE128'
}

export function formatCardNumber(code, type) {
  if (!code) return code
  const clean = code.replace(/[\s-]/g, '')
  if (type === 'EAN13' || type === 'EAN8' || type === 'UPC' || type === 'ITF') {
    return clean.match(/.{1,4}/g)?.join(' ') || clean
  }
  return clean.match(/.{1,4}/g)?.join(' ') || clean
}

export function validateItalianFiscalCode(code) {
  const clean = code.replace(/[\s-]/g, '').toUpperCase()
  if (!/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z0-9]{3,4}[A-Z]$/.test(clean)) {
    return { valid: false, reason: 'Formato codice fiscale non valido' }
  }

  const oddTable = {
    '0':1,'1':0,'2':5,'3':7,'4':9,'5':13,'6':15,'7':17,'8':19,'9':21,
    'A':1,'B':0,'C':5,'D':7,'E':9,'F':13,'G':15,'H':17,'I':19,'J':21,
    'K':2,'L':4,'M':18,'N':20,'O':11,'P':3,'Q':6,'R':8,'S':12,'T':14,
    'U':16,'V':10,'W':22,'X':25,'Y':24,'Z':23,
  }
  const evenTable = {
    '0':0,'1':1,'2':2,'3':3,'4':4,'5':5,'6':6,'7':7,'8':8,'9':9,
    'A':0,'B':1,'C':2,'D':3,'E':4,'F':5,'G':6,'H':7,'I':8,'J':9,
    'K':10,'L':11,'M':12,'N':13,'O':14,'P':15,'Q':16,'R':17,'S':18,'T':19,
    'U':20,'V':21,'W':22,'X':23,'Y':24,'Z':25,
  }

  const controlChar = clean[15]
  let sum = 0
  for (let i = 0; i < 15; i++) {
    sum += i % 2 === 0 ? (oddTable[clean[i]] ?? 0) : (evenTable[clean[i]] ?? 0)
  }
  const expected = String.fromCharCode(65 + (sum % 26))

  if (controlChar !== expected) {
    return { valid: false, reason: `Codice fiscale non valido (carattere di controllo errato, atteso ${expected})` }
  }
  return { valid: true }
}

export function validateChecksum(code, type) {
  if (!code) return { valid: false, reason: 'Codice vuoto' }
  const clean = code.replace(/[\s-]/g, '')

  if (type === 'FISCALCODE') return validateItalianFiscalCode(clean)

  const digits = clean.split('').map(Number)
  if (digits.some(isNaN)) return { valid: true }

  const checksumTypes = ['EAN13', 'EAN8', 'UPC', 'ITF']
  if (!checksumTypes.includes(type)) return { valid: true }

  let expectedLen
  let weights
  switch (type) {
    case 'EAN13':
      expectedLen = 13
      weights = digits.map((_, i) => i % 2 === 0 ? 1 : 3)
      break
    case 'UPC':
      expectedLen = 12
      weights = digits.map((_, i) => i % 2 === 0 ? 1 : 3)
      break
    case 'EAN8':
      expectedLen = 8
      weights = digits.map((_, i) => i % 2 === 0 ? 1 : 3)
      break
    case 'ITF':
      expectedLen = 14
      weights = digits.map((_, i) => i % 2 === 0 ? 3 : 1)
      break
    default:
      return { valid: true }
  }

  if (digits.length !== expectedLen) return { valid: true }

  const checkDigit = digits[expectedLen - 1]
  const dataDigits = digits.slice(0, expectedLen - 1)
  const sum = dataDigits.reduce((acc, d, i) => acc + d * weights[i], 0)
  const expected = (10 - (sum % 10)) % 10

  if (checkDigit !== expected) {
    return { valid: false, reason: `Checksum non valido (atteso ${expected}, ricevuto ${checkDigit})` }
  }
  return { valid: true }
}

const barcodeFormats = [
  { value: 'CODE128', label: 'CODE128 (alphanumerico)' },
  { value: 'EAN13', label: 'EAN-13 (13 cifre)' },
  { value: 'EAN8', label: 'EAN-8 (8 cifre)' },
  { value: 'UPC', label: 'UPC-A (12 cifre)' },
  { value: 'CODE39', label: 'CODE39 (alphanumerico)' },
  { value: 'ITF', label: 'ITF-14 (14 cifre)' },
  { value: 'pharmacode', label: 'Pharmacode' },
  { value: 'QR', label: 'QR Code' },
  { value: 'FISCALCODE', label: 'Codice Fiscale' },
]

const scannerFormats = [
  'CODE_128', 'EAN_13', 'EAN_8', 'UPC_A', 'UPC_E',
  'CODE_39', 'ITF', 'QR_CODE', 'AZTEC', 'DATA_MATRIX',
  'PDF_417', 'CODABAR', 'CODE_93',
]

function mapFormatToScanner(format) {
  const map = {
    CODE128: 'CODE_128',
    EAN13: 'EAN_13',
    EAN8: 'EAN_8',
    UPC: 'UPC_A',
    CODE39: 'CODE_39',
    ITF: 'ITF',
    pharmacode: 'CODE_128',
    QR: 'QR_CODE',
    FISCALCODE: 'CODE_128',
  }
  return map[format] || 'CODE_128'
}

export { barcodeFormats, scannerFormats, mapFormatToScanner }
