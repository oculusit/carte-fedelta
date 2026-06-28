const fs = require('fs')
const path = require('path')

const SRC = path.resolve('dist')
const DST = path.resolve('deploy')
const REMOVE_EXTRA = ['.htaccess', 'api', 'database', 'icons', 'uploads', 'sw.js', 'manifest.json']
const REMOVE_FILES = ['package.json']

function copyRecursive(src, dst) {
  fs.mkdirSync(dst, { recursive: true })
  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    const s = path.join(src, entry.name)
    const d = path.join(dst, entry.name)
    if (entry.isDirectory()) {
      copyRecursive(s, d)
    } else {
      fs.copyFileSync(s, d)
    }
  }
}

function removeDir(dir) {
  if (fs.existsSync(dir)) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const p = path.join(dir, entry.name)
      if (entry.isDirectory()) {
        removeDir(p)
      } else {
        fs.unlinkSync(p)
      }
    }
    fs.rmdirSync(dir)
  }
}

function cleanAssets() {
  const assetsDir = path.join(DST, 'assets')
  if (fs.existsSync(assetsDir)) {
    for (const entry of fs.readdirSync(assetsDir, { withFileTypes: true })) {
      const p = path.join(assetsDir, entry.name)
      if (entry.isFile()) {
        fs.unlinkSync(p)
      }
    }
  }
}

// Remove old assets and dist files (keep api, .htaccess, etc.)
const dstIndex = path.join(DST, 'index.html')
if (fs.existsSync(dstIndex)) fs.unlinkSync(dstIndex)
cleanAssets()

// Remove files not needed in production
for (const file of REMOVE_FILES) {
  const p = path.join(DST, file)
  if (fs.existsSync(p)) fs.unlinkSync(p)
}
// Remove directories not needed in production
const rmDir = path.join(DST, 'database')
if (fs.existsSync(rmDir)) removeDir(rmDir)

// Copy new build output
copyRecursive(SRC, DST)

// Copy API PHP files (not processed by Vite)
const API_SRC = path.resolve('api')
const API_DST = path.join(DST, 'api')
copyRecursive(API_SRC, API_DST)

// Copy root .htaccess, icons, uploads
for (const dir of ['icons', 'uploads']) {
  const src = path.resolve(dir)
  const dst = path.join(DST, dir)
  if (fs.existsSync(src)) copyRecursive(src, dst)
}
const htaccessSrc = path.resolve('.htaccess')
const htaccessDst = path.join(DST, '.htaccess')
if (fs.existsSync(htaccessSrc)) fs.copyFileSync(htaccessSrc, htaccessDst)

console.log('Sync dist/ → deploy/ completato')
