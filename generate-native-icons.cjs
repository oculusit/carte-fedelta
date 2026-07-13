const sharp = require('sharp')
const fs = require('fs')
const path = require('path')

const svgPath = path.resolve(__dirname, 'public/icons/icon.svg')
const svgContent = fs.readFileSync(svgPath, 'utf-8')

// Android mipmap sizes (density → px)
const ANDROID_SIZES = {
  'mdpi': 48,
  'hdpi': 72,
  'xhdpi': 96,
  'xxhdpi': 144,
  'xxxhdpi': 192,
}

// Adaptive icon foreground at 108x108dp (72dp safe zone)
// The SVG has its own blue background, so it's fine as foreground
const ADAPTIVE_SIZE = 108

const IOS_SIZE = 1024

async function generate() {
  const buf = Buffer.from(svgContent)

  // --- Android mipmap icons ---
  for (const [density, px] of Object.entries(ANDROID_SIZES)) {
    const outDir = path.resolve(__dirname, `android/app/src/main/res/mipmap-${density}`)

    await sharp(buf).resize(px, px).png().toFile(path.join(outDir, 'ic_launcher.png'))
    await sharp(buf).resize(px, px).png().toFile(path.join(outDir, 'ic_launcher_round.png'))

    // Foreground: same SVG, no background color override needed since SVG has its own
    await sharp(buf).resize(px, px).png().toFile(path.join(outDir, 'ic_launcher_foreground.png'))

    console.log(`Generated mipmap-${density} (${px}x${px})`)
  }

  // --- Adaptive icon foreground (anydpi-v26) ---
  // The foreground used by adaptive-icon XMLs references mipmap resource,
  // which will resolve to the correct density. So we already generated it above.
  // Just need to make sure the foreground has the correct 108dp output on a 108x108 canvas.
  const foreground108 = path.resolve(__dirname, 'android/app/src/main/res/mipmap-anydpi-v26')
  if (!fs.existsSync(foreground108)) fs.mkdirSync(foreground108, { recursive: true })
  // The XML files are already there, pointing to @mipmap/ic_launcher_foreground

  console.log('Adaptive icon config files already in place (XML + mipmap fallback)')

  // --- iOS AppIcon ---
  const iosDir = path.resolve(__dirname, 'ios/App/App/Assets.xcassets/AppIcon.appiconset')
  await sharp(buf).resize(IOS_SIZE, IOS_SIZE).png().toFile(path.join(iosDir, 'AppIcon-512@2x.png'))
  console.log(`Generated iOS AppIcon (${IOS_SIZE}x${IOS_SIZE})`)

  console.log('\nAll native icons regenerated from site favicon.')
}

generate().catch(console.error)
