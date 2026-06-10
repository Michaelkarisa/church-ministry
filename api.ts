/**
 * Church Ministry Platform — API Client
 * ======================================
 * Zero dependencies. Uses the browser's native fetch API.
 *
 * Usage:
 *   import api from './api'
 *
 *   // Login
 *   const { data } = await api.auth.login({ email, password })
 *   // Token is stored automatically.
 *
 *   // Typed requests
 *   const { data } = await api.transactions.list({ period: 30, service_type: 'sunday_morning' })
 *   const { data } = await api.analytics.ministry.overview()
 */

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const TOKEN_KEY = 'church_api_token' as const
const EXPIRES_KEY = 'church_api_expires' as const

// ─────────────────────────────────────────────────────────────────────────────
// Enums / Literals
// ─────────────────────────────────────────────────────────────────────────────

export type RoleName = 'ministry_admin' | 'zone_admin' | 'church_admin'

export type TransactionCategory =
  | 'offering'
  | 'tithe'
  | 'donation'
  | 'project'
  | 'building'
  | 'welfare'
  | 'pledge'
  | 'harvesting'
  | 'thanksgiving'
  | 'other'

export type ServiceType =
  | 'sunday_morning'
  | 'sunday_evening'
  | 'wednesday'
  | 'friday'
  | 'saturday'
  | 'special'
  | 'crusade'
  | 'prayer_meeting'
  | 'conference'
  | 'other'

export type Gender = 'male' | 'female' | 'other'
export type MaritalStatus = 'single' | 'married' | 'widowed' | 'divorced'
export type GroupBy = 'day' | 'week' | 'month'
export type AccessLevel = 'ministry' | 'zone' | 'church'

// ─────────────────────────────────────────────────────────────────────────────
// Core API response shapes
// ─────────────────────────────────────────────────────────────────────────────

export interface ApiSuccess<T> {
  success: true
  message: string
  data: T
}

export interface ApiPaginated<T> {
  success: true
  message: string
  data: T[]
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    from: number | null
    to: number | null
  }
}

export interface ApiError {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export type ApiResponse<T> = ApiSuccess<T>
export type ApiListResponse<T> = ApiPaginated<T>

/** Thrown when the server returns a non-2xx response. */
export class ApiException extends Error {
  constructor(
    public readonly status: number,
    public readonly payload: ApiError,
  ) {
    super(payload.message)
    this.name = 'ApiException'
  }

  /** Convenience: returns field-level validation errors, if any. */
  get validationErrors(): Record<string, string[]> | undefined {
    return this.payload.errors
  }

