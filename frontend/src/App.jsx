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
    return <LoadingState label="Loading portal" />;
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
    return <LoadingState label="Checking session" />;
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
        <span className="portal-eyebrow">Access unavailable</span>
        <h1>Unsupported role</h1>
        <p>
          This account is authenticated, but its role cannot access the appointment portal.
          Contact clinic staff if this account needs patient, doctor, or staff access.
        </p>
      </div>
      <div className="alert alert-warning" role="alert">
        Current role: <strong>{user?.role || "missing"}</strong>
      </div>
    </section>
  );
}

function PlaceholderPage({ title, eyebrow, description, status = "Preview" }) {
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
          <h2>Next implementation step</h2>
          <p>
            This route is ready for the appointment workflows that will be added in the next frontend tasks.
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
                    eyebrow="Profile"
                    title="Patient profile"
                    description="Keep patient contact details and appointment preferences current."
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
