import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import { API_BASE } from '../config';
import '../styles/ListPages.css';

type Consult = {
  id: number;
  date_consultation?: string;
  motif?: string;
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

export default function Consultations() {
  const [list, setList] = useState<Consult[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .getConsultations({ page: 1, limit: 50 })
      .then((res: { success?: boolean; data?: Consult[] }) => {
        if (res.success && Array.isArray(res.data)) setList(res.data);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="list-page animate-in">
      <div className="page-header-row">
        <h1 className="page-title">Consultations</h1>
        <a
          href={`${API_BASE}/consultations/ajouter.php`}
          target="_blank"
          rel="noopener noreferrer"
          className="btn-primary page-action-btn"
        >
          + Nouvelle consultation
        </a>
      </div>
      {error && <p className="list-error">{error}</p>}
      {loading ? (
        <div className="list-loading">Chargement…</div>
      ) : list.length === 0 ? (
        <div className="list-empty">Aucune consultation</div>
      ) : (
        <ul className="list-cards">
          {list.map((c) => (
            <li key={c.id} className="list-card-item">
              <div className="card list-card elevated animate-card list-card-with-actions">
                <span className="list-card-avatar consult">🩺</span>
                <div className="list-card-body">
                  <strong>{[c.patient_prenom, c.patient_nom].filter(Boolean).join(' ')}</strong>
                  <span className="list-card-meta">{formatDate(c.date_consultation)}</span>
                  {[c.medecin_prenom, c.medecin_nom].filter(Boolean).length > 0 && (
                    <span className="list-card-meta">Dr. {[c.medecin_prenom, c.medecin_nom].filter(Boolean).join(' ')}</span>
                  )}
                  {c.motif && <span className="list-card-meta motif">{c.motif}</span>}
                </div>
                <a
                  href={`${API_BASE}/consultations/voir.php?id=${c.id}`}
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
