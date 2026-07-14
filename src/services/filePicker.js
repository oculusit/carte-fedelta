import { registerPlugin } from '@capacitor/core'

const FilePickerNative = registerPlugin('FilePicker', {
  web: () => Promise.resolve({
    saveToDownloads: async ({ filename }) => {
      return { path: filename, filename, size: 0 }
    }
  }),
})

export async function saveToDownloads({ filename, data }) {
  return FilePickerNative.saveToDownloads({ filename, data })
}
