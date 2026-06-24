import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { systemLogoUrl } from '../../config';
import './DesktopLayout.css';

const navItems = [
  { to: '/', label: 'Tableau de bord', icon: '📊' },
  { to: '/patients', label: 'Patients', icon: '👤' },
  { to: '/rendez-vous', label: 'Rendez-vous', icon: '📅' },
  { to: '/consultations', label: 'Consultations', icon: '🩺' },
  { to: '/laboratoire', label: 'Laboratoire', icon: '🧪' },
];

export default function DesktopLayout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <div className="layout desktop-layout">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <img
            className="sidebar-logo-img"
            src={systemLogoUrl(360, 120)}
            alt=""
            width={180}
            height={60}
            decoding="async"
          />
          <h1 className="sidebar-title">Efficasante</h1>
        </div>
        <nav className="sidebar-nav">
          {navItems.map(({ to, label, icon }) => (
            <NavLink key={to} to={to} className={({ isActive }) => (isActive ? 'active' : '')}>
              <span className="nav-icon">{icon}</span>
              {label}
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="sidebar-user">
            <span className="user-avatar">{user?.nom_utilisateur?.charAt(0) || '?'}</span>
            <div className="user-info">
              <span className="user-name">{user?.nom_utilisateur}</span>
              <span className="user-role">{user?.role}</span>
            </div>
          </div>
          <button type="button" className="sidebar-logout" onClick={handleLogout}>
            Déconnexion
          </button>
        </div>
      </aside>
      <main className="main-content">{children}</main>
    </div>
  );
}
