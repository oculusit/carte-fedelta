const sharp = require('sharp')
const fs = require('fs')
const path = require('path')

const svgPath = path.resolve(__dirname, 'public/icons/icon.svg')
const out192 = path.resolve(__dirname, 'public/icons/icon-192.png')
const out512 = path.resolve(__dirname, 'public/icons/icon-512.png')

const svgContent = fs.readFileSync(svgPath, 'utf-8')

async function generate() {
  await sharp(Buffer.from(svgContent)).resize(192, 192).png().toFile(out192)
  console.log('Generated icon-192.png')
  await sharp(Buffer.from(svgContent)).resize(512, 512).png().toFile(out512)
  console.log('Generated icon-512.png')
  const s192 = fs.statSync(out192).size
  const s512 = fs.statSync(out512).size
  console.log(`icon-192.png: ${s192} bytes, icon-512.png: ${s512} bytes`)
}

generate().catch(console.error)
