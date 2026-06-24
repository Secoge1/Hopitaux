import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import { API_BASE } from '../config';
import '../styles/ListPages.css';

type Rdv = {
  id: number;
  date_rdv?: string;
  statut?: string;
  patient_nom?: string;
  patient_prenom?: string;
  medecin_nom?: string;
  medecin_prenom?: string;
};

function formatDate(s: string | undefined) {
  if (!s) return '';
  try {
    const d = new Date(s);
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  } catch {
    return s;
  }
}

export default function RendezVous() {
  const [list, setList] = useState<Rdv[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filterDate, setFilterDate] = useState('');

  useEffect(() => {
    setLoading(true);
    api
      .getRendezVous({ page: 1, limit: 50, date: filterDate || undefined })
      .then((res: { success?: boolean; data?: Rdv[] }) => {
        if (res.success && Array.isArray(res.data)) setList(res.data);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
      .finally(() => setLoading(false));
  }, [filterDate]);

  return (
    <div className="list-page animate-in">
      <div className="page-header-row">
        <h1 className="page-title">Rendez-vous</h1>
        <div className="page-action-group">
          <a
            href={`${API_BASE}/rendez-vous/calendrier.php`}
            target="_blank"
            rel="noopener noreferrer"
            className="btn-secondary page-action-btn"
          >
            Calendrier
          </a>
          <a
            href={`${API_BASE}/rendez-vous/ajouter.php`}
            target="_blank"
            rel="noopener noreferrer"
            className="btn-primary page-action-btn"
          >
            + Nouveau RDV
          </a>
        </div>
      </div>
      <div className="filter-row">
        <input
          type="date"
          value={filterDate}
          onChange={(e) => setFilterDate(e.target.value)}
          className="search-input"
        />
        {filterDate && (
          <button type="button" className="btn-primary" onClick={() => setFilterDate('')}>
            Effacer
          </button>
        )}
      </div>
      {error && <p className="list-error">{error}</p>}
      {loading ? (
        <div className="list-loading">Chargement…</div>
      ) : list.length === 0 ? (
        <div className="list-empty">Aucun rendez-vous</div>
      ) : (
        <ul className="list-cards">
          {list.map((r) => (
            <li key={r.id} className="list-card-item">
              <div className="card list-card elevated animate-card list-card-with-actions">
                <span className="list-card-avatar rdv">📅</span>
                <div className="list-card-body">
                  <strong>{[r.patient_prenom, r.patient_nom].filter(Boolean).join(' ')}</strong>
                  <span className="list-card-meta">{formatDate(r.date_rdv)}</span>
                  {[r.medecin_prenom, r.medecin_nom].filter(Boolean).length > 0 && (
                    <span className="list-card-meta">Dr. {[r.medecin_prenom, r.medecin_nom].filter(Boolean).join(' ')}</span>
                  )}
                </div>
                {r.statut && (
                  <span className={`badge badge-${r.statut === 'confirme' ? 'success' : r.statut === 'annule' ? 'danger' : 'default'}`}>
                    {r.statut}
                  </span>
                )}
                <a
                  href={`${API_BASE}/rendez-vous/voir.php?id=${r.id}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="list-card-action-btn"
                  title="Voir"
                >
                  Voir
                </a>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
