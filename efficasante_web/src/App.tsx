import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './contexts/AuthContext';
import AdaptiveShell from './components/layout/AdaptiveShell';
import OfflineBanner from './components/OfflineBanner';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Patients from './pages/Patients';
import PatientDetail from './pages/PatientDetail';
import RendezVous from './pages/RendezVous';
import Consultations from './pages/Consultations';
import Laboratoire from './pages/Laboratoire';

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isLoggedIn, loading } = useAuth();
  if (loading) return <div className="loading-screen">Chargement…</div>;
  if (!isLoggedIn) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

export default function App() {
  return (
    <>
      <OfflineBanner />
      <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <AdaptiveShell />
          </ProtectedRoute>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="patients" element={<Patients />} />
        <Route path="patients/:id" element={<PatientDetail />} />
        <Route path="rendez-vous" element={<RendezVous />} />
        <Route path="consultations" element={<Consultations />} />
        <Route path="laboratoire" element={<Laboratoire />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
    </>
  );
}
