import { useEffect, useMemo, useState } from "react";
import api from "../api/client";
import LoadingState from "../components/LoadingState";
import StatusBadge from "../components/StatusBadge";

const DOCTOR_STATUS_ACTIONS = {
  confirmed: [
    { status: "checked_in", label: "Checked in", className: "btn-outline-primary" },
    { status: "no_show", label: "No show", className: "btn-outline-danger" },
  ],
  checked_in: [
    { status: "completed", label: "Completed", className: "btn-outline-success" },
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

function patientLabel(appointment) {
  return appointment.patient_name || `Patient #${appointment.patient}`;
}

function formatTime(value) {
  if (!value) {
    return "Time pending";
  }

  return new Intl.DateTimeFormat(undefined, {
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
    return <span className="dashboard-readonly">No actions</span>;
  }

  return (
    <div className="status-action-group" aria-label={`Update ${patientLabel(appointment)} status`}>
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

export default function DoctorDashboard() {
  const [date, setDate] = useState(todayString);
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [actionMessage, setActionMessage] = useState("");
  const [busyAction, setBusyAction] = useState("");

  async function loadSchedule(selectedDate = date) {
    setLoading(true);
    setError("");

    try {
      const response = await api.get("/appointments/doctor/schedule/", {
        params: { date: selectedDate },
      });
      setAppointments(listFromResponse(response.data));
    } catch (scheduleError) {
      setError(apiMessage(scheduleError, "Schedule could not be loaded. Please refresh and try again."));
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

  async function updateStatus(appointment, nextStatus) {
    setBusyAction(`${appointment.id}:${nextStatus}`);
    setError("");
    setActionMessage("");

    try {
      await api.post(`/appointments/doctor/${appointment.id}/status/`, {
        status: nextStatus,
      });
      setActionMessage("Appointment status updated.");
      await loadSchedule(date);
    } catch (statusError) {
      setError(apiMessage(statusError, "Appointment status could not be updated."));
    } finally {
      setBusyAction("");
    }
  }

  return (
    <section className="portal-section operations-dashboard">
      <div className="portal-page-heading portal-heading-row">
        <div>
          <span className="portal-eyebrow">Doctor portal</span>
          <h1>Today</h1>
          <p>Review the day by start time and update visit flow as patients move through care.</p>
        </div>
        <label className="dashboard-date-filter" htmlFor="doctor-schedule-date">
          <span>Date</span>
          <input
            id="doctor-schedule-date"
            className="form-control"
            type="date"
            value={date}
            onChange={(event) => setDate(event.target.value)}
          />
        </label>
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

      <div className="dashboard-stat-row">
        <section className="dashboard-stat">
          <span>Appointments</span>
          <strong>{sortedAppointments.length}</strong>
          <small>{date === todayString() ? "today" : date}</small>
        </section>
      </div>

      <section className="portal-panel dashboard-table-panel">
        <div className="section-heading">
          <h2>Schedule</h2>
          <span>{sortedAppointments.length} total</span>
        </div>

        {loading ? (
          <LoadingState label="Loading doctor schedule" />
        ) : sortedAppointments.length > 0 ? (
          <div className="table-responsive dashboard-table-wrap">
            <table className="table dashboard-table align-middle">
              <thead>
                <tr>
                  <th scope="col">Time</th>
                  <th scope="col">Patient</th>
                  <th scope="col">Service</th>
                  <th scope="col">Clinic</th>
                  <th scope="col">Status</th>
                  <th scope="col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {sortedAppointments.map((appointment) => (
                  <tr key={appointment.id}>
                    <td className="dashboard-table__time">{formatTime(appointment.start_at)}</td>
                    <td>{patientLabel(appointment)}</td>
                    <td>{appointment.service_name || "Appointment"}</td>
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
            <h3>No appointments</h3>
            <p>No visits are scheduled for this date.</p>
          </div>
        )}
      </section>
    </section>
  );
}
