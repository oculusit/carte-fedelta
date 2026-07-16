import { registerPlugin } from '@capacitor/core'

const FilePickerNative = registerPlugin('FilePicker', {
  web: () => Promise.resolve({
    saveToDownloads: async ({ filename }) => {
      return { path: filename, filename, size: 0 }
    },
    pickFile: async () => {
      throw new Error('pickFile not supported on web, use native file input')
    },
    openDownloadsFolder: async () => {},
  }),
})

export async function saveToDownloads({ filename, data }) {
  return FilePickerNative.saveToDownloads({ filename, data })
}

export async function pickJsonFile() {
  return FilePickerNative.pickFile({ acceptType: 'application/json' })
}

export async function openDownloadsFolder() {
  return FilePickerNative.openDownloadsFolder()
}
