import { createApp } from 'vue'
import ElementPlus from 'element-plus'
import zhCn from 'element-plus/es/locale/lang/zh-cn'
import en from 'element-plus/es/locale/lang/en'
import 'element-plus/dist/index.css'
import App from './App.vue'
import { locale } from './api/http'



const elLocale = String(locale() || 'zh-CN').toLowerCase().startsWith('zh') ? zhCn : en



const el = document.getElementById('gpllib-connector-app')
if (el) {
  createApp(App).use(ElementPlus, { locale: elLocale, zIndex: 100100 }).mount(el)
}
