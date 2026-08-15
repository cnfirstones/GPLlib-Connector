

const CFG = window.GPLLIB_CONNECTOR_CFG || {}



export function t(key) {
  const dict = CFG.i18n
  return dict && Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : key
}


export function locale() {
  return CFG.locale || undefined
}

function base() {
  return (CFG.rest_url || '/wp-json/gpllib-connector/v1/').replace(/\/+$/, '/')
}

async function request(method, path, body) {
  const res = await fetch(base() + String(path).replace(/^\/+/, ''), {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-WP-Nonce': CFG.nonce || '',
    },
    body: body ? JSON.stringify(body) : undefined,
  })
  try {
    return await res.json()
  } catch (e) {
    return { ok: false, message: t('响应异常') }
  }
}

export const api = {
  cfg: CFG,
  state: () => request('GET', 'state'),
  activate: (license_key) => request('POST', 'activate', { license_key }),
  deactivate: () => request('POST', 'deactivate'),
  checkNow: () => request('POST', 'check-now'),
  saveSettings: (api_base) => request('POST', 'settings', { api_base }),
}
