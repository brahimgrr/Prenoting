import { useEffect, useMemo, useState } from "react";
import api from "../api/client";
import LoadingState from "../components/LoadingState";
import StatusBadge from "../components/StatusBadge";

const DOCTOR_STATUS_ACTIONS = {
  confirmed: [
    { status: "checked_in", label: "Accetta", className: "btn-outline-primary" },
    { status: "no_show", label: "Assente", className: "btn-outline-danger" },
  ],
  checked_in: [
    { status: "completed", label: "Completa", className: "btn-outline-success" },
  ],
};

function todayString() {
  const today = new Date();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");

  return `${today.getFullYear()}-${month}-${day}`;
}

const initialAvailabilityForm = {
  date: todayString(),
  startTime: "09:00",
  endTime: "09:30",
  clinic: "",
};

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

function patientLabel(appointment) {
  return appointment.patient_name || `Paziente #${appointment.patient}`;
}

function formatTime(value) {
  if (!value) {
    return "Orario in attesa";
  }

  return new Intl.DateTimeFormat("it-IT", {
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(value));
}

function sortByStartTime(first, second) {
  return new Date(first.start_at || 0) - new Date(second.start_at || 0);
}

function StatusActions({ appointment, busyAction, onUpdate }) {
  const actions = DOCTOR_STATUS_ACTIONS[String(appointment.status).toLowerCase()] ?? [];

  if (actions.length === 0) {
    return <span className="dashboard-readonly">Nessuna azione</span>;
  }

  return (
    <div className="status-action-group" aria-label={`Aggiorna stato di ${patientLabel(appointment)}`}>
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

export default function DoctorDashboard({ mode = "today" }) {
  const [date, setDate] = useState(todayString);
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [actionMessage, setActionMessage] = useState("");
  const [busyAction, setBusyAction] = useState("");
  const [availabilityForm, setAvailabilityForm] = useState(initialAvailabilityForm);
  const [savingAvailability, setSavingAvailability] = useState(false);

  async function loadSchedule(selectedDate = date) {
    setLoading(true);
    setError("");

    try {
      const response = await api.get("/appointments/doctor/schedule/", {
        params: { date: selectedDate },
      });
      setAppointments(listFromResponse(response.data));
    } catch (scheduleError) {
      setError(apiMessage(scheduleError, "Impossibile caricare l'agenda. Aggiorna la pagina e riprova."));
      setAppointments([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadSchedule(date);
  }, [date]);

  const sortedAppointments = useMemo(
    () => [...appointments].sort(sortByStartTime),
    [appointments],
  );
  const confirmedCount = sortedAppointments.filter(
    (appointment) => appointment.status === "confirmed",
  ).length;
  const inProgressCount = sortedAppointments.filter(
    (appointment) => appointment.status === "checked_in",
  ).length;
  const completedCount = sortedAppointments.filter(
    (appointment) => appointment.status === "completed",
  ).length;
  const visibleAppointments = mode === "today" ? sortedAppointments.slice(0, 4) : sortedAppointments;
  const isScheduleMode = mode === "schedule";

  async function updateStatus(appointment, nextStatus) {
    setBusyAction(`${appointment.id}:${nextStatus}`);
    setError("");
    setActionMessage("");

    try {
      await api.post(`/appointments/doctor/${appointment.id}/status/`, {
        status: nextStatus,
      });
      setActionMessage("Stato appuntamento aggiornato.");
      await loadSchedule(date);
    } catch (statusError) {
      setError(apiMessage(statusError, "Impossibile aggiornare lo stato dell'appuntamento."));
    } finally {
      setBusyAction("");
    }
  }

  function updateAvailabilityField(name, value) {
    setAvailabilityForm((current) => ({ ...current, [name]: value }));
  }

  async function createAvailability(event) {
    event.preventDefault();
    setSavingAvailability(true);
    setError("");
    setActionMessage("");

    try {
      await api.post("/availability/doctor/", {
        clinic: availabilityForm.clinic,
        start_at: `${availabilityForm.date}T${availabilityForm.startTime}:00`,
        end_at: `${availabilityForm.date}T${availabilityForm.endTime}:00`,
      });
      setActionMessage("Disponibilità aggiunta.");
      setAvailabilityForm((current) => ({
        ...initialAvailabilityForm,
        date: current.date,
        clinic: current.clinic,
      }));
    } catch (availabilityError) {
      setError(apiMessage(availabilityError, "Impossibile aggiungere la disponibilità."));
    } finally {
      setSavingAvailability(false);
    }
  }

  return (
    <section className="portal-section operations-dashboard">
      <div className="portal-page-heading portal-heading-row">
        <div>
          <span className="portal-eyebrow">Portale medico</span>
          <h1>{isScheduleMode ? "Agenda" : "Oggi"}</h1>
          <p>
            {isScheduleMode
              ? "Consulta gli appuntamenti di una data e aggiorna lo stato delle visite."
              : "Segui il flusso delle visite di oggi e concentrati sui prossimi pazienti."}
          </p>
        </div>
        {isScheduleMode && (
          <label className="dashboard-date-filter" htmlFor="doctor-schedule-date">
            <span>Data</span>
            <input
              id="doctor-schedule-date"
              className="form-control"
              type="date"
              value={date}
              onChange={(event) => setDate(event.target.value)}
            />
          </label>
        )}
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
          <span>Appuntamenti</span>
          <strong>{sortedAppointments.length}</strong>
          <small>{date === todayString() ? "oggi" : date}</small>
        </section>
        <section className="dashboard-stat">
          <span>In attesa</span>
          <strong>{confirmedCount}</strong>
          <small>confermati</small>
        </section>
        <section className="dashboard-stat">
          <span>Completati</span>
          <strong>{completedCount}</strong>
          <small>{inProgressCount} in corso</small>
        </section>
      </div>

      {isScheduleMode && (
        <section className="portal-panel dashboard-filter-panel">
          <div className="section-heading">
            <h2>Nuova disponibilità</h2>
            <span>Uno slot alla volta</span>
          </div>
          <form className="dashboard-filter-grid" onSubmit={createAvailability}>
            <label className="form-label" htmlFor="availability-date">
              Data
              <input
                id="availability-date"
                className="form-control"
                type="date"
                value={availabilityForm.date}
                onChange={(event) => updateAvailabilityField("date", event.target.value)}
                required
              />
            </label>
            <label className="form-label" htmlFor="availability-start">
              Ora inizio
              <input
                id="availability-start"
                className="form-control"
                type="time"
                value={availabilityForm.startTime}
                onChange={(event) => updateAvailabilityField("startTime", event.target.value)}
                required
              />
            </label>
            <label className="form-label" htmlFor="availability-end">
              Ora fine
              <input
                id="availability-end"
                className="form-control"
                type="time"
                value={availabilityForm.endTime}
                onChange={(event) => updateAvailabilityField("endTime", event.target.value)}
                required
              />
            </label>
            <label className="form-label" htmlFor="availability-clinic">
              ID ambulatorio
              <input
                id="availability-clinic"
                className="form-control"
                inputMode="numeric"
                value={availabilityForm.clinic}
                onChange={(event) => updateAvailabilityField("clinic", event.target.value.replace(/\D/g, ""))}
                placeholder="Es. 1"
                required
              />
            </label>
            <div className="dashboard-filter-actions">
              <button type="submit" className="btn btn-primary" disabled={savingAvailability}>
                {savingAvailability ? "Salvataggio..." : "Aggiungi"}
              </button>
            </div>
          </form>
        </section>
      )}

      <section className="portal-panel dashboard-table-panel">
        <div className="section-heading">
          <h2>{isScheduleMode ? "Agenda completa" : "Prossimi appuntamenti"}</h2>
          <span>{isScheduleMode ? sortedAppointments.length : visibleAppointments.length} mostrati</span>
        </div>

        {loading ? (
          <LoadingState label="Caricamento agenda medico" />
        ) : visibleAppointments.length > 0 ? (
          <div className="table-responsive dashboard-table-wrap">
            <table className="table dashboard-table align-middle">
              <thead>
                <tr>
                  <th scope="col">Orario</th>
                  <th scope="col">Paziente</th>
                  <th scope="col">Prestazione</th>
                  <th scope="col">Ambulatorio</th>
                  <th scope="col">Stato</th>
                  <th scope="col">Azioni</th>
                </tr>
              </thead>
              <tbody>
                {visibleAppointments.map((appointment) => (
                  <tr key={appointment.id}>
                    <td className="dashboard-table__time">{formatTime(appointment.start_at)}</td>
                    <td>{patientLabel(appointment)}</td>
                    <td>{appointment.service_name || "Appuntamento"}</td>
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
            <h3>Nessun appuntamento</h3>
            <p>Nessuna visita programmata per questa data.</p>
          </div>
        )}
      </section>
    </section>
  );
}
