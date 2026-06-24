export const API_BASE =
  import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') ||
  (typeof window !== 'undefined' ? `${window.location.origin}` : '') + '/efficasante';

export const REST_URL = `${API_BASE}/api/rest/index.php`;

/** Même URL que l’app PHP (`includes/header_logo.php`) : logo issu des paramètres système. */
export function systemLogoUrl(width = 360, height = 120): string {
  const w = Math.max(1, Math.min(width, 1200));
  const h = Math.max(1, Math.min(height, 800));
  return `${API_BASE}/display_logo.php?w=${w}&h=${h}`;
}

export const MOBILE_BREAKPOINT = 768;
