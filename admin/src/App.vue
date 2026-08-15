<template>
  <div class="gplc-wrap" v-loading="loading">
    <h1 class="gplc-title">{{ t('GPLlib 自动更新 · Connector') }}</h1>

    <el-tabs v-model="tab" class="gplc-tabs">
      
      <el-tab-pane :label="t('概览')" name="overview">
        <div class="gplc-cols">
          <div class="gplc-col-main">
            
            <el-card v-if="!state.bound" class="gplc-card" shadow="never">
              <template #header><strong>{{ t('激活授权') }}</strong></template>
              <el-alert
                type="info" :closable="false" show-icon
                :title="t('在 GPLlib 用户中心「自动更新」页复制授权码，填入下方完成本站绑定。')"
                style="margin-bottom:16px"
              />
              <el-form label-width="96px" @submit.prevent>
                <el-form-item :label="t('本站域名')">
                  <el-input :model-value="state.site_host" disabled />
                </el-form-item>
                <el-form-item :label="t('授权码')">
                  <el-input v-model="licenseInput" :placeholder="t('粘贴 GPLlib 用户中心的授权码')" clearable />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="busy" @click="doActivate">{{ t('激活并绑定') }}</el-button>
                </el-form-item>
              </el-form>
            </el-card>

            
            <el-card v-else class="gplc-card" shadow="never">
              <template #header>
                <strong>{{ t('绑定状态') }}</strong>
                <el-tag :type="state.status === 'active' ? 'success' : 'danger'" style="margin-left:8px">
                  {{ state.status === 'active' ? t('已绑定') : t('需重新激活') }}
                </el-tag>
              </template>
              
              <el-descriptions direction="vertical" :column="2" border>
                <el-descriptions-item :label="t('绑定域名')">{{ state.domain }}</el-descriptions-item>
                <el-descriptions-item :label="t('授权码')">{{ state.license_masked }}</el-descriptions-item>
                <el-descriptions-item :label="t('会员资格')">
                  <span v-if="ent && ent.has_subscription">{{ membershipText }}</span>
                  <span v-else>{{ t('单购用户') }}</span>
                  <span v-if="ent"> · {{ purchasedText }}</span>
                </el-descriptions-item>
                <el-descriptions-item :label="t('授权站点')">
                  {{ state.sites_used ?? '-' }} / {{ state.site_limit ?? '-' }}
                </el-descriptions-item>
                <el-descriptions-item :label="t('令牌有效期')">
                  <el-tag v-if="!state.expires_at" type="info" size="small">{{ t('永久') }}</el-tag>
                  <el-tag v-else-if="expired" type="danger" size="small">{{ t('已过期，请重新激活') }}</el-tag>
                  <span v-else>{{ expiresText }}</span>
                </el-descriptions-item>
                <el-descriptions-item :label="t('上次检查')">{{ lastCheckText }}</el-descriptions-item>
              </el-descriptions>

              <div class="gplc-actions">
                <el-button type="primary" :loading="busy" @click="doCheck">{{ t('立即检查更新') }}</el-button>
                <el-popconfirm :title="t('确定解绑本站？解绑后将停止自动更新。')" @confirm="doDeactivate">
                  <template #reference><el-button :loading="busy">{{ t('解绑本站') }}</el-button></template>
                </el-popconfirm>
              </div>

              
              <el-alert
                v-if="state.status !== 'active'" type="warning" :closable="false" show-icon
                style="margin-top:12px"
                :title="t('令牌已失效或被吊销，请在 GPLlib 用户中心确认权限后重新激活。')"
              />
              <el-alert
                v-else-if="lastError" type="warning" :closable="false" show-icon
                style="margin-top:12px"
                :title="lastError"
              />
            </el-card>
          </div>

          
          <div class="gplc-col-side">
            <el-card class="gplc-card gplc-guide" shadow="never">
              <template #header>
                <strong>{{ t('使用引导') }}</strong>
                <el-link type="primary" :href="userCenterUrl" target="_blank" :underline="false" class="gplc-guide-link">
                  {{ t('打开 GPLlib 用户中心 →') }}
                </el-link>
              </template>
              
              <el-steps :active="bindStep" direction="vertical" finish-status="success" class="gplc-steps">
                <el-step
                  :title="t('获取授权码')"
                  :description="t('登录 GPLlib，在「用户中心 → 自动更新」复制专属授权码')"
                />
                <el-step
                  :title="t('激活本站')"
                  :description="t('把授权码填入左侧，点击「激活并绑定」')"
                />
                <el-step
                  :title="t('自动更新')"
                  :description="t('有权限且支持自动更新的主题/插件将自动检查并可一键升级')"
                />
              </el-steps>
              <el-alert
                type="info" :closable="false" show-icon class="gplc-guide-tip"
                :title="t('提示：仅「会员有效期内」或「已单独购买」且 GPLlib 标注支持自动更新的资源才会出现更新。')"
              />
            </el-card>
          </div>
        </div>
      </el-tab-pane>

      
      <el-tab-pane :label="t('更新概览')" name="updates">
        <el-card class="gplc-card" shadow="never">
          <template #header>
            <strong>{{ t('更新概览') }}</strong>
            <el-button
              v-if="state.bound" type="primary" size="small" :loading="busy"
              class="gplc-header-btn" @click="doCheck"
            >
              {{ t('立即检查更新') }}
            </el-button>
            <div class="gplc-subtitle">{{ t('实际更新在「仪表盘 → 更新」或插件/主题列表中执行。') }}</div>
          </template>

          <el-empty v-if="!state.bound" :image-size="90" :description="t('尚未激活，激活后可在此查看受管资源与待更新列表。')" />
          <el-empty v-else-if="!summary" :image-size="90" :description="t('尚未检查更新，点击「立即检查更新」获取最新结果。')" />
          <template v-else>
            <div class="gplc-stats">
              <div class="gplc-stat">
                <div class="gplc-stat-num">{{ summary.managed ?? 0 }}</div>
                <div class="gplc-stat-label">{{ t('受管资源') }}</div>
              </div>
              <div class="gplc-stat">
                <div class="gplc-stat-num" :class="{ 'is-hl': pending.length }">{{ summary.updatable ?? 0 }}</div>
                <div class="gplc-stat-label">{{ t('待更新') }}</div>
              </div>
              <div class="gplc-stat">
                <div class="gplc-stat-num is-time">{{ lastCheckText }}</div>
                <div class="gplc-stat-label">{{ t('上次检查') }}</div>
              </div>
            </div>

            <el-alert
              v-if="!pending.length" type="success" :closable="false" show-icon
              class="gplc-uptodate" :title="t('所有受管资源均为最新版本。')"
            />

            
            <div v-else class="gplc-pending">
              <div class="gplc-pending-title">{{ t('待更新资源') }}</div>
              <div v-for="item in pagedPending" :key="item.slug" class="gplc-pending-item">
                <div class="gplc-pending-head">
                  <strong>{{ item.name || item.slug }}</strong>
                  <el-tag type="success" size="small">
                    {{ t('新版本 %s').replace('%s', item.version || '-') }}
                  </el-tag>
                  <span class="gplc-slug">{{ item.slug }}</span>
                </div>
                <template v-if="item.changelog">
                  <p class="gplc-changelog" :class="{ 'is-clamped': !expanded[item.slug] }">{{ item.changelog }}</p>
                  <el-link
                    v-if="isLongChangelog(item)" type="primary" :underline="false" class="gplc-toggle"
                    @click="toggleChangelog(item.slug)"
                  >
                    {{ expanded[item.slug] ? t('收起') : t('展开') }}
                  </el-link>
                </template>
              </div>

              
              <el-pagination
                v-if="pending.length > pageSize"
                v-model:current-page="page"
                class="gplc-pager"
                small background hide-on-single-page
                layout="total, prev, pager, next"
                :page-size="pageSize"
                :total="pending.length"
              />
            </div>
          </template>
        </el-card>
      </el-tab-pane>

      
      <el-tab-pane :label="t('设置')" name="settings">
        <el-card class="gplc-card" shadow="never">
          <template #header><strong>{{ t('接口设置') }}</strong></template>
          <el-form label-width="120px" @submit.prevent>
            <el-form-item :label="t('API 地址')">
              <el-input v-model="apiBaseInput" class="gplc-api-input" placeholder="https://gpllib.com/wp-json/gpl/v1" />
              <div class="gplc-muted gplc-help">{{ t('一般无需修改；仅当 GPLlib 官方告知新地址时才需要调整。') }}</div>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="saveApi">{{ t('保存地址') }}</el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { api, t, locale } from './api/http'

