import { Link, NavLink, Outlet, useNavigate } from "react-router-dom";
import { routeForRole, useAuth } from "../auth/AuthContext";

const NAV_ITEMS = {
  patient: [
    { to: "/patient", label: "Dashboard", end: true },
    { to: "/patient/book", label: "Book appointment" },
    { to: "/patient/appointments", label: "My appointments" },
    { to: "/patient/profile", label: "Profile" },
  ],
  doctor: [
    { to: "/doctor", label: "Today", end: true },
    { to: "/doctor/schedule", label: "Schedule" },
  ],
  staff: [
    { to: "/staff", label: "Daily operations", end: true },
    { to: "/staff/appointments", label: "Appointments" },
  ],
};

function navItemsForRole(role) {
  if (role === "doctor") {
    return NAV_ITEMS.doctor;
  }

  if (role === "staff" || role === "admin") {
    return NAV_ITEMS.staff;
  }

  return NAV_ITEMS.patient;
}

function UserSummary({ user }) {
  const displayName = [user?.first_name, user?.last_name].filter(Boolean).join(" ");

  return (
    <div className="app-user">
      <span className="app-user__avatar">{(displayName || user?.username || "U").slice(0, 1).toUpperCase()}</span>
      <span className="app-user__meta">
        <span className="app-user__name">{displayName || user?.username}</span>
        <span className="app-user__role">{user?.role}</span>
      </span>
    </div>
  );
}

function Navigation({ items }) {
  return (
    <nav className="app-nav" aria-label="Portal navigation">
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
  const navItems = navItemsForRole(user?.role);

  async function handleLogout() {
    await logout();
    navigate("/login", { replace: true });
  }

  return (
    <div className="app-shell">
      <aside className="app-sidebar">
        <Link to={routeForRole(user)} className="app-brand">
          <span className="app-brand__mark">M</span>
          <span>
            <span className="app-brand__name">MedPortal</span>
            <span className="app-brand__subline">Appointments</span>
          </span>
        </Link>
        <Navigation items={navItems} />
        <div className="app-sidebar__footer">
          <UserSummary user={user} />
          <button type="button" className="btn btn-outline-light w-100" onClick={handleLogout}>
            Logout
          </button>
        </div>
      </aside>

      <div className="app-main">
        <header className="app-topbar">
          <Link to={routeForRole(user)} className="app-brand app-brand--mobile">
            <span className="app-brand__mark">M</span>
            <span className="app-brand__name">MedPortal</span>
          </Link>
          <button type="button" className="btn btn-sm btn-outline-secondary" onClick={handleLogout}>
            Logout
          </button>
        </header>

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
