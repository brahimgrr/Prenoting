import { useEffect, useMemo, useState } from "react";
import api from "../api/client";
import LoadingState from "../components/LoadingState";
import StatusBadge from "../components/StatusBadge";

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "confirmed", label: "Confirmed" },
  { value: "checked_in", label: "Checked in" },
  { value: "completed", label: "Completed" },
  { value: "cancelled", label: "Cancelled" },
  { value: "no_show", label: "No show" },
];

const STAFF_STATUS_ACTIONS = {
  confirmed: [
    { status: "checked_in", label: "Checked in", className: "btn-outline-primary" },
    { status: "completed", label: "Completed", className: "btn-outline-success" },
    { status: "cancelled", label: "Cancelled", className: "btn-outline-secondary" },
    { status: "no_show", label: "No show", className: "btn-outline-danger" },
  ],
  checked_in: [
    { status: "completed", label: "Completed", className: "btn-outline-success" },
    { status: "cancelled", label: "Cancelled", className: "btn-outline-secondary" },
    { status: "no_show", label: "No show", className: "btn-outline-danger" },
  ],
};

function todayString() {
  const today = new Date();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");

  return `${today.getFullYear()}-${month}-${day}`;
}

function listFromResponse(data) {
  return Array.isArray(data) ? data : data?.results ?? [];
}

function apiMessage(error, fallback) {
  const data = error?.response?.data;
  if (typeof data?.detail === "string") {
    return data.detail;
  }
  if (data && typeof data === "object") {
    const firstValue = Object.values(data)[0];
    if (Array.isArray(firstValue)) {
      return firstValue.join(" ");
    }
    if (typeof firstValue === "string") {
      return firstValue;
    }
  }
  return fallback;
}

function compactParams(filters) {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => String(value).trim() !== ""),
  );
}

function patientLabel(appointment) {
  return appointment.patient_name || `Patient #${appointment.patient}`;
}

