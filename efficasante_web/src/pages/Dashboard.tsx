import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { api } from '../services/api';
import '../styles/Dashboard.css';

const statCards = [
  { key: 'patients', label: 'Patients', icon: '👤', color: '#11998e' },
  { key: 'consultations_aujourd_hui', label: 'Consultations aujourd\'hui', icon: '🩺', color: '#4facfe' },
  { key: 'rdv_aujourd_hui', label: 'Rendez-vous aujourd\'hui', icon: '📅', color: '#f093fb' },
  { key: 'analyses_en_cours', label: 'Analyses en cours', icon: '🧪', color: '#fa709a' },
  { key: 'paiements_en_attente', label: 'Paiements en attente', icon: '💳', color: '#ff9a9e' },
  { key: 'medecins_actifs', label: 'Médecins actifs', icon: '👨‍⚕️', color: '#667eea' },
];

export default function Dashboard() {
  const { user } = useAuth();
  const [stats, setStats] = useState<Record<string, number | string> | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .getDashboardStats()
      .then((res) => {
        if (!cancelled && res.data) setStats(res.data as Record<string, number | string>);
      })
      .catch((e) => {
        if (!cancelled) setError(e instanceof Error ? e.message : 'Erreur');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  if (loading) {
    return (
      <div className="page-loading">
        <div className="spinner" aria-hidden />
        <p>Chargement des statistiques…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="page-error">
        <p>{error}</p>
        <button type="button" className="btn-primary" onClick={() => window.location.reload()}>
          Réessayer
        </button>
      </div>
    );
  }

  return (
    <div className="dashboard-page animate-in">
      <h1 className="page-title">Tableau de bord</h1>
      <p className="page-subtitle">Bonjour, {user?.nom_utilisateur}</p>
      <div className="stats-grid">
        {statCards.map(({ key, label, icon, color }, i) => (
          <div
            key={key}
            className="stat-card card elevated animate-card"
            style={{ animationDelay: `${i * 50}ms` }}
          >
            <div className="stat-icon" style={{ backgroundColor: `${color}20`, color }}>
              {icon}
            </div>
            <div className="stat-value">{stats?.[key] ?? 0}</div>
            <div className="stat-label">{label}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
