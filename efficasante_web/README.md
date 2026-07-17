# Efficasante – Unified Healthcare Web App

**One codebase. Adaptive UI. No redirection.**

- **Desktop**: Sidebar layout, full navigation.
- **Mobile**: Flutter-inspired Material 3 layout, bottom navigation, cards, touch-optimized.
- **PWA**: Installable on Android & iOS, offline support, uses existing backend API.

## Tech

- **React 18** + **Vite 5**
- **TypeScript**
- **React Router 6**
- **PWA** (vite-plugin-pwa: manifest, service worker, cache)
- **Existing backend**: PHP REST API at `api/rest/index.php` (same Efficasante project)

## Setup

```bash
cd efficasante_web
npm install
```

## Configure API URL

Create `.env` (copy from `.env.example`):

```env
# Local WAMP
VITE_API_BASE_URL=http://localhost/Hopitaux

# Production build
# VITE_API_BASE_URL=https://sesante.secogesarl.com
```

If omitted when the PWA is served from the same domain as PHP, `window.location.origin` is used.

## Run

```bash
npm run dev
```

- App: http://localhost:5173  
- If backend is on same machine, use proxy in `vite.config.ts` or set `VITE_API_BASE_URL` so API calls hit your PHP backend.

## Build & deploy

```bash
npm run build
```

Output: `dist/`. Serve `dist/` as a static site (e.g. same server as Efficasante, or CDN).

- **Android / iOS**: Users open the app URL in browser, then “Add to Home Screen” / “Install app” for PWA install.
- **Offline**: Service worker caches assets and uses NetworkFirst for API (with short timeout).

## Behaviour

- **Breakpoint 768px**: Below = mobile layout (bottom nav, Material 3 cards). Above = desktop layout (sidebar).
- **No redirect**: Same URL and routes; layout adapts to viewport.
- **Auth**: Login stores token in `localStorage`; all API requests send `Authorization: Bearer <token>`.

## Project structure

```
efficasante_web/
├── public/           # Static assets, favicon
├── src/
│   ├── components/layout/   # AdaptiveShell, DesktopLayout, MobileLayout
│   ├── contexts/           # AuthContext
│   ├── hooks/              # useIsMobile (breakpoint)
│   ├── pages/              # Login, Dashboard, Patients, RendezVous, Consultations
│   ├── services/           # api.ts (REST client)
│   ├── styles/             # global + page CSS (Material 3 vars)
│   ├── App.tsx
│   └── main.tsx
├── index.html
├── vite.config.ts    # React + PWA plugin
└── package.json
```

## License

Same as Efficasante project.