  get isUnauthorized(): boolean { return this.status === 401 }
  get isForbidden():    boolean { return this.status === 403 }
  get isNotFound():     boolean { return this.status === 404 }
  get isValidation():   boolean { return this.status === 422 }
  get isRateLimit():    boolean { return this.status === 429 }
}

// ─────────────────────────────────────────────────────────────────────────────
// Domain models
// ─────────────────────────────────────────────────────────────────────────────

export interface Role {
  id: number  // integer PK — roles table unchanged
  name: RoleName
  display_name: string
  description: string | null
  level: 1 | 2 | 3
}

export interface Permission {
  id: number  // integer PK — permissions table unchanged
  name: string
  display_name: string
  group: string
}

export interface Ministry {
  id: string
  name: string
  code: string
  address: string | null
  city: string | null
  county: string | null
  country: string
  phone: string | null
  email: string | null
  logo: string | null
  description: string | null
  currency_code: string
  founded_year: number | null
  website: string | null
  is_active: boolean
  zones_count?: number
  churches_count?: number
  created_at: string
  updated_at: string
}

export interface Zone {
  id: string
  ministry_id: string
  name: string
  code: string
  region: string | null
  address: string | null
  phone: string | null
  email: string | null
  is_active: boolean
  churches_count?: number
  created_at: string
  updated_at: string
  deleted_at: string | null
}

export interface Church {
  id: string
  zone_id: string
  zone?: Zone
  name: string
  code: string
  address: string | null
  location: string | null
  phone: string | null
  email: string | null
  pastor_name: string | null
  establishment_date: string | null
  latitude: number | null
  longitude: number | null
  is_active: boolean
  members_count?: number
  created_at: string
  updated_at: string
  deleted_at: string | null
}

export type AccessScope =
  | { level: 'ministry' }
  | { level: 'zone'; zone_id: string }
  | { level: 'church'; church_id: string };

export interface User {
  id: string
  name: string
  email: string
  phone: string | null
  avatar: string | null
  is_active: boolean
  last_login_at: string | null
  role: Role
  access_scope: AccessScope
  zone?: Pick<Zone, 'id' | 'name' | 'code'> | null
  church?: Pick<Church, 'id' | 'name' | 'code'> | null
  created_at: string
  updated_at: string
}

/** Returned only from /auth/me */
export interface AuthenticatedUser extends User {
  ministry: Pick<Ministry, 'id' | 'name' | 'currency_code'>
}

export interface Member {
  id: string
  church_id: string
  church?: Pick<Church, 'id' | 'name' | 'code'>
  member_number: string
  first_name: string
  last_name: string
  full_name?: string
  email: string | null
  phone: string | null
  date_of_birth: string | null
  gender: Gender | null
  marital_status: MaritalStatus | null
  address: string | null
  occupation: string | null
  membership_date: string | null
  is_active: boolean
  transactions_count?: number
  created_at: string
  updated_at: string
}

export interface TransactionType {
  id: string
  name: string
  code: string
  description: string | null
  category: TransactionCategory
  display_order: number
  is_active: boolean
  transactions_count?: number
  created_at: string
  updated_at: string
}

export interface Transaction {
  id: string
  church_id: string
  church?: Pick<Church, 'id' | 'name' | 'code'>
  transaction_type_id: string
  transaction_type?: TransactionType
  recorded_by: number
  recorder?: Pick<User, 'id' | 'name' | 'email'>
  member_id: string | null
  member?: Pick<Member, 'id' | 'first_name' | 'last_name' | 'member_number'> | null
  amount: string         // decimal comes as string from Laravel
  currency: string
  transaction_date: string
  service_type: ServiceType | null
  reference_number: string | null
  description: string | null
  notes: string | null
  is_verified: boolean
  verified_by: number | null
  verifier?: Pick<User, 'id' | 'name'> | null
  verified_at: string | null
  created_at: string
  updated_at: string
}

export interface ActivityLog {
  id: string
  user_id: string | null
  user?: Pick<User, 'id' | 'name' | 'email'> | null
  ministry_id: string | null
  zone_id: string | null
  church_id: string | null
  action: string
  module: string
  record_type: string | null
  record_id: string | null
  description: string | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  method: string | null
  url: string | null
  status_code: number | null
  performed_at: string
  created_at: string
}

// ─────────────────────────────────────────────────────────────────────────────
// Analytics types
// ─────────────────────────────────────────────────────────────────────────────

export interface Period {
  from: string
  to: string
}

export interface TrendPoint {
  period_label: string
  total: string
  count: number
}

export interface CategoryBreakdown {
  category: TransactionCategory
  total: string
  count: number
}

export interface TypeBreakdown {
  id: string
  name: string
  category: TransactionCategory
  total: string
  count: number
}

export interface ServiceBreakdown {
  service_type: ServiceType | null
  total: string
  count: number
}

export interface ChurchSummary {
  period: Period
  church_id: string
  total_amount: number
  total_count: number
  verified: number
  pending: number
  by_category: CategoryBreakdown[]
  by_type: TypeBreakdown[]
  by_service: ServiceBreakdown[]
  currency: string
}

export interface ChurchTrend {
  period: Period
  group_by: GroupBy
  trend: TrendPoint[]
}

export interface TopMember {
  id: string
  full_name: string
  member_number: string
  total: string
  transaction_count: number
}

export interface ChurchTopMembers {
  period: Period
  top_members: TopMember[]
}

export interface ZoneSummary {
  period: Period
  zone_id: string
  total_amount: number
  total_count: number
  verified: number
  churches_count: number
  by_category: CategoryBreakdown[]
  by_type: TypeBreakdown[]
  currency: string
}

export interface ChurchComparison {
  church_id: string
  church_name: string
  church_code: string
  total_amount: number
  count: number
  members: number
}

export interface ZoneChurchesComparison {
  period: Period
  zone_id: string
  churches: ChurchComparison[]
}

export interface ZoneTrend {
  period: Period
  group_by: GroupBy
  trend: TrendPoint[]
}

export interface MinistrySummary {
  period: Period
  total_amount: number
  total_count: number
  verified: number
  pending: number
  by_category: CategoryBreakdown[]
  by_type: TypeBreakdown[]
  currency: string
}

export interface ZoneComparison {
  zone_id: string
  zone_name: string
  zone_code: string
  churches: number
  total_amount: number
  count: number
}

export interface ZonesComparison {
  period: Period
  zones: ZoneComparison[]
}

export interface TopChurch {
  id: string
  church_name: string
  church_code: string
  zone_name: string
  total: string
  count: number
}

export interface MinistryTopChurches {
  period: Period
  top_churches: TopChurch[]
}

export interface MinistryTrend {
  period: Period
  group_by: GroupBy
  trend: TrendPoint[]
}

export interface MinistryOverview {
  ministry: Pick<Ministry, 'id' | 'name' | 'code' | 'currency_code'>
  totals: {
    zones: number
    churches: number
    members: number
    users: number
  }
  this_month: MinistrySummary
  last_month: MinistrySummary
  this_year: MinistrySummary
}

export interface ActivityStats {
  period: Period
  total: number
  by_module: { module: string; count: number }[]
  by_action: { action: string; count: number }[]
  by_user: { id: string; name: string; count: number }[]
  trend: { day: string; count: number }[]
}

export interface UsageStats {
  period: Period
  total_requests: number
  failed_requests: number
  hourly_distribution: { hour: number; count: number }[]
  status_codes: { status_code: number; count: number }[]
  active_users: number
}

// ─────────────────────────────────────────────────────────────────────────────
// Request bodies
// ─────────────────────────────────────────────────────────────────────────────

export interface LoginBody {
  email: string
  password: string
}

export interface ForgotPasswordBody {
  email: string
}

export interface ResetPasswordBody {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export interface VerifyResetTokenParams {
  token: string
  email: string
}

export interface ChangePasswordBody {
  current_password: string
  password: string
  password_confirmation: string
}

export interface UpdateProfileBody {
  name?: string
  phone?: string | null
}

export interface StoreUserBody {
  name: string
  email: string
  password: string
  role_id: number
  zone_id?: number | null
  church_id?: number | null
  phone?: string | null
  is_active?: boolean
}

export interface UpdateUserBody extends Partial<Omit<StoreUserBody, 'password'>> {
  password?: string
}

export interface StoreZoneBody {
  name: string
  code: string
  region?: string | null
  address?: string | null
  phone?: string | null
  email?: string | null
  is_active?: boolean
}

export type UpdateZoneBody = Partial<StoreZoneBody>

export interface StoreChurchBody {
  zone_id: string
  name: string
  code: string
  address?: string | null
  location?: string | null
  phone?: string | null
  email?: string | null
  pastor_name?: string | null
  establishment_date?: string | null
  latitude?: number | null
  longitude?: number | null
  is_active?: boolean
}

export type UpdateChurchBody = Partial<Omit<StoreChurchBody, 'zone_id'>>

export interface StoreMemberBody {
  church_id: string
  member_number: string
  first_name: string
  last_name: string
  email?: string | null
  phone?: string | null
  date_of_birth?: string | null
  gender?: Gender | null
  marital_status?: MaritalStatus | null
  address?: string | null
  occupation?: string | null
  membership_date?: string | null
  is_active?: boolean
}

export type UpdateMemberBody = Partial<Omit<StoreMemberBody, 'church_id'>>

export interface StoreTransactionTypeBody {
  name: string
  code: string
  description?: string | null
  category: TransactionCategory
  display_order?: number
  is_active?: boolean
}

export type UpdateTransactionTypeBody = Partial<StoreTransactionTypeBody>

export interface StoreTransactionBody {
  church_id: string
  transaction_type_id: string
  amount: number
  currency?: string
  transaction_date: string
  service_type?: ServiceType | null
  member_id?: number | null
  reference_number?: string | null
  description?: string | null
  notes?: string | null
}

export type UpdateTransactionBody = Partial<Omit<StoreTransactionBody, 'church_id'>>

export interface UpdateMinistryBody {
  name?: string
  address?: string | null
  city?: string | null
  county?: string | null
  country?: string | null
  phone?: string | null
  email?: string | null
  description?: string | null
  website?: string | null
  currency_code?: string
  founded_year?: number | null
}

// ─────────────────────────────────────────────────────────────────────────────
// Query / filter param types
// ─────────────────────────────────────────────────────────────────────────────

export interface PaginationParams {
  page?: number
  per_page?: number
}

export interface AnalyticsPeriodParams {
  period?: number
  from?: string
  to?: string
  group_by?: GroupBy
}

export interface UserListParams extends PaginationParams {
  search?: string
  role_id?: number
  zone_id?: number
  church_id?: number
  is_active?: boolean
}

export interface ZoneListParams extends PaginationParams {
  search?: string
  is_active?: boolean
}

export interface ChurchListParams extends PaginationParams {
  search?: string
  zone_id?: number
  is_active?: boolean
}

export interface MemberListParams extends PaginationParams {
  search?: string
  church_id?: number
  gender?: Gender
  is_active?: boolean
}

export interface TransactionTypeListParams {
  category?: TransactionCategory
  is_active?: boolean
}

export interface TransactionListParams extends PaginationParams {
  church_id?: number
  transaction_type_id?: number
  category?: TransactionCategory
  service_type?: ServiceType
  member_id?: number
  is_verified?: boolean
  from?: string
  to?: string
  search?: string
}

export interface ChurchAnalyticsParams extends AnalyticsPeriodParams {
  church_id?: number
}

export interface ZoneAnalyticsParams extends AnalyticsPeriodParams {
  zone_id?: number
}

export interface TopMembersParams extends ChurchAnalyticsParams {
  limit?: number
}

export interface TopChurchesParams extends AnalyticsPeriodParams {
  limit?: number
}

export interface ActivityLogListParams extends PaginationParams {
  module?: string
  action?: string
  user_id?: number
  from?: string
  to?: string
}

// ─────────────────────────────────────────────────────────────────────────────
// Auth token helpers
// ─────────────────────────────────────────────────────────────────────────────

function saveToken(token: string, expiresAt: string): void {
  try {
    localStorage.setItem(TOKEN_KEY, token)
    localStorage.setItem(EXPIRES_KEY, expiresAt)
  } catch { /* SSR / private browsing — silently skip */ }
}

function getToken(): string | null {
  try { return localStorage.getItem(TOKEN_KEY) } catch { return null }
}

function clearToken(): void {
  try {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(EXPIRES_KEY)
  } catch { /* ignore */ }
}

function isTokenExpired(): boolean {
  try {
    const exp = localStorage.getItem(EXPIRES_KEY)
    if (!exp) return false
    return new Date(exp) < new Date()
  } catch { return false }
}

// ─────────────────────────────────────────────────────────────────────────────
// Query-string builder
// ─────────────────────────────────────────────────────────────────────────────

function buildQuery(params?: Record<string, unknown>): string {
  if (!params) return ''
  const entries = Object.entries(params).filter(
    ([, v]) => v !== undefined && v !== null && v !== '',
  )
  if (!entries.length) return ''
  return '?' + entries.map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`).join('&')
}

// ─────────────────────────────────────────────────────────────────────────────
// Core HTTP client
// ─────────────────────────────────────────────────────────────────────────────

class HttpClient {
  private baseUrl: string
  private refreshing = false
  private refreshQueue: Array<(token: string | null) => void> = []

  /** Callback invoked when a 401 cannot be recovered. Use to redirect to login. */
  onUnauthenticated?: () => void

  constructor(baseUrl: string) {
    this.baseUrl = baseUrl.replace(/\/$/, '')
  }

  /** Updates the base URL at runtime (e.g. from environment config). */
  setBaseUrl(url: string): void {
    this.baseUrl = url.replace(/\/$/, '')
  }

  // ── Main request method ──────────────────────────────────────────────────

  async request<T>(
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    path: string,
    options: {
      body?: unknown
      params?: Record<string, unknown>
      skipAuth?: boolean
      isRetry?: boolean
    } = {},
  ): Promise<T> {
    const url = this.baseUrl + path + buildQuery(options.params)

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    }

    if (!options.skipAuth) {
      const token = getToken()
      if (token) headers['Authorization'] = `Bearer ${token}`
    }

    const init: RequestInit = {
      method,
      headers,
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    }

    const res = await fetch(url, init)

    // ── Token expired: try silent refresh then replay ──────────────────────
    if (res.status === 401 && !options.skipAuth && !options.isRetry) {
      const newToken = await this.tryRefresh()
      if (newToken) {
        return this.request<T>(method, path, { ...options, isRetry: true })
      }
      clearToken()
      this.onUnauthenticated?.()
      const errorPayload: ApiError = { success: false, message: 'Session expired. Please log in again.' }
      throw new ApiException(401, errorPayload)
    }

    const payload = await res.json().catch(() => ({
      success: false,
      message: `HTTP ${res.status} — ${res.statusText}`,
    }))

    if (!res.ok) {
      throw new ApiException(res.status, payload as ApiError)
    }

    return payload as T
  }

  // ── Convenience wrappers ─────────────────────────────────────────────────

  get<T>(path: string, params?: Record<string, unknown>): Promise<T> {
    return this.request<T>('GET', path, { params })
  }

  post<T>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('POST', path, { body })
  }

  put<T>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('PUT', path, { body })
  }

  del<T>(path: string): Promise<T> {
    return this.request<T>('DELETE', path)
  }

  // ── Silent token refresh ─────────────────────────────────────────────────

  private tryRefresh(): Promise<string | null> {
    if (this.refreshing) {
      return new Promise((resolve) => this.refreshQueue.push(resolve))
    }

    this.refreshing = true

    return this.request<ApiSuccess<{ access_token: string; expires_at: string }>>(
      'POST',
      '/auth/refresh',
      { isRetry: true },
    )
      .then(({ data }) => {
        saveToken(data.access_token, data.expires_at)
        this.refreshQueue.forEach((cb) => cb(data.access_token))
        return data.access_token
      })
      .catch(() => {
        this.refreshQueue.forEach((cb) => cb(null))
        return null
      })
      .finally(() => {
        this.refreshing = false
        this.refreshQueue = []
      })
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service definitions
// ─────────────────────────────────────────────────────────────────────────────

function makeAuthService(http: HttpClient) {
  return {
    /**
     * Log in with email and password.
     * Automatically stores the returned Bearer token.
     */
    async login(body: LoginBody) {
      const res = await http.request<ApiSuccess<{
        access_token: string
        token_type: string
        expires_at: string
        user: AuthenticatedUser
      }>>('POST', '/auth/login', { body, skipAuth: true })
      saveToken(res.data.access_token, res.data.expires_at)
      return res
    },

    /** Log out. Clears the stored token. */
    async logout() {
      const res = await http.post<ApiSuccess<null>>('/auth/logout')
      clearToken()
      return res
    },

    /** Fetch the authenticated user's profile. */
    me(): Promise<ApiSuccess<AuthenticatedUser>> {
      return http.get('/auth/me')
    },

    /** Update name / phone. */
    updateProfile(body: UpdateProfileBody): Promise<ApiSuccess<AuthenticatedUser>> {
      return http.put('/auth/profile', body)
    },

    /** Change password. Forces re-login after success. */
    changePassword(body: ChangePasswordBody): Promise<ApiSuccess<null>> {
      return http.put('/auth/change-password', body)
    },

    /** Send a password reset link to the given email. */
    forgotPassword(body: ForgotPasswordBody): Promise<ApiSuccess<null>> {
      return http.request<ApiSuccess<null>>('POST', '/auth/forgot-password', {
        body,
        skipAuth: true,
      })
    },

    /** Reset password using the emailed token. */
    resetPassword(body: ResetPasswordBody): Promise<ApiSuccess<null>> {
      return http.request<ApiSuccess<null>>('POST', '/auth/reset-password', {
        body,
        skipAuth: true,
      })
    },

    /** Verify that a reset token is still valid (use before showing the reset form). */
    verifyResetToken(params: VerifyResetTokenParams): Promise<ApiSuccess<null>> {
      return http.request<ApiSuccess<null>>('GET', '/auth/reset-password/verify', {
        params: params as Record<string, unknown>,
        skipAuth: true,
      })
    },

    /**
     * Rotate the Bearer token.
     * Called automatically by the HTTP client on 401 — but you can call
     * it proactively when the token is about to expire.
     */
    async refresh() {
      const res = await http.post<ApiSuccess<{ access_token: string; expires_at: string }>>(
        '/auth/refresh',
      )
      saveToken(res.data.access_token, res.data.expires_at)
      return res
    },

    /** Returns the raw token stored in localStorage, or null. */
    getToken,
    /** Returns true if no token exists or it is past its expiry time. */
    isExpired: isTokenExpired,
    /** Clears the stored token (use on forced logout). */
    clearToken,
  }
}

function makeMinistryService(http: HttpClient) {
  return {
    /** Get the single ministry profile. MinistryAdmin only. */
    get(): Promise<ApiSuccess<Ministry>> {
      return http.get('/ministry')
    },

    /** Update the ministry profile. MinistryAdmin only. */
    update(body: UpdateMinistryBody): Promise<ApiSuccess<Ministry>> {
      return http.put('/ministry', body)
    },
  }
}

function makeUsersService(http: HttpClient) {
  return {
    /** List users. MinistryAdmin only. */
    list(params?: UserListParams): Promise<ApiListResponse<User>> {
      return http.get('/users', params as Record<string, unknown>)
    },

    /** Create a user. MinistryAdmin only. ministry_id is auto-assigned by the server. */
    create(body: StoreUserBody): Promise<ApiSuccess<User>> {
      return http.post('/users', body)
    },

    /** Get a single user. MinistryAdmin only. */
    get(id: string): Promise<ApiSuccess<User>> {
      return http.get(`/users/${id}`)
    },

    /** Update a user. MinistryAdmin only. */
    update(id: string, body: UpdateUserBody): Promise<ApiSuccess<User>> {
      return http.put(`/users/${id}`, body)
    },

    /** Delete a user. MinistryAdmin only. */
    delete(id: string): Promise<ApiSuccess<null>> {
      return http.del(`/users/${id}`)
    },

    /** Activate or deactivate a user (toggles). MinistryAdmin only. */
    toggleStatus(id: string): Promise<ApiSuccess<{ is_active: boolean }>> {
      return http.post(`/users/${id}/toggle-status`)
    },
  }
}

function makeZonesService(http: HttpClient) {
  return {
    /** List zones. ZoneAdmin+ only. */
    list(params?: ZoneListParams): Promise<ApiListResponse<Zone>> {
      return http.get('/zones', params as Record<string, unknown>)
    },

    /** Create a zone. MinistryAdmin only. ministry_id is auto-assigned. */
    create(body: StoreZoneBody): Promise<ApiSuccess<Zone>> {
      return http.post('/zones', body)
    },

    /** Get a single zone with its churches. ZoneAdmin+ only. */
    get(id: string): Promise<ApiSuccess<Zone & { churches: Church[] }>> {
      return http.get(`/zones/${id}`)
    },

    /** Update a zone. MinistryAdmin only. */
    update(id: string, body: UpdateZoneBody): Promise<ApiSuccess<Zone>> {
      return http.put(`/zones/${id}`, body)
    },

    /** Delete a zone (only if it has no churches). MinistryAdmin only. */
    delete(id: string): Promise<ApiSuccess<null>> {
      return http.del(`/zones/${id}`)
    },
  }
}

function makeChurchesService(http: HttpClient) {
  return {
    /** List churches (scoped by role). */
    list(params?: ChurchListParams): Promise<ApiListResponse<Church>> {
      return http.get('/churches', params as Record<string, unknown>)
    },

    /** Create a church. ZoneAdmin+ only. */
    create(body: StoreChurchBody): Promise<ApiSuccess<Church>> {
      return http.post('/churches', body)
    },

    /** Get a single church. All admins (scoped). */
    get(id: string): Promise<ApiSuccess<Church>> {
      return http.get(`/churches/${id}`)
    },

    /** Update a church. All admins (scoped). */
    update(id: string, body: UpdateChurchBody): Promise<ApiSuccess<Church>> {
      return http.put(`/churches/${id}`, body)
    },

    /** Delete a church (only if empty). ZoneAdmin+ only. */
    delete(id: string): Promise<ApiSuccess<null>> {
      return http.del(`/churches/${id}`)
    },
  }
}

function makeMembersService(http: HttpClient) {
  return {
    /** List members (scoped by role). */
    list(params?: MemberListParams): Promise<ApiListResponse<Member>> {
      return http.get('/members', params as Record<string, unknown>)
    },

    /** Add a member. All admins (scoped). */
    create(body: StoreMemberBody): Promise<ApiSuccess<Member>> {
      return http.post('/members', body)
    },

    /** Get a member with transaction count. */
    get(id: string): Promise<ApiSuccess<Member>> {
      return http.get(`/members/${id}`)
    },

    /** Update a member. */
    update(id: string, body: UpdateMemberBody): Promise<ApiSuccess<Member>> {
      return http.put(`/members/${id}`, body)
    },

    /** Soft-delete a member. */
    delete(id: string): Promise<ApiSuccess<null>> {
      return http.del(`/members/${id}`)
    },
  }
}

function makeTransactionTypesService(http: HttpClient) {
  return {
    /**
     * List all transaction types.
     * Typically called once at app start and cached client-side.
     * All admins.
     */
    list(params?: TransactionTypeListParams): Promise<ApiSuccess<TransactionType[]>> {
      return http.get('/transaction-types', params as Record<string, unknown>)
    },

    /** Get a single type with usage count. All admins. */
    get(id: string): Promise<ApiSuccess<TransactionType>> {
      return http.get(`/transaction-types/${id}`)
    },

    /** Create a type. MinistryAdmin only. */
    create(body: StoreTransactionTypeBody): Promise<ApiSuccess<TransactionType>> {
      return http.post('/transaction-types', body)
    },

    /** Update a type. MinistryAdmin only. */
    update(id: string, body: UpdateTransactionTypeBody): Promise<ApiSuccess<TransactionType>> {
      return http.put(`/transaction-types/${id}`, body)
    },

    /** Delete a type (only if unused). MinistryAdmin only. */
    delete(id: string): Promise<ApiSuccess<null>> {
      return http.del(`/transaction-types/${id}`)
    },
  }
}

function makeTransactionsService(http: HttpClient) {
  return {
    /**
     * List transactions (scoped by role).
     * Supports date range, type, service type, verification status filters.
     */
    list(params?: TransactionListParams): Promise<ApiListResponse<Transaction>> {
      return http.get('/transactions', params as Record<string, unknown>)
    },

    /**
     * Record a new transaction (offering, tithe, donation, etc.).
     * Church admins can only record for their church.
     */
    create(body: StoreTransactionBody): Promise<ApiSuccess<Transaction>> {
      return http.post('/transactions', body)
    },

    /** Get a single transaction with full relations. */
    get(id: string): Promise<ApiSuccess<Transaction>> {
      return http.get(`/transactions/${id}`)
    },

    /** Edit a transaction (not allowed if already verified, unless MinistryAdmin). */
    update(id: string, body: UpdateTransactionBody): Promise<ApiSuccess<Transaction>> {
      return http.put(`/transactions/${id}`, body)
    },

    /** Soft-delete a transaction (not allowed if verified, unless MinistryAdmin). */
    delete(id: string): Promise<ApiSuccess<null>> {
      return http.del(`/transactions/${id}`)
    },

    /** Mark a transaction as verified. All admins (scoped). */
    verify(id: string): Promise<ApiSuccess<Transaction>> {
      return http.post(`/transactions/${id}/verify`)
    },

    /** Reverse verification. MinistryAdmin only. */
    unverify(id: string): Promise<ApiSuccess<Transaction>> {
      return http.post(`/transactions/${id}/unverify`)
    },
  }
}

function makeAnalyticsService(http: HttpClient) {
  return {
    /** Church-level analytics. All admins (auto-scoped by role). */
    church: {
      /**
       * Financial summary for a church — totals, by-type, by-category, by-service.
       * ChurchAdmin: always their church.
       * ZoneAdmin / MinistryAdmin: pass `church_id` to specify which church.
       */
      summary(params?: ChurchAnalyticsParams): Promise<ApiSuccess<ChurchSummary>> {
        return http.get('/analytics/church/summary', params as Record<string, unknown>)
      },

      /** Daily / weekly / monthly trend line for a church. */
      trend(params?: ChurchAnalyticsParams): Promise<ApiSuccess<ChurchTrend>> {
        return http.get('/analytics/church/trend', params as Record<string, unknown>)
      },

      /** Top contributing members for a church. */
      topMembers(params?: TopMembersParams): Promise<ApiSuccess<ChurchTopMembers>> {
        return http.get('/analytics/church/top-members', params as Record<string, unknown>)
      },
    },

    /** Zone-level analytics. ZoneAdmin+ only. */
    zone: {
      /**
       * Financial summary across all churches in a zone.
       * ZoneAdmin: always their zone.
       * MinistryAdmin: pass `zone_id` to specify.
       */
      summary(params?: ZoneAnalyticsParams): Promise<ApiSuccess<ZoneSummary>> {
        return http.get('/analytics/zone/summary', params as Record<string, unknown>)
      },

      /** Side-by-side financial comparison of all churches in the zone. */
      churchesComparison(params?: ZoneAnalyticsParams): Promise<ApiSuccess<ZoneChurchesComparison>> {
        return http.get('/analytics/zone/churches-comparison', params as Record<string, unknown>)
      },

      /** Trend line aggregated across the whole zone. */
      trend(params?: ZoneAnalyticsParams): Promise<ApiSuccess<ZoneTrend>> {
        return http.get('/analytics/zone/trend', params as Record<string, unknown>)
      },
    },

    /** Ministry-level analytics. MinistryAdmin only. */
    ministry: {
      /**
       * Dashboard overview: ministry info, entity counts, this month, last month, this year.
       * Best starting point for the MinistryAdmin dashboard.
       */
      overview(): Promise<ApiSuccess<MinistryOverview>> {
        return http.get('/analytics/ministry/overview')
      },

      /** Platform-wide financial summary for any period. */
      summary(params?: AnalyticsPeriodParams): Promise<ApiSuccess<MinistrySummary>> {
        return http.get('/analytics/ministry/summary', params as Record<string, unknown>)
      },

      /** Side-by-side comparison of all zones. */
      zonesComparison(params?: AnalyticsPeriodParams): Promise<ApiSuccess<ZonesComparison>> {
        return http.get('/analytics/ministry/zones-comparison', params as Record<string, unknown>)
      },

      /** Top performing churches across the whole ministry. */
      topChurches(params?: TopChurchesParams): Promise<ApiSuccess<MinistryTopChurches>> {
        return http.get('/analytics/ministry/top-churches', params as Record<string, unknown>)
      },

      /** Platform-wide revenue trend. */
      trend(params?: AnalyticsPeriodParams): Promise<ApiSuccess<MinistryTrend>> {
        return http.get('/analytics/ministry/trend', params as Record<string, unknown>)
      },
    },
  }
}

function makeActivityLogsService(http: HttpClient) {
  return {
    /**
     * Paginated activity log (scoped by role — church/zone/ministry).
     * Filterable by module, action, user, and date range.
     */
    list(params?: ActivityLogListParams): Promise<ApiListResponse<ActivityLog>> {
      return http.get('/activity-logs', params as Record<string, unknown>)
    },

    /**
     * Aggregated stats: totals by module, by action, by user, daily trend.
     * Scoped by role.
     */
    stats(params?: AnalyticsPeriodParams): Promise<ApiSuccess<ActivityStats>> {
      return http.get('/activity-logs/stats', params as Record<string, unknown>)
    },

    /**
     * API usage analytics: request volume, hourly distribution, status codes,
     * active users. Scoped by role.
     */
    usage(params?: AnalyticsPeriodParams): Promise<ApiSuccess<UsageStats>> {
      return http.get('/activity-logs/usage', params as Record<string, unknown>)
    },
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Assemble the client
// ─────────────────────────────────────────────────────────────────────────────

function createApiClient(baseUrl: string) {
  const http = new HttpClient(baseUrl)

  const client = {
    /** Underlying HTTP client — use for one-off requests not covered by the helpers. */
    http,

    /** Set or override the API base URL (useful when loading from env at runtime). */
    setBaseUrl: http.setBaseUrl.bind(http),

    /**
     * Provide a callback that runs when the API returns 401 and the silent
     * token refresh also fails. Use this to redirect the user to the login page.
     *
     * @example
     * api.onUnauthenticated(() => router.push('/login'))
     */
    onUnauthenticated(cb: () => void) {
      http.onUnauthenticated = cb
    },

    auth:             makeAuthService(http),
    ministry:         makeMinistryService(http),
    users:            makeUsersService(http),
    zones:            makeZonesService(http),
    churches:         makeChurchesService(http),
    members:          makeMembersService(http),
    transactionTypes: makeTransactionTypesService(http),
    transactions:     makeTransactionsService(http),
    analytics:        makeAnalyticsService(http),
    activityLogs:     makeActivityLogsService(http),
  }

  return client
}

// ─────────────────────────────────────────────────────────────────────────────
// Singleton export
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Pre-configured singleton for the Church Ministry Platform API.
 *
 * The base URL is read from the environment variable:
 *   VITE_API_URL      → Vite / React / Vue
 *   NEXT_PUBLIC_API_URL → Next.js
 *   REACT_APP_API_URL → CRA
 *
 * Falls back to http://localhost:8000/api for local development.
 */
const apiBaseUrl: string =
  (typeof import.meta !== 'undefined' && (import.meta as Record<string, unknown>).env
    ? ((import.meta as Record<string, Record<string, unknown>>).env['VITE_API_URL'] as string)
    : undefined) ??
  (typeof process !== 'undefined'
    ? process.env['NEXT_PUBLIC_API_URL'] ?? process.env['REACT_APP_API_URL']
    : undefined) ??
  'http://localhost:8000'

const api = createApiClient(apiBaseUrl)

export default api
export { createApiClient }
export type ApiClient = ReturnType<typeof createApiClient>