const MSG_OFFSET = 56
const msg = {
  success: (message) => ElMessage({ type: 'success', message, offset: MSG_OFFSET }),
  error: (message) => ElMessage({ type: 'error', message, offset: MSG_OFFSET }),
  warning: (message) => ElMessage({ type: 'warning', message, offset: MSG_OFFSET }),
}

const loading = ref(true)
const busy = ref(false)
const tab = ref('overview')
const state = reactive({ bound: false, status: 'unbound', site_host: api.cfg.site_host || '' })
const licenseInput = ref('')
const apiBaseInput = ref('')
const summary = ref(null)

const lastError = ref('')

const ent = computed(() => state.entitlement || null)
const bindStep = computed(() => (state.bound ? 3 : 1))
const siteBase = computed(() => String(state.api_base || api.cfg.api_base || '').replace(/\/wp-json\/.*$/, ''))
const userCenterUrl = computed(() => (siteBase.value ? siteBase.value + '/dashboard/#connector' : '#'))

const lastCheckText = computed(() => {
  if (!state.last_check) return t('从未')
  return new Date(state.last_check * 1000).toLocaleString(locale())
})
const expiresDate = computed(() => {
  if (!state.expires_at) return null
  const t2 = new Date(String(state.expires_at).replace(' ', 'T'))
  return isNaN(t2) ? null : t2
})
const expired = computed(() => !!expiresDate.value && expiresDate.value.getTime() < Date.now())
const expiresText = computed(() =>
  expiresDate.value ? expiresDate.value.toLocaleString(locale()) : (state.expires_at || '-')
)

