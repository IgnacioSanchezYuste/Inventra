import { http } from './http'

export interface UploadResp { success: boolean; url: string; filename: string }

export const uploadsApi = {
  productImage: (file: File, onProgress?: (pct: number) => void) => {
    const fd = new FormData()
    fd.append('image', file)
    return http.post<UploadResp>('/uploads/product-image', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => {
        if (onProgress && e.total) onProgress(Math.round((e.loaded / e.total) * 100))
      }
    }).then(r => r.data)
  }
}
