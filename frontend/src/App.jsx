import { BrowserRouter, Navigate, Route, Routes, useLocation } from "react-router-dom";
import { AuthProvider, routeForRole, useAuth } from "./auth/AuthContext";
import AppLayout from "./components/AppLayout";
import LoadingState from "./components/LoadingState";
import StatusBadge from "./components/StatusBadge";
import BookingPage from "./pages/BookingPage";
import DoctorDashboard from "./pages/DoctorDashboard";
import LoginPage from "./pages/LoginPage";
import MyAppointmentsPage from "./pages/MyAppointmentsPage";
import PatientDashboard from "./pages/PatientDashboard";
import RegisterPage from "./pages/RegisterPage";
import StaffDashboard from "./pages/StaffDashboard";

function ProtectedRoute({ allowedRoles, children }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return <LoadingState label="Caricamento portale" />;
  }

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (allowedRoles && !allowedRoles.includes(user.role)) {
    return <Navigate to={routeForRole(user) ?? "/unsupported-role"} replace />;
  }

  return children;
}

function PublicOnlyRoute({ children }) {
  const { user, loading } = useAuth();

  if (loading) {
    return <LoadingState label="Verifica sessione" />;
  }

  if (user) {
    return <Navigate to={routeForRole(user) ?? "/unsupported-role"} replace />;
  }

  return children;
}

function UnsupportedRolePage() {
  const { user } = useAuth();

  return (
    <section className="portal-section">
      <div className="portal-page-heading">
        <span className="portal-eyebrow">Accesso non disponibile</span>
        <h1>Ruolo non supportato</h1>
        <p>
          Questo account è autenticato, ma il suo ruolo non può accedere al portale appuntamenti.
          Gli amministratori devono usare il pannello Django admin; contatta lo staff se serve accesso come paziente, medico o staff.
        </p>
      </div>
      <div className="alert alert-warning" role="alert">
        Ruolo attuale: <strong>{user?.role || "mancante"}</strong>
      </div>
    </section>
  );
}

function PlaceholderPage({ title, eyebrow, description, status = "Anteprima" }) {
  return (
    <section className="portal-section">
      <div className="portal-page-heading">
        <span className="portal-eyebrow">{eyebrow}</span>
        <div className="d-flex flex-wrap align-items-center gap-3">
          <h1>{title}</h1>
          <StatusBadge status="scheduled">{status}</StatusBadge>
        </div>
        <p>{description}</p>
      </div>
      <div className="portal-panel">
        <div>
          <h2>Prossimo passaggio</h2>
          <p>
            Questa sezione è pronta per i prossimi flussi di gestione appuntamenti.
          </p>
        </div>
      </div>
    </section>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/" element={<Navigate to="/login" replace />} />
          <Route
            path="/login"
            element={
              <PublicOnlyRoute>
                <LoginPage />
              </PublicOnlyRoute>
            }
          />
          <Route
            path="/register"
            element={
              <PublicOnlyRoute>
                <RegisterPage />
              </PublicOnlyRoute>
            }
          />

          <Route
            element={
              <ProtectedRoute>
                <AppLayout />
              </ProtectedRoute>
            }
          >
            <Route
              path="/unsupported-role"
              element={<UnsupportedRolePage />}
            />
            <Route
              path="/patient"
              element={
                <ProtectedRoute allowedRoles={["patient"]}>
                  <PatientDashboard />
                </ProtectedRoute>
              }
            />
            <Route
              path="/patient/book"
              element={
                <ProtectedRoute allowedRoles={["patient"]}>
                  <BookingPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/patient/appointments"
              element={
                <ProtectedRoute allowedRoles={["patient"]}>
                  <MyAppointmentsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/patient/profile"
              element={
                <ProtectedRoute allowedRoles={["patient"]}>
                  <PlaceholderPage
                    eyebrow="Profilo"
                    title="Profilo paziente"
                    description="Mantieni aggiornati i dati di contatto e le preferenze per gli appuntamenti."
                  />
                </ProtectedRoute>
              }
            />
            <Route
              path="/doctor"
              element={
                <ProtectedRoute allowedRoles={["doctor"]}>
                  <DoctorDashboard mode="today" />
                </ProtectedRoute>
              }
            />
            <Route
              path="/doctor/schedule"
              element={
                <ProtectedRoute allowedRoles={["doctor"]}>
                  <DoctorDashboard mode="schedule" />
                </ProtectedRoute>
              }
            />
            <Route
              path="/staff"
              element={
                <ProtectedRoute allowedRoles={["staff"]}>
                  <StaffDashboard mode="operations" />
                </ProtectedRoute>
              }
            />
            <Route
              path="/staff/appointments"
              element={
                <ProtectedRoute allowedRoles={["staff"]}>
                  <StaffDashboard mode="appointments" />
                </ProtectedRoute>
              }
            />
          </Route>

          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
