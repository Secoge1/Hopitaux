import { REST_URL } from '../config';

const TOKEN_KEY = 'efficasante_token';
const USER_KEY = 'efficasante_user';

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setStoredToken(token: string | null): void {
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export function getStoredUser(): { id: number; nom_utilisateur: string; email: string; role: string } | null {
  try {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function setStoredUser(user: { id: number; nom_utilisateur: string; email: string; role: string } | null): void {
  if (user) localStorage.setItem(USER_KEY, JSON.stringify(user));
  else localStorage.removeItem(USER_KEY);
}

async function rest<T>(
  path: string,
  opts: { method?: string; body?: object; params?: Record<string, string> } = {}
): Promise<T> {
  const { method = 'GET', body, params = {} } = opts;
  const q = new URLSearchParams({ path, ...params });
  const url = `${REST_URL}?${q}`;
  const headers: Record<string, string> = { 'Content-Type': 'application/json', Accept: 'application/json' };
  const token = getStoredToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const res = await fetch(url, { method, headers, body: body ? JSON.stringify(body) : undefined });
  const data = await res.json().catch(() => ({}));
  if (res.status >= 400) throw new Error((data as { error?: string }).error || `HTTP ${res.status}`);
  return data as T;
}

export const api = {
  login: (email: string, password: string) =>
    rest<{ success: boolean; token?: string; user?: { id: number; nom_utilisateur: string; email: string; role: string } }>(
      'login',
      { method: 'POST', body: { email, password } }
    ),

  getDashboardStats: () => rest<{ success: boolean; data: Record<string, number | string> }>('dashboard/stats'),

  getPatients: (params?: { page?: number; limit?: number; search?: string; id?: number }) => {
    const p: Record<string, string> = {};
    if (params?.page) p.page = String(params.page);
    if (params?.limit) p.limit = String(params.limit);
    if (params?.search) p.search = params.search;
    if (params?.id != null) p.id = String(params.id);
    return rest<{ success: boolean; data: unknown[] | unknown; pagination?: { page: number; limit: number; total: number; total_pages: number } }>(
      'patients',
      { params: p }
    );
  },

  getRendezVous: (params?: { page?: number; limit?: number; date?: string }) => {
    const p: Record<string, string> = {};
    if (params?.page) p.page = String(params.page);
    if (params?.limit) p.limit = String(params.limit);
    if (params?.date) p.date = params.date;
    return rest<{ success: boolean; data: unknown[]; pagination?: { page: number; limit: number; total: number; total_pages: number } }>(
      'rendez-vous',
      { params: p }
    );
  },

  getConsultations: (params?: { page?: number; limit?: number }) => {
    const p: Record<string, string> = {};
    if (params?.page) p.page = String(params.page);
    if (params?.limit) p.limit = String(params.limit);
    return rest<{ success: boolean; data: unknown[]; pagination?: { page: number; limit: number; total: number; total_pages: number } }>(
      'consultations',
      { params: p }
    );
  },

  getLaboratoire: (params?: { limit?: number; statut?: string; search?: string }) => {
    const p: Record<string, string> = {};
    if (params?.limit) p.limit = String(params.limit);
    if (params?.statut) p.statut = params.statut;
    if (params?.search) p.search = params.search;
    return rest<{ success: boolean; data: unknown[]; pagination?: { limit: number; total: number } }>(
      'laboratoire',
      { params: p }
    );
  },

  getTenantNotices: () =>
    rest<{
      success: boolean;
      data: {
        user_id: number;
        notices: Array<{
          key: string;
          enabled: boolean;
          stamp: string | null;
          title: string;
          message: string;
          duration_ms?: number;
        }>;
      };
    }>('tenant/notices'),
};
