const AdmZip = require('adm-zip')
const path = require('path')
const fs = require('fs')

const ROOT = path.resolve('.')
const DEPLOY = path.resolve(ROOT, 'deploy')
const ZIP = path.resolve(ROOT, 'progetto-cards.zip')

const zip = new AdmZip()

function addRecursive(dir, basePath) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, entry.name)
    const entryPath = basePath ? `${basePath}/${entry.name}` : entry.name
    if (entry.isDirectory()) {
      addRecursive(fullPath, entryPath)
    } else {
      zip.addLocalFile(fullPath, basePath || '')
    }
  }
}

addRecursive(DEPLOY, '')
zip.writeZip(ZIP)

const stats = fs.statSync(ZIP)
console.log(`Zip creato: progetto-cards.zip (${(stats.size / 1024).toFixed(1)} KB)`)

const verify = new AdmZip(ZIP)
const entries = verify.getEntries()
const hasBackslash = entries.some(e => e.entryName.includes('\\'))
console.log(`Entries: ${entries.length}, Forward slash paths: ${!hasBackslash}`)
