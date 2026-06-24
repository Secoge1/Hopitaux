import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../services/api';
import { API_BASE } from '../config';
import '../styles/ListPages.css';

type Patient = { id: number; nom?: string; prenom?: string; numero_dossier?: string };

export default function Patients() {
  const [list, setList] = useState<Patient[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [search, setSearch] = useState('');

  const load = useCallback(async (pageNum: number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.getPatients({
        page: pageNum,
        limit: 12,
        search: search || undefined,
      }) as { success: boolean; data: Patient[]; pagination?: { total_pages: number } };
      if (res.success && Array.isArray(res.data)) {
        setList(res.data);
        setTotalPages(res.pagination?.total_pages ?? 1);
        setPage(pageNum);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erreur');
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => {
    load(1);
  }, [search]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    load(1);
  };

  return (
    <div className="list-page animate-in">
      <div className="page-header-row">
        <h1 className="page-title">Patients</h1>
        <a
          href={`${API_BASE}/patients/ajouter.php`}
          target="_blank"
          rel="noopener noreferrer"
          className="btn-primary page-action-btn"
        >
          + Nouveau patient
        </a>
      </div>
      <form onSubmit={handleSearch} className="search-form">
        <input
          type="search"
          placeholder="Nom, prénom ou n° dossier"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="search-input"
        />
        <button type="submit" className="btn-primary">Rechercher</button>
      </form>
      {error && <p className="list-error">{error}</p>}
      {loading ? (
        <div className="list-loading"><span className="spinner" /> Chargement…</div>
      ) : list.length === 0 ? (
        <div className="list-empty">Aucun patient</div>
      ) : (
        <ul className="list-cards">
          {list.map((p) => (
            <li key={p.id} className="list-card-item">
              <Link to={`/patients/${p.id}`} className="card list-card elevated animate-card">
                <span className="list-card-avatar">
                  {(p.prenom || p.nom || '?').charAt(0).toUpperCase()}
                </span>
                <div className="list-card-body">
                  <strong>{[p.prenom, p.nom].filter(Boolean).join(' ')}</strong>
                  {p.numero_dossier && <span className="list-card-meta">Dossier: {p.numero_dossier}</span>}
                </div>
                <span className="list-card-chevron">›</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
      {!loading && totalPages > 1 && (
        <div className="list-pagination">
          <button type="button" className="btn-primary" disabled={page <= 1} onClick={() => load(page - 1)}>
            Précédent
          </button>
          <span>Page {page} / {totalPages}</span>
          <button type="button" className="btn-primary" disabled={page >= totalPages} onClick={() => load(page + 1)}>
            Suivant
          </button>
        </div>
      )}
    </div>
  );
}
