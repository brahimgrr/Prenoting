import { useState } from "react";
import { Link, NavLink, Outlet, useNavigate } from "react-router-dom";
import { routeForRole, useAuth } from "../auth/AuthContext";

const NAV_ITEMS = {
  patient: [
    { to: "/patient", label: "Riepilogo", end: true },
    { to: "/patient/book", label: "Prenota visita" },
    { to: "/patient/appointments", label: "I miei appuntamenti" },
    { to: "/patient/profile", label: "Profilo" },
  ],
  doctor: [
    { to: "/doctor", label: "Oggi", end: true },
    { to: "/doctor/schedule", label: "Agenda" },
  ],
  staff: [
    { to: "/staff", label: "Operatività", end: true },
    { to: "/staff/appointments", label: "Appuntamenti" },
  ],
};

function navItemsForRole(role) {
  if (role === "patient") {
    return NAV_ITEMS.patient;
  }

  if (role === "doctor") {
    return NAV_ITEMS.doctor;
  }

  if (role === "staff") {
    return NAV_ITEMS.staff;
  }

  return [];
}

function UserSummary({ user }) {
  const displayName = [user?.first_name, user?.last_name].filter(Boolean).join(" ");
  const roleLabels = {
    patient: "Paziente",
    doctor: "Medico",
    staff: "Staff",
  };

  return (
    <div className="app-user">
      <span className="app-user__avatar">{(displayName || user?.username || "U").slice(0, 1).toUpperCase()}</span>
      <span className="app-user__meta">
        <span className="app-user__name">{displayName || user?.username}</span>
        <span className="app-user__role">{roleLabels[user?.role] ?? user?.role}</span>
      </span>
    </div>
  );
}

function Navigation({ items }) {
  return (
    <nav className="app-nav" aria-label="Navigazione del portale">
      {items.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          end={item.end}
          className={({ isActive }) => `app-nav__link${isActive ? " is-active" : ""}`}
        >
          {item.label}
        </NavLink>
      ))}
    </nav>
  );
}

export default function AppLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [logoutError, setLogoutError] = useState("");
  const navItems = navItemsForRole(user?.role);
  const homeRoute = routeForRole(user) ?? "/unsupported-role";

  async function handleLogout() {
    setLogoutError("");

    try {
      await logout();
      navigate("/login", { replace: true });
    } catch {
      setLogoutError("Non è stato possibile uscire. Riprova.");
    }
  }

  return (
    <div className="app-shell">
      <aside className="app-sidebar">
        <Link to={homeRoute} className="app-brand">
          <span className="app-brand__mark">M</span>
          <span>
            <span className="app-brand__name">MedPortal</span>
            <span className="app-brand__subline">Prenotazioni</span>
          </span>
        </Link>
        <Navigation items={navItems} />
        <div className="app-sidebar__footer">
          {logoutError && (
            <div className="alert alert-warning app-alert" role="alert">
              {logoutError}
            </div>
          )}
          <UserSummary user={user} />
          <button type="button" className="btn btn-outline-light w-100" onClick={handleLogout}>
            Esci
          </button>
        </div>
      </aside>

      <div className="app-main">
        <header className="app-topbar">
          <Link to={homeRoute} className="app-brand app-brand--mobile">
            <span className="app-brand__mark">M</span>
            <span className="app-brand__name">MedPortal</span>
          </Link>
          <button type="button" className="btn btn-sm btn-outline-secondary" onClick={handleLogout}>
            Esci
          </button>
        </header>
        {logoutError && (
          <div className="alert alert-warning app-top-alert" role="alert">
            {logoutError}
          </div>
        )}

        <div className="app-mobile-nav">
          <Navigation items={navItems} />
        </div>

        <main className="app-content">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
