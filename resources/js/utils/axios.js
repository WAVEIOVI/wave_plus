import { $api } from '@/utils/api'

const axios = {
  get: async (url, config = {}) => {
    const query = config.params || {}
    const res = await $api(url, { method: 'GET', query, ...config })
    
    return { data: res }
  },
  post: async (url, data = {}, config = {}) => {
    const res = await $api(url, { method: 'POST', body: data, ...config })
    
    return { data: res }
  },
  put: async (url, data = {}, config = {}) => {
    const res = await $api(url, { method: 'PUT', body: data, ...config })
    
    return { data: res }
  },
  patch: async (url, data = {}, config = {}) => {
    const res = await $api(url, { method: 'PATCH', body: data, ...config })
    
    return { data: res }
  },
  delete: async (url, config = {}) => {
    const res = await $api(url, { method: 'DELETE', ...config })
    
    return { data: res }
  },
}

export default axios
