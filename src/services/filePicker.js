import { registerPlugin } from '@capacitor/core'

const FilePickerNative = registerPlugin('FilePicker', {
  web: () => import('./filePickerWeb.js').then(m => new m.FilePickerWeb()),
})

export async function saveFileWithDialog({ filename, data, mimeType = 'application/json' }) {
  return FilePickerNative.saveFile({ filename, data, mimeType })
}
