import { useEffect, useMemo, useState } from "react";
import api from "../api/client";
import LoadingState from "../components/LoadingState";
import StatusBadge from "../components/StatusBadge";

const UPCOMING_STATUSES = new Set(["confirmed", "checked_in"]);

function listFromResponse(data) {
  return Array.isArray(data) ? data : data?.results ?? [];
}

function isUpcoming(appointment) {
  return UPCOMING_STATUSES.has(String(appointment.status).toLowerCase()) && new Date(appointment.start_at) > new Date();
}

function canManageAppointment(appointment) {
  return String(appointment.status).toLowerCase() === "confirmed" && new Date(appointment.start_at) > new Date();
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

function formatTimeRange(slot) {
  const start = slot.start_at ? new Date(slot.start_at) : null;
  const end = slot.end_at ? new Date(slot.end_at) : null;
  const formatter = new Intl.DateTimeFormat(undefined, {
    hour: "numeric",
    minute: "2-digit",
  });

  if (!start) {
    return "Time pending";
  }

  return end ? `${formatter.format(start)} - ${formatter.format(end)}` : formatter.format(start);
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

function AppointmentDetails({ appointment }) {
  return (
    <dl className="appointment-details">
      <div>
        <dt>Service</dt>
        <dd>{appointment.service_name || "Appointment"}</dd>
      </div>
      <div>
        <dt>Doctor</dt>
        <dd>{appointment.doctor_name || "Doctor pending"}</dd>
      </div>
      <div>
        <dt>Clinic</dt>
        <dd>{appointment.clinic_name || "Clinic pending"}</dd>
      </div>
      <div>
        <dt>Time</dt>
        <dd>{formatDateTime(appointment.start_at)}</dd>
      </div>
    </dl>
  );
}

export default function MyAppointmentsPage() {
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [actionMessage, setActionMessage] = useState("");
  const [cancelAppointment, setCancelAppointment] = useState(null);
  const [cancelReason, setCancelReason] = useState("");
  const [canceling, setCanceling] = useState(false);
  const [rescheduleAppointment, setRescheduleAppointment] = useState(null);
  const [rescheduleDate, setRescheduleDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [rescheduleSlots, setRescheduleSlots] = useState([]);
  const [selectedRescheduleSlot, setSelectedRescheduleSlot] = useState(null);
  const [loadingRescheduleSlots, setLoadingRescheduleSlots] = useState(false);
  const [rescheduling, setRescheduling] = useState(false);

  async function loadAppointments() {
    setLoading(true);
    setError("");

    try {
      const response = await api.get("/appointments/");
      setAppointments(listFromResponse(response.data));
    } catch {
      setError("Appointments could not be loaded. Please refresh and try again.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadAppointments();
  }, []);

  useEffect(() => {
    if (!rescheduleAppointment) {
      return;
    }

    let active = true;

    async function loadAvailability() {
      setLoadingRescheduleSlots(true);
      setSelectedRescheduleSlot(null);
      setError("");

      try {
        const response = await api.get("/availability/", {
          params: {
            service: rescheduleAppointment.service,
            date: rescheduleDate,
          },
        });
        if (active) {
          setRescheduleSlots(listFromResponse(response.data));
        }
      } catch {
        if (active) {
          setError("Reschedule availability could not be loaded.");
          setRescheduleSlots([]);
        }
      } finally {
        if (active) {
          setLoadingRescheduleSlots(false);
        }
      }
    }

    loadAvailability();

    return () => {
      active = false;
    };
  }, [rescheduleAppointment, rescheduleDate]);

  const grouped = useMemo(() => {
    const upcoming = appointments
      .filter(isUpcoming)
      .sort((first, second) => new Date(first.start_at) - new Date(second.start_at));
    const past = appointments
      .filter((appointment) => !isUpcoming(appointment))
      .sort((first, second) => new Date(second.start_at) - new Date(first.start_at));

    return { upcoming, past };
  }, [appointments]);

  async function submitCancel(event) {
    event.preventDefault();
    setCanceling(true);
    setError("");
    setActionMessage("");

    try {
      await api.post(`/appointments/${cancelAppointment.id}/cancel/`, {
        cancellation_reason: cancelReason,
      });
      setActionMessage("Appointment cancelled.");
      setCancelAppointment(null);
      setCancelReason("");
      await loadAppointments();
    } catch (cancelError) {
      setError(apiMessage(cancelError, "Appointment could not be cancelled."));
    } finally {
      setCanceling(false);
    }
  }

  async function submitReschedule(event) {
    event.preventDefault();
    setRescheduling(true);
    setError("");
    setActionMessage("");

    try {
      await api.post(`/appointments/${rescheduleAppointment.id}/reschedule/`, {
        slot: selectedRescheduleSlot.id,
      });
      setActionMessage("Appointment rescheduled.");
      setRescheduleAppointment(null);
      setSelectedRescheduleSlot(null);
      await loadAppointments();
    } catch (rescheduleError) {
      setError(apiMessage(rescheduleError, "Appointment could not be rescheduled."));
    } finally {
      setRescheduling(false);
    }
  }

  if (loading) {
    return <LoadingState label="Loading appointments" />;
  }

  return (
    <section className="portal-section">
      <div className="portal-page-heading">
        <span className="portal-eyebrow">Appointments</span>
        <h1>My appointments</h1>
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

      <section className="appointment-group">
        <div className="section-heading">
          <h2>Upcoming</h2>
          <span>{grouped.upcoming.length}</span>
        </div>
        {grouped.upcoming.length > 0 ? (
          <div className="appointment-list">
            {grouped.upcoming.map((appointment) => (
              <article className="appointment-card" key={appointment.id}>
                <div className="appointment-card__header">
                  <div>
                    <h3>{appointment.service_name || "Appointment"}</h3>
                    <p>{formatDateTime(appointment.start_at)}</p>
                  </div>
                  <StatusBadge status={appointment.status} />
                </div>
                <AppointmentDetails appointment={appointment} />
                {canManageAppointment(appointment) ? (
                  <div className="appointment-actions">
                    <button
                      type="button"
                      className="btn btn-outline-secondary"
                      onClick={() => setRescheduleAppointment(appointment)}
                    >
                      Reschedule
                    </button>
                    <button
                      type="button"
                      className="btn btn-outline-danger"
                      onClick={() => setCancelAppointment(appointment)}
                    >
                      Cancel
                    </button>
                  </div>
                ) : (
                  <div className="appointment-actions appointment-actions--readonly">
                    <span className="text-secondary fw-medium">Details only</span>
                  </div>
                )}
              </article>
            ))}
          </div>
        ) : (
          <div className="portal-panel empty-state">
            <h3>No upcoming appointments</h3>
            <p>Confirmed future visits will appear here.</p>
          </div>
        )}
      </section>

      <section className="appointment-group">
        <div className="section-heading">
          <h2>Past and cancelled</h2>
          <span>{grouped.past.length}</span>
        </div>
        {grouped.past.length > 0 ? (
          <div className="appointment-list">
            {grouped.past.map((appointment) => (
              <article className="appointment-card appointment-card--muted" key={appointment.id}>
                <div className="appointment-card__header">
                  <div>
                    <h3>{appointment.service_name || "Appointment"}</h3>
                    <p>{formatDateTime(appointment.start_at)}</p>
                  </div>
                  <StatusBadge status={appointment.status} />
                </div>
                <AppointmentDetails appointment={appointment} />
              </article>
            ))}
          </div>
        ) : (
          <div className="portal-panel empty-state">
            <h3>No appointment history</h3>
            <p>Past visits and cancelled appointments will appear here.</p>
          </div>
        )}
      </section>

      {cancelAppointment && (
        <div className="modal-backdrop-custom" role="presentation">
          <div className="modal-dialog modal-dialog-centered" role="dialog" aria-modal="true" aria-labelledby="cancel-title">
            <form className="modal-content" onSubmit={submitCancel}>
              <div className="modal-header">
                <h2 className="modal-title fs-5" id="cancel-title">
                  Cancel appointment
                </h2>
                <button type="button" className="btn-close" aria-label="Close" onClick={() => setCancelAppointment(null)} />
              </div>
              <div className="modal-body">
                <p className="text-secondary mb-3">{cancelAppointment.service_name} at {formatDateTime(cancelAppointment.start_at)}</p>
                <label className="form-label" htmlFor="cancel-reason">
                  Reason
                </label>
                <textarea
                  id="cancel-reason"
                  className="form-control"
                  rows="3"
                  value={cancelReason}
                  onChange={(event) => setCancelReason(event.target.value)}
                />
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-outline-secondary" onClick={() => setCancelAppointment(null)}>
                  Keep appointment
                </button>
                <button type="submit" className="btn btn-danger" disabled={canceling}>
                  {canceling ? "Cancelling..." : "Cancel appointment"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {rescheduleAppointment && (
        <div className="reschedule-panel portal-panel">
          <div className="section-heading">
            <h2>Reschedule</h2>
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setRescheduleAppointment(null)}>
              Close
            </button>
          </div>
          <form onSubmit={submitReschedule}>
            <label className="form-label" htmlFor="reschedule-date">
              Date
            </label>
            <input
              id="reschedule-date"
              className="form-control reschedule-date"
              type="date"
              value={rescheduleDate}
              onChange={(event) => setRescheduleDate(event.target.value)}
            />
            {loadingRescheduleSlots ? (
              <LoadingState label="Loading new times" />
            ) : (
              <div className="slot-list mt-3">
                {rescheduleSlots.map((slot) => (
                  <button
                    type="button"
                    key={slot.id}
                    className={`slot-chip${selectedRescheduleSlot?.id === slot.id ? " is-selected" : ""}`}
                    onClick={() => setSelectedRescheduleSlot(slot)}
                  >
                    <span>{formatTimeRange(slot)}</span>
                    <small>{slot.doctor_name || "Doctor"} / {slot.clinic_name || `Clinic ${slot.clinic}`}</small>
                  </button>
                ))}
                {rescheduleSlots.length === 0 && (
                  <div className="empty-state">
                    <h3>No slots available</h3>
                    <p>Choose another date.</p>
                  </div>
                )}
              </div>
            )}
            <button type="submit" className="btn btn-primary mt-3" disabled={!selectedRescheduleSlot || rescheduling}>
              {rescheduling ? "Rescheduling..." : "Save new time"}
            </button>
          </form>
        </div>
      )}
    </section>
  );
}
