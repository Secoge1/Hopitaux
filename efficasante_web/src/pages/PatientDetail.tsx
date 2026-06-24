import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../services/api';
import { API_BASE } from '../config';
import '../styles/DetailPage.css';

type PatientData = Record<string, unknown>;

export default function PatientDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [data, setData] = useState<PatientData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    api
      .getPatients({ id: Number(id) })
      .then((res: { success?: boolean; data?: PatientData }) => {
        if (res.success && res.data) setData(res.data as PatientData);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div className="detail-loading">Chargement…</div>;
  if (error || !data) {
    return (
      <div className="detail-error">
        <p>{error || 'Patient non trouvé'}</p>
        <button type="button" className="btn-primary" onClick={() => navigate('/patients')}>
          Retour
        </button>
      </div>
    );
  }

  const name = [data.prenom, data.nom].filter(Boolean).join(' ');
  const initial = (name || '?').charAt(0).toUpperCase();
  const sections: { title: string; entries: [string, unknown][] }[] = [
    {
      title: 'Informations',
      entries: [
        ['Date de naissance', data.date_naissance],
        ['Sexe', data.sexe],
        ['Téléphone', data.telephone],
        ['Email', data.email],
        ['Adresse', data.adresse],
        ['Ville', data.ville],
      ].filter(([, v]) => v != null && String(v).trim() !== ''),
    },
  ];
  if (data.antecedents_medicaux) {
    sections.push({ title: 'Antécédents médicaux', entries: [['', data.antecedents_medicaux]] });
  }
  if (data.allergies) {
    sections.push({ title: 'Allergies', entries: [['', data.allergies]] });
  }

  return (
    <div className="detail-page animate-in">
      <div className="detail-actions-row">
        <button type="button" className="back-link" onClick={() => navigate('/patients')}>
          ← Patients
        </button>
        <div className="detail-action-buttons">
          <a
            href={`${API_BASE}/patients/modifier.php?id=${id}`}
            target="_blank"
            rel="noopener noreferrer"
            className="btn-primary btn-sm"
          >
            Modifier
          </a>
          <a
            href={`${API_BASE}/patients/voir.php?id=${id}`}
            target="_blank"
            rel="noopener noreferrer"
            className="btn-secondary btn-sm"
          >
            Voir (complet)
          </a>
          <a
            href={`${API_BASE}/patients/dossier_medical.php?id=${id}`}
            target="_blank"
            rel="noopener noreferrer"
            className="btn-secondary btn-sm"
          >
            Dossier médical
          </a>
        </div>
      </div>
      <div className="detail-header card elevated">
        <span className="detail-avatar">{initial}</span>
        <h1>{name || 'Patient'}</h1>
        {data.numero_dossier && <p className="detail-meta">Dossier: {String(data.numero_dossier)}</p>}
        {data.age != null && <p className="detail-meta">{data.age} ans</p>}
      </div>
      {sections.map(({ title, entries }) => (
        <section key={title} className="detail-section card">
          <h2>{title}</h2>
          {entries.map(([label, value]) => (
            <div key={label || title} className="detail-row">
              {label && <span className="detail-label">{label}</span>}
              <span className="detail-value">{String(value)}</span>
            </div>
          ))}
        </section>
      ))}
    </div>
  );
}