const membershipText = computed(() => {
  if (!ent.value) return ''
  if (ent.value.is_lifetime) return t('会员（永久）')
  return t('会员，到期：%s').replace('%s', ent.value.subscription_expired_at || '-')
})
const purchasedText = computed(() =>
  t('已购资源 %s').replace('%s', String(ent.value ? (ent.value.purchased_count ?? 0) : 0))
)

const pending = computed(() => (summary.value && Array.isArray(summary.value.pending) ? summary.value.pending : []))

const pageSize = 10
const page = ref(1)
const pagedPending = computed(() => pending.value.slice((page.value - 1) * pageSize, page.value * pageSize))
watch(pending, () => { page.value = 1 })

const expanded = reactive({})
const isLongChangelog = (item) => String(item.changelog || '').length > 80
function toggleChangelog(slug) {
  expanded[slug] = !expanded[slug]
}

function applyState(s) {
  if (s) Object.assign(state, s)
  apiBaseInput.value = state.api_base || ''
  
  if (s && Object.prototype.hasOwnProperty.call(s, 'summary')) summary.value = s.summary || null
}

async function load() {
  loading.value = true
  const res = await api.state()
  applyState(res)
  loading.value = false
}

async function doActivate() {
  if (!licenseInput.value.trim()) return msg.warning(t('请输入授权码'))
  busy.value = true
  const res = await api.activate(licenseInput.value.trim())
  busy.value = false
  if (res.ok) { msg.success(t('激活成功')); lastError.value = ''; applyState(res.state) }
  else msg.error(res.message || t('激活失败'))
}

async function doDeactivate() {
  busy.value = true
  const res = await api.deactivate()
  busy.value = false
  msg.success(t('已解绑')); applyState(res.state); summary.value = null; lastError.value = ''
}

async function doCheck() {
  busy.value = true
  const res = await api.checkNow()
  busy.value = false
  if (res.ok) {
    lastError.value = ''
    applyState(res.state)
    summary.value = res.summary 
    msg.success(t('检查完成'))
    tab.value = 'updates' 
  } else {
    
    lastError.value = res.message || t('检查失败')
    applyState(res.state)
    msg.error(lastError.value)
  }
}

async function saveApi() {
  const res = await api.saveSettings(apiBaseInput.value.trim())
  if (res.ok) { applyState(res.state); msg.success(t('已保存')) }
  else { if (res.state) applyState(res.state); msg.error(res.message || t('保存失败')) }
}

onMounted(load)
</script>

