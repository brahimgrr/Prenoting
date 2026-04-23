import { useEffect, useMemo, useState } from "react";
import api from "../api/client";
import LoadingState from "../components/LoadingState";
import StatusBadge from "../components/StatusBadge";

const STATUS_OPTIONS = [
  { value: "", label: "Tutti gli stati" },
  { value: "confirmed", label: "Confermato" },
  { value: "checked_in", label: "Accettato" },
  { value: "completed", label: "Completato" },
  { value: "cancelled", label: "Annullato" },
  { value: "no_show", label: "Assente" },
];

const STAFF_STATUS_ACTIONS = {
  confirmed: [
    { status: "checked_in", label: "Accetta", className: "btn-outline-primary" },
    { status: "completed", label: "Completa", className: "btn-outline-success" },
    { status: "cancelled", label: "Annulla", className: "btn-outline-secondary" },
    { status: "no_show", label: "Assente", className: "btn-outline-danger" },
  ],
  checked_in: [
    { status: "completed", label: "Completa", className: "btn-outline-success" },
    { status: "cancelled", label: "Annulla", className: "btn-outline-secondary" },
    { status: "no_show", label: "Assente", className: "btn-outline-danger" },
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
  return appointment.patient_name || `Paziente #${appointment.patient}`;
}

function formatDateTime(value) {
  if (!value) {
    return "Orario in attesa";
  }

  return new Intl.DateTimeFormat("it-IT", {
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
    return <span className="dashboard-readonly">Nessuna azione</span>;
  }

  return (
    <div className="status-action-group status-action-group--dense" aria-label={`Aggiorna stato di ${patientLabel(appointment)}`}>
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
            {busy ? "Salvataggio..." : action.label}
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
      setError(apiMessage(appointmentError, "Impossibile caricare gli appuntamenti. Controlla i filtri e riprova."));
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
      setActionMessage("Stato appuntamento aggiornato.");
      await loadAppointments(filters);
    } catch (statusError) {
      setError(apiMessage(statusError, "Impossibile aggiornare lo stato dell'appuntamento."));
    } finally {
      setBusyAction("");
    }
  }

  return (
    <section className="portal-section operations-dashboard operations-dashboard--wide">
      <div className="portal-page-heading">
        <span className="portal-eyebrow">Portale staff</span>
        <h1>{isAppointmentsMode ? "Appuntamenti" : "Operatività giornaliera"}</h1>
        <p>
          {isAppointmentsMode
            ? "Cerca e gestisci gli appuntamenti per ambulatori, medici, prestazioni e stati."
            : "Monitora il flusso operativo di oggi e individua le code che richiedono attenzione."}
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
          <span>Totale</span>
          <strong>{counts.total}</strong>
          <small>visualizzati</small>
        </section>
        <section className="dashboard-stat">
          <span>In attesa</span>
          <strong>{counts.confirmed}</strong>
          <small>confermati</small>
        </section>
        <section className="dashboard-stat">
          <span>Accettati</span>
          <strong>{counts.checkedIn}</strong>
          <small>in corso</small>
        </section>
        <section className="dashboard-stat">
          <span>Completati</span>
          <strong>{counts.completed}</strong>
          <small>{counts.cancelled} annullati</small>
        </section>
      </div>

      {isAppointmentsMode ? (
        <section className="portal-panel dashboard-filter-panel">
          <div className="dashboard-filter-grid">
            <label className="form-label" htmlFor="staff-date">
              Data
              <input
                id="staff-date"
                className="form-control"
                type="date"
                value={filters.date}
                onChange={(event) => updateFilter("date", event.target.value)}
              />
            </label>
            <label className="form-label" htmlFor="staff-clinic">
              ID ambulatorio
              <input
                id="staff-clinic"
                className="form-control"
                inputMode="numeric"
                value={filters.clinic}
                onChange={(event) => updateFilter("clinic", event.target.value)}
                placeholder="Qualsiasi"
              />
            </label>
            <label className="form-label" htmlFor="staff-doctor">
              ID medico
              <input
                id="staff-doctor"
                className="form-control"
                inputMode="numeric"
                value={filters.doctor}
                onChange={(event) => updateFilter("doctor", event.target.value)}
                placeholder="Qualsiasi"
              />
            </label>
            <label className="form-label" htmlFor="staff-service">
              ID prestazione
              <input
                id="staff-service"
                className="form-control"
                inputMode="numeric"
                value={filters.service}
                onChange={(event) => updateFilter("service", event.target.value)}
                placeholder="Qualsiasi"
              />
            </label>
            <label className="form-label" htmlFor="staff-status">
              Stato
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
                Reimposta
              </button>
            </div>
          </div>
        </section>
      ) : (
        <section className="portal-panel">
          <div className="section-heading">
            <h2>Riepilogo operativo</h2>
            <span>{filters.date}</span>
          </div>
          {loading ? (
            <LoadingState label="Caricamento operatività giornaliera" />
          ) : (
            <div className="operations-summary-grid">
              <div>
                <strong>{counts.confirmed}</strong>
                <span>pazienti attesi</span>
              </div>
              <div>
                <strong>{counts.checkedIn}</strong>
                <span>attualmente accettati</span>
              </div>
              <div>
                <strong>{counts.completed}</strong>
                <span>visite completate</span>
              </div>
            </div>
          )}
        </section>
      )}

      {isAppointmentsMode && (
      <section className="portal-panel dashboard-table-panel">
        <div className="section-heading">
          <h2>Appuntamenti</h2>
          <span>{sortedAppointments.length} totali</span>
        </div>

        {loading ? (
          <LoadingState label="Caricamento appuntamenti" />
        ) : sortedAppointments.length > 0 ? (
          <div className="table-responsive dashboard-table-wrap">
            <table className="table dashboard-table dashboard-table--dense align-middle">
              <thead>
                <tr>
                  <th scope="col">Orario</th>
                  <th scope="col">Paziente</th>
                  <th scope="col">Medico</th>
                  <th scope="col">Prestazione</th>
                  <th scope="col">Ambulatorio</th>
                  <th scope="col">Stato</th>
                  <th scope="col">Controlli</th>
                </tr>
              </thead>
              <tbody>
                {sortedAppointments.map((appointment) => (
                  <tr key={appointment.id}>
                    <td className="dashboard-table__time">{formatDateTime(appointment.start_at)}</td>
                    <td>{patientLabel(appointment)}</td>
                    <td>{appointment.doctor_name || `Medico #${appointment.doctor}`}</td>
                    <td>{appointment.service_name || `Prestazione #${appointment.service}`}</td>
                    <td>{appointment.clinic_name || `Ambulatorio #${appointment.clinic}`}</td>
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
            <h3>Nessun appuntamento trovato</h3>
            <p>Modifica i filtri per visualizzare un'altra coda di lavoro.</p>
          </div>
        )}
      </section>
      )}
    </section>
  );
}
