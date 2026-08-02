const AdmZip = require('adm-zip')
const path = require('path')
const fs = require('fs')

const ROOT = path.resolve('.')
const DEPLOY = path.resolve(ROOT, 'deploy')
const ZIP = path.resolve(ROOT, 'progetto-cards.zip')

const zip = new AdmZip()

const EXCLUDE_ROOT = new Set(['index.html', '.htaccess'])

function addRecursive(dir, basePath, isRoot) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (isRoot && EXCLUDE_ROOT.has(entry.name)) continue
    const fullPath = path.join(dir, entry.name)
    const entryPath = basePath ? `${basePath}/${entry.name}` : entry.name
    if (entry.isDirectory()) {
      addRecursive(fullPath, entryPath, false)
    } else {
      zip.addLocalFile(fullPath, basePath || '')
    }
  }
}

addRecursive(DEPLOY, '', true)
zip.writeZip(ZIP)

const stats = fs.statSync(ZIP)
console.log(`Zip creato: progetto-cards.zip (${(stats.size / 1024).toFixed(1)} KB)`)

const verify = new AdmZip(ZIP)
const entries = verify.getEntries()
const hasBackslash = entries.some(e => e.entryName.includes('\\'))
console.log(`Entries: ${entries.length}, Forward slash paths: ${!hasBackslash}`)