function formatDateTime(value) {
  if (!value) {
    return "Time pending";
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function sortByStartTime(first, second) {
  return new Date(first.start_at || 0) - new Date(second.start_at || 0);
}

function countStatus(appointments, status) {
  return appointments.filter((appointment) => String(appointment.status).toLowerCase() === status).length;
}

function StatusActions({ appointment, busyAction, onUpdate }) {
  const actions = STAFF_STATUS_ACTIONS[String(appointment.status).toLowerCase()] ?? [];

  if (actions.length === 0) {
    return <span className="dashboard-readonly">No actions</span>;
  }

  return (
    <div className="status-action-group status-action-group--dense" aria-label={`Update ${patientLabel(appointment)} status`}>
      {actions.map((action) => {
        const busy = busyAction === `${appointment.id}:${action.status}`;

        return (
          <button
            type="button"
            key={action.status}
            className={`btn btn-sm ${action.className}`}
            disabled={Boolean(busyAction)}
            onClick={() => onUpdate(appointment, action.status)}
          >
            {busy ? "Saving..." : action.label}
          </button>
        );
      })}
    </div>
  );
}

export default function StaffDashboard({ mode = "operations" }) {
  const [filters, setFilters] = useState({
    date: todayString(),
    clinic: "",
    doctor: "",
    service: "",
    status: "",
  });
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [actionMessage, setActionMessage] = useState("");
  const [busyAction, setBusyAction] = useState("");

  async function loadAppointments(nextFilters = filters) {
    setLoading(true);
    setError("");

    try {
      const response = await api.get("/appointments/staff/", {
        params: compactParams(nextFilters),
      });
      setAppointments(listFromResponse(response.data));
    } catch (appointmentError) {
      setError(apiMessage(appointmentError, "Appointments could not be loaded. Check filters and try again."));
      setAppointments([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadAppointments(filters);
  }, [filters]);

  const sortedAppointments = useMemo(
    () => [...appointments].sort(sortByStartTime),
    [appointments],
  );

  const counts = useMemo(
    () => ({
      total: sortedAppointments.length,
      confirmed: countStatus(sortedAppointments, "confirmed"),
      checkedIn: countStatus(sortedAppointments, "checked_in"),
      completed: countStatus(sortedAppointments, "completed"),
      cancelled: countStatus(sortedAppointments, "cancelled"),
    }),
    [sortedAppointments],
  );
  const isAppointmentsMode = mode === "appointments";

  function updateFilter(name, value) {
    setFilters((current) => ({ ...current, [name]: value }));
  }

  function clearFilters() {
    setFilters({
      date: todayString(),
      clinic: "",
      doctor: "",
      service: "",
      status: "",
    });
  }

  async function updateStatus(appointment, nextStatus) {
    setBusyAction(`${appointment.id}:${nextStatus}`);
    setError("");
    setActionMessage("");

    try {
      await api.post(`/appointments/staff/${appointment.id}/status/`, {
        status: nextStatus,
      });
      setActionMessage("Appointment status updated.");
      await loadAppointments(filters);
    } catch (statusError) {
      setError(apiMessage(statusError, "Appointment status could not be updated."));
    } finally {
      setBusyAction("");
    }
  }

  return (
    <section className="portal-section operations-dashboard operations-dashboard--wide">
      <div className="portal-page-heading">
        <span className="portal-eyebrow">Staff portal</span>
        <h1>{isAppointmentsMode ? "Appointments" : "Daily operations"}</h1>
        <p>
          {isAppointmentsMode
            ? "Search and manage appointment records across clinics, doctors, services, and statuses."
            : "Monitor today's operational flow and spot queues that need staff attention."}
        </p>
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}
      {actionMessage && (
        <div className="alert alert-success" role="status">
          {actionMessage}
        </div>
      )}

      <div className="dashboard-stat-row dashboard-stat-row--three">
        <section className="dashboard-stat">
          <span>Total</span>
          <strong>{counts.total}</strong>
          <small>displayed</small>
        </section>
        <section className="dashboard-stat">
          <span>Waiting</span>
          <strong>{counts.confirmed}</strong>
          <small>confirmed</small>
        </section>
        <section className="dashboard-stat">
          <span>Checked in</span>
          <strong>{counts.checkedIn}</strong>
          <small>in progress</small>
        </section>
        <section className="dashboard-stat">
          <span>Completed</span>
          <strong>{counts.completed}</strong>
          <small>{counts.cancelled} cancelled</small>
        </section>
      </div>

      {isAppointmentsMode ? (
        <section className="portal-panel dashboard-filter-panel">
          <div className="dashboard-filter-grid">
            <label className="form-label" htmlFor="staff-date">
              Date
              <input
                id="staff-date"
                className="form-control"
                type="date"
                value={filters.date}
                onChange={(event) => updateFilter("date", event.target.value)}
              />
            </label>
            <label className="form-label" htmlFor="staff-clinic">
              Clinic ID
              <input
                id="staff-clinic"
                className="form-control"
                inputMode="numeric"
                value={filters.clinic}
                onChange={(event) => updateFilter("clinic", event.target.value)}
                placeholder="Any"
              />
            </label>
            <label className="form-label" htmlFor="staff-doctor">
              Doctor ID
              <input
                id="staff-doctor"
                className="form-control"
                inputMode="numeric"
                value={filters.doctor}
                onChange={(event) => updateFilter("doctor", event.target.value)}
                placeholder="Any"
              />
            </label>
            <label className="form-label" htmlFor="staff-service">
              Service ID
              <input
                id="staff-service"
                className="form-control"
                inputMode="numeric"
                value={filters.service}
                onChange={(event) => updateFilter("service", event.target.value)}
                placeholder="Any"
              />
            </label>
            <label className="form-label" htmlFor="staff-status">
              Status
              <select
                id="staff-status"
                className="form-control"
                value={filters.status}
                onChange={(event) => updateFilter("status", event.target.value)}
              >
                {STATUS_OPTIONS.map((option) => (
                  <option key={option.value || "all"} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
            <div className="dashboard-filter-actions">
              <button type="button" className="btn btn-outline-secondary" onClick={clearFilters}>
                Reset
              </button>
            </div>
          </div>
        </section>
      ) : (
        <section className="portal-panel">
          <div className="section-heading">
            <h2>Operational snapshot</h2>
            <span>{filters.date}</span>
          </div>
          {loading ? (
            <LoadingState label="Loading daily operations" />
          ) : (
            <div className="operations-summary-grid">
              <div>
                <strong>{counts.confirmed}</strong>
                <span>patients expected</span>
              </div>
              <div>
                <strong>{counts.checkedIn}</strong>
                <span>currently checked in</span>
              </div>
              <div>
                <strong>{counts.completed}</strong>
                <span>visits completed</span>
              </div>
            </div>
          )}
        </section>
      )}

      {isAppointmentsMode && (
      <section className="portal-panel dashboard-table-panel">
        <div className="section-heading">
          <h2>Appointments</h2>
          <span>{sortedAppointments.length} total</span>
        </div>

        {loading ? (
          <LoadingState label="Loading appointments" />
        ) : sortedAppointments.length > 0 ? (
          <div className="table-responsive dashboard-table-wrap">
            <table className="table dashboard-table dashboard-table--dense align-middle">
              <thead>
                <tr>
                  <th scope="col">Time</th>
                  <th scope="col">Patient</th>
                  <th scope="col">Doctor</th>
                  <th scope="col">Service</th>
                  <th scope="col">Clinic</th>
                  <th scope="col">Status</th>
                  <th scope="col">Controls</th>
                </tr>
              </thead>
              <tbody>
                {sortedAppointments.map((appointment) => (
                  <tr key={appointment.id}>
                    <td className="dashboard-table__time">{formatDateTime(appointment.start_at)}</td>
                    <td>{patientLabel(appointment)}</td>
                    <td>{appointment.doctor_name || `Doctor #${appointment.doctor}`}</td>
                    <td>{appointment.service_name || `Service #${appointment.service}`}</td>
                    <td>{appointment.clinic_name || `Clinic #${appointment.clinic}`}</td>
                    <td>
                      <StatusBadge status={appointment.status} />
                    </td>
                    <td>
                      <StatusActions
                        appointment={appointment}
                        busyAction={busyAction}
                        onUpdate={updateStatus}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="empty-state">
            <h3>No appointments found</h3>
            <p>Adjust filters to view another work queue.</p>
          </div>
        )}
      </section>
      )}
    </section>
  );
}
