import { BrowserRouter, Navigate, Route, Routes, useLocation } from "react-router-dom";
import { AuthProvider, routeForRole, useAuth } from "./auth/AuthContext";
import AppLayout from "./components/AppLayout";
import LoadingState from "./components/LoadingState";
import StatusBadge from "./components/StatusBadge";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";

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
    return <Navigate to={routeForRole(user)} replace />;
  }

  return children;
}

function PublicOnlyRoute({ children }) {
  const { user, loading } = useAuth();

  if (loading) {
    return <LoadingState label="Checking session" />;
  }

  if (user) {
    return <Navigate to={routeForRole(user)} replace />;
  }

  return children;
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
              path="/patient"
              element={
                <ProtectedRoute allowedRoles={["patient"]}>
                  <PlaceholderPage
                    eyebrow="Patient portal"
                    title="Patient dashboard"
                    description="Review upcoming appointments, recent activity, and booking shortcuts."
                  />
                </ProtectedRoute>
              }
            />
            <Route
              path="/patient/book"
              element={
                <ProtectedRoute allowedRoles={["patient"]}>
                  <PlaceholderPage
                    eyebrow="Booking"
                    title="Book appointment"
                    description="Search care services, doctors, and available visit times."
                  />
                </ProtectedRoute>
              }
            />
            <Route
              path="/patient/appointments"
              element={
                <ProtectedRoute allowedRoles={["patient"]}>
                  <PlaceholderPage
                    eyebrow="Appointments"
                    title="My appointments"
                    description="Track upcoming visits and manage confirmed appointments."
                  />
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
                  <PlaceholderPage
                    eyebrow="Doctor portal"
                    title="Today"
                    description="Review today's appointments and patient visit context."
                  />
                </ProtectedRoute>
              }
            />
            <Route
              path="/doctor/schedule"
              element={
                <ProtectedRoute allowedRoles={["doctor"]}>
                  <PlaceholderPage
                    eyebrow="Schedule"
                    title="Schedule"
                    description="Manage upcoming visits and appointment status updates."
                  />
                </ProtectedRoute>
              }
            />
            <Route
              path="/staff"
              element={
                <ProtectedRoute allowedRoles={["staff", "admin"]}>
                  <PlaceholderPage
                    eyebrow="Staff portal"
                    title="Daily operations"
                    description="Coordinate clinic schedules, appointment flow, and daily work queues."
                  />
                </ProtectedRoute>
              }
            />
            <Route
              path="/staff/appointments"
              element={
                <ProtectedRoute allowedRoles={["staff", "admin"]}>
                  <PlaceholderPage
                    eyebrow="Appointments"
                    title="Appointments"
                    description="Monitor appointment status across doctors, clinics, and services."
                  />
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
