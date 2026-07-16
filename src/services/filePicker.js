import { registerPlugin } from '@capacitor/core'
import { Capacitor } from '@capacitor/core'

const isNative = Capacitor.isNativePlatform()
const isIOS = Capacitor.getPlatform() === 'ios'

const FilePickerNative = registerPlugin('FilePicker', {
  web: () => Promise.resolve({
    saveToDownloads: async ({ filename }) => {
      return { path: filename, filename, size: 0 }
    },
    pickFile: async () => {
      throw new Error('pickFile not supported on web, use native file input')
    },
    openDownloadsFolder: async () => {},
    shareFile: async () => {},
  }),
  ios: () => Promise.resolve({
    saveToDownloads: async ({ filename, data }) => {
      const { Filesystem, Directory } = await import('@capacitor/filesystem')
      const result = await Filesystem.writeFile({
        path: filename,
        data: data,
        directory: Directory.Cache,
        recursive: true,
      })
      return { path: result.uri, filename, size: data.length }
    },
    pickFile: async () => {
      throw new Error('pickFile not supported on iOS, use file input')
    },
    openDownloadsFolder: async () => {},
    shareFile: async ({ filename, data, title, text }) => {
      const { Share } = await import('@capacitor/share')
      const { Filesystem, Directory } = await import('@capacitor/filesystem')
      await Filesystem.writeFile({
        path: filename,
        data: data,
        directory: Directory.Cache,
        recursive: true,
      })
      await Share.share({
        title: title || filename,
        text: text || '',
        files: [`capacitor://localhost/_capacitor/files/${filename}`],
      })
    },
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

export async function shareFile({ filename, data, title, text }) {
  return FilePickerNative.shareFile({ filename, data, title, text })
}

export { isIOS }
