import { WebPlugin } from '@capacitor/core'

export class FilePickerWeb extends WebPlugin {
  async saveFile(options) {
    const { filename, data } = options
    const blob = new Blob([data], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
    return { uri: url, filename }
  }
}
