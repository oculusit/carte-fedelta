import { Capacitor } from '@capacitor/core'

let Nfc = null

async function getNfc() {
  if (Nfc) return Nfc
  const { CapacitorNfc } = await import('@capgo/capacitor-nfc')
  Nfc = wrapPlugin(CapacitorNfc)
  return Nfc
}

function wrapPlugin(CapacitorNfc) {
  return {
    isSupported: () => CapacitorNfc.isSupported(),
    getStatus: () => CapacitorNfc.getStatus(),
    showSettings: () => CapacitorNfc.showSettings(),
    addListener: (eventName, callback) => CapacitorNfc.addListener(eventName, callback),
    write: (options) => CapacitorNfc.write(options),
    startScanning: (options) => CapacitorNfc.startScanning(options),
    stopScanning: () => CapacitorNfc.stopScanning(),
  }
}

function utf8Bytes(str) {
  return Array.from(new TextEncoder().encode(str))
}

function textByteArray(str) {
  return Array.from(str, (c) => c.charCodeAt(0) & 0xff)
}

export function isNfcNativeAvailable() {
  return Capacitor.isNativePlatform()
}

export async function isNfcSupported() {
  if (!isNfcNativeAvailable()) return false
  try {
    const nfc = await getNfc()
    const { supported } = await nfc.isSupported()
    return !!supported
  } catch {
    return false
  }
}

export async function getNfcStatus() {
  try {
    const nfc = await getNfc()
    const { status } = await nfc.getStatus()
    return status
  } catch {
    return null
  }
}

export async function openNfcSettings() {
  if (!isNfcNativeAvailable()) return
  const nfc = await getNfc()
  await nfc.showSettings()
}

function vcardMimeRecord(vcardText) {
  return {
    tnf: 2,
    type: textByteArray('text/x-vcard'),
    id: [],
    payload: utf8Bytes(vcardText),
  }
}

export async function writeVCardToTag(vcardText, onProgress, onReady) {
  const nfc = await getNfc()
  const { supported } = await nfc.isSupported()
  if (!supported) {
    throw new Error('NFC non supportato su questo dispositivo')
  }

  return new Promise((resolve, reject) => {
    let sessionOpen = true
    let handled = false

    const cleanup = async () => {
      if (!sessionOpen) return
      sessionOpen = false
      try {
        await nfc.stopScanning()
      } catch {}
      try {
        listener?.remove()
      } catch {}
    }

    const fail = async (msg) => {
      if (handled) return
      handled = true
      await cleanup()
      reject(new Error(msg))
    }

    const ok = async () => {
      if (handled) return
      handled = true
      await cleanup()
      resolve()
    }

    const cancel = async () => {
      if (handled) return
      handled = true
      await cleanup()
      const err = new Error('Scrittura NFC annullata')
      err.code = 'NFC_CANCELLED'
      reject(err)
    }

    if (onReady) onReady(cancel)

    let listener
    const start = async () => {
      onProgress?.('appoggia')
      listener = await nfc.addListener('nfcEvent', async (event) => {
        if (handled || !sessionOpen) return
        onProgress?.('trovato')
        try {
          await nfc.write({ records: [vcardMimeRecord(vcardText)] })
          onProgress?.('scritto')
          await ok()
        } catch (e) {
          fail(e?.message || 'Scrittura sul tag non riuscita')
        }
      })

      await nfc.startScanning({
        alertMessage: 'Appoggia il tag NFC da scrivere',
        invalidateAfterFirstRead: false,
      })
    }

    start().catch((e) => {
      const msg = e?.message || ''
      const hasNfc = /NFC/i.test(msg)
      const err = new Error(
        hasNfc ? 'NFC non disponibile o disattivato' : 'Impossibile avviare la sessione NFC'
      )
      err.code = hasNfc ? 'NFC_UNAVAILABLE' : 'NFC_START_FAILED'
      if (!handled) {
        handled = true
        reject(err)
      }
    })
  })
}

let NdefShare = null

async function getNdefShare() {
  if (NdefShare) return NdefShare
  const { registerPlugin } = await import('@capacitor/core')
  const raw = registerPlugin('NdefEmulation', {
    web: () => ({
      start: async () => {
        throw new Error('NFC non disponibile')
      },
      stop: async () => {},
      isActive: async () => ({ active: false }),
    }),
  })
  NdefShare = {
    start: (vcard) => raw.start({ vcard }),
    stop: () => raw.stop(),
    isActive: () => raw.isActive(),
  }
  return NdefShare
}

export async function startNfcShare(vcardText) {
  if (!isNfcNativeAvailable()) {
    throw new Error('NFC non disponibile su questo dispositivo')
  }
  const share = await getNdefShare()
  return share.start(vcardText)
}

export async function stopNfcShare() {
  if (!isNfcNativeAvailable()) return
  const share = await getNdefShare()
  await share.stop()
}

export async function isNfcShareActive() {
  if (!isNfcNativeAvailable()) return false
  const share = await getNdefShare()
  const { active } = await share.isActive()
  return !!active
}
