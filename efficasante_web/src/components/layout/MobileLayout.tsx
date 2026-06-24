import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { API_BASE, systemLogoUrl } from '../../config';
import { IconHome, IconPeople, IconCalendar, IconMedical, IconLab, IconSmartphone } from './MobileNavIcons';
import './MobileLayout.css';

const navItems = [
  { to: '/', label: 'Accueil', Icon: IconHome },
  { to: '/patients', label: 'Patients', Icon: IconPeople },
  { to: '/rendez-vous', label: 'RDV', Icon: IconCalendar },
  { to: '/consultations', label: 'Consult.', Icon: IconMedical },
  { to: '/laboratoire', label: 'Labo', Icon: IconLab },
];

export default function MobileLayout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <div className="layout mobile-layout" data-mobile="true">
      <header className="mobile-header">
        <div className="mobile-header-inner">
          <img
            className="mobile-logo"
            src={systemLogoUrl(240, 80)}
            alt=""
            width={120}
            height={40}
            decoding="async"
          />
          <div className="mobile-title-block">
            <h1 className="mobile-title">Efficasante</h1>
            <span className="mobile-badge" aria-label="Version mobile">
              <IconSmartphone className="mobile-badge-icon" />
              <span>Mobile</span>
            </span>
          </div>
          <div className="mobile-header-actions">
            <a
              href={`${API_BASE}/dashboard.php`}
              target="_blank"
              rel="noopener noreferrer"
              className="mobile-header-icon"
              aria-label="Tableau de bord complet"
              title="Ouvrir le tableau de bord"
            >
              <span className="icon-bell" aria-hidden>🔔</span>
            </a>
            <button
              type="button"
              className="mobile-menu-btn"
              onClick={() => navigate('/')}
              aria-label="Menu"
            >
              <span className="icon-menu">☰</span>
            </button>
          </div>
        </div>
        <div className="mobile-user-bar">
          <span className="mobile-user-name">{user?.nom_utilisateur}</span>
          <span className="mobile-user-role">{user?.role}</span>
          <button type="button" className="mobile-logout" onClick={handleLogout}>
            Déconnexion
          </button>
        </div>
      </header>
      <main className="main-content">{children}</main>
      <nav className="bottom-nav" aria-label="Navigation principale">
        <div className="bottom-nav-inner">
          {navItems.map(({ to, label, Icon }) => (
            <NavLink
              key={to}
              to={to}
              className={({ isActive }) => `bottom-nav-item ${isActive ? 'active' : ''}`}
            >
              {({ isActive }) => (
                <>
                  <span className="bottom-nav-icon">
                    <Icon active={isActive} />
                  </span>
                  <span className="bottom-nav-label">{label}</span>
                </>
              )}
            </NavLink>
          ))}
        </div>
      </nav>
    </div>
  );
}
