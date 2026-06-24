import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import { API_BASE } from '../config';
import '../styles/ListPages.css';

type Analyse = {
  id: number;
  type_analyse?: string;
  description?: string;
  statut?: string;
  date_creation?: string;
  nom?: string;
  prenom?: string;
  patient_id?: number;
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

export default function Laboratoire() {
  const [list, setList] = useState<Analyse[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statut, setStatut] = useState('');
  const [search, setSearch] = useState('');

  const load = () => {
    setLoading(true);
    setError(null);
    api
      .getLaboratoire({ limit: 50, statut: statut || undefined, search: search || undefined })
      .then((res: { success?: boolean; data?: Analyse[] }) => {
        if (res.success && Array.isArray(res.data)) setList(res.data);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, [statut]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    load();
  };

  return (
    <div className="list-page animate-in">
      <div className="page-header-row">
        <h1 className="page-title">Laboratoire</h1>
        <div className="page-action-group">
          <a
            href={`${API_BASE}/laboratoire/rapport.php`}
            target="_blank"
            rel="noopener noreferrer"
            className="btn-secondary page-action-btn"
          >
            Rapport
          </a>
          <a
            href={`${API_BASE}/laboratoire/ajouter.php`}
            target="_blank"
            rel="noopener noreferrer"
            className="btn-primary page-action-btn"
          >
            + Nouvelle analyse
          </a>
        </div>
      </div>
      <form onSubmit={handleSearch} className="search-form">
        <input
          type="search"
          placeholder="Type, description ou patient"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="search-input"
        />
        <select
          value={statut}
          onChange={(e) => setStatut(e.target.value)}
          className="search-input"
          style={{ maxWidth: 160 }}
        >
          <option value="">Tous les statuts</option>
          <option value="en_attente">En attente</option>
          <option value="en_cours">En cours</option>
          <option value="termine">Terminé</option>
        </select>
        <button type="submit" className="btn-primary">Rechercher</button>
      </form>
      {error && <p className="list-error">{error}</p>}
      {loading ? (
        <div className="list-loading">Chargement…</div>
      ) : list.length === 0 ? (
        <div className="list-empty">Aucune analyse</div>
      ) : (
        <ul className="list-cards">
          {list.map((a) => (
            <li key={a.id} className="list-card-item">
              <div className="card list-card elevated animate-card list-card-with-actions">
                <span className="list-card-avatar lab">🧪</span>
                <div className="list-card-body">
                  <strong>{a.type_analyse || 'Analyse'}</strong>
                  <span className="list-card-meta">
                    {[a.prenom, a.nom].filter(Boolean).join(' ')}
                    {a.date_creation && ` · ${formatDate(a.date_creation)}`}
                  </span>
                  {a.description && <span className="list-card-meta motif">{a.description}</span>}
                </div>
                {a.statut && (
                  <span className={`badge badge-${a.statut === 'termine' ? 'success' : a.statut === 'en_cours' ? 'default' : 'default'}`}>
                    {a.statut}
                  </span>
                )}
                <a
                  href={`${API_BASE}/laboratoire/voir.php?id=${a.id}`}
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