<style scoped>
.gplc-wrap { max-width: 1120px; }
.gplc-title { font-size: 20px; margin: 12px 0 8px; }
.gplc-tabs { margin-top: 4px; }
.gplc-card { margin-bottom: 16px; }
.gplc-actions { margin-top: 16px; display: flex; gap: 10px; }
.gplc-muted { color: #888; font-size: 12px; }
.gplc-help { line-height: 1.6; margin-top: 4px; }
.gplc-api-input { max-width: 480px; }
.gplc-header-btn { float: right; }
.gplc-subtitle { clear: both; color: #888; font-size: 12px; margin-top: 6px; }

.gplc-cols { display: flex; flex-wrap: wrap; gap: 16px; align-items: stretch; min-height: 480px; }
.gplc-col-main { flex: 1 1 460px; min-width: 0; display: flex; flex-direction: column; }
.gplc-col-side { flex: 0 1 340px; min-width: 0; display: flex; flex-direction: column; }

.gplc-steps { flex: 1; min-height: 260px; }

.gplc-guide-tip { margin-top: auto; padding-top: 16px; }

.gplc-guide-link { float: right; font-weight: normal; }

.gplc-stats { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 4px; }
.gplc-stat {
  flex: 1 1 160px;
  padding: 14px 16px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  background: #fafafa;
}
.gplc-stat-num { font-size: 24px; font-weight: 600; line-height: 1.3; color: #303133; }
.gplc-stat-num.is-hl { color: #e6a23c; }
.gplc-stat-num.is-time { font-size: 15px; font-weight: 500; }
.gplc-stat-label { margin-top: 4px; color: #909399; font-size: 12px; }
.gplc-uptodate { margin-top: 16px; }

.gplc-pending { margin: 20px 0 4px; }
.gplc-pending-title { font-weight: 600; margin-bottom: 8px; }
.gplc-pending-item { border-top: 1px solid #ebeef5; padding: 14px 0; }
.gplc-pending-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.gplc-slug { color: #c0c4cc; font-size: 12px; }
.gplc-toggle { font-size: 12px; margin-top: 4px; }
.gplc-pager { margin-top: 16px; display: flex; justify-content: flex-end; }

.gplc-changelog {
  white-space: pre-wrap;
  word-break: break-word;
  margin: 6px 0 0;
  color: #606266;
  font-size: 13px;
  line-height: 1.7;
}

.gplc-changelog.is-clamped {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>

<style>
#gpllib-connector-app .el-input__inner,
#gpllib-connector-app .el-select__input,
#gpllib-connector-app .el-textarea__inner {
  border: none;
  border-radius: 0;
  background: none;
  box-shadow: none;
  outline: none;
  margin: 0;
  min-height: 0;
  max-width: none;
  color: inherit;
  font-family: inherit;
  font-size: inherit;
}

#gpllib-connector-app .el-input__inner,
#gpllib-connector-app .el-select__input {
  padding: 0;
  height: inherit;
  line-height: inherit;
}

#gpllib-connector-app .el-textarea__inner {
  
  box-shadow: 0 0 0 1px var(--el-input-border-color, var(--el-border-color)) inset;
  line-height: 1.5;
}

#gpllib-connector-app .el-input__inner:focus,
#gpllib-connector-app .el-select__input:focus,
#gpllib-connector-app .el-textarea__inner:focus {
  border-color: transparent;
  box-shadow: none;
  outline: none;
}
#gpllib-connector-app .el-textarea__inner:focus {
  box-shadow: 0 0 0 1px var(--el-input-focus-border-color, var(--el-color-primary)) inset;
}

#gpllib-connector-app .el-checkbox__original,
#gpllib-connector-app .el-radio__original {
  border: none;
  background: none;
  box-shadow: none;
  min-width: 0;
  min-height: 0;
  margin: 0;
  padding: 0;
}
#gpllib-connector-app .el-checkbox__original::before,
#gpllib-connector-app .el-radio__original::before {
  content: none;
}

#gpllib-connector-app .el-button {
  font-family: inherit;
  line-height: 1;
  text-decoration: none;
  box-shadow: none;
  text-shadow: none;
}

#gpllib-connector-app .gplc-cols > div > .el-card {
  height: 100%;
  margin-bottom: 0;
  display: flex;
  flex-direction: column;
}
#gpllib-connector-app .gplc-cols > div > .el-card > .el-card__body {
  flex: 1;
  display: flex;
  flex-direction: column;
}

#gpllib-connector-app p {
  font-size: inherit;
  line-height: 1.6;
}
</style>
