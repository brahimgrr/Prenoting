import { Link } from "react-router-dom";
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

function formatDateTime(value) {
  if (!value) {
    return "Time pending";
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function AppointmentRow({ appointment }) {
  return (
    <article className="appointment-row">
      <div>
        <h3>{appointment.service_name || "Appointment"}</h3>
        <p>{formatDateTime(appointment.start_at)}</p>
      </div>
      <div className="appointment-row__meta">
        <span>{appointment.doctor_name || "Doctor pending"}</span>
        <StatusBadge status={appointment.status} />
      </div>
    </article>
  );
}

export default function PatientDashboard() {
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;

    async function loadAppointments() {
      setLoading(true);
      setError("");

      try {
        const response = await api.get("/appointments/");
        if (active) {
          setAppointments(listFromResponse(response.data));
        }
      } catch {
        if (active) {
          setError("Appointments could not be loaded. Please refresh and try again.");
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    loadAppointments();

    return () => {
      active = false;
    };
  }, []);

  const upcomingAppointments = useMemo(
    () =>
      appointments
        .filter(isUpcoming)
        .sort((first, second) => new Date(first.start_at) - new Date(second.start_at)),
    [appointments],
  );
  const nextAppointment = upcomingAppointments[0];

  if (loading) {
    return <LoadingState label="Loading patient dashboard" />;
  }

  return (
    <section className="portal-section">
      <div className="portal-page-heading portal-heading-row">
        <div>
          <span className="portal-eyebrow">Patient portal</span>
          <h1>Dashboard</h1>
        </div>
        <Link className="btn btn-primary" to="/patient/book">
          Book appointment
        </Link>
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      <div className="dashboard-grid">
        <section className="metric-panel">
          <span className="metric-panel__label">Upcoming</span>
          <strong>{upcomingAppointments.length}</strong>
          <span className="metric-panel__caption">confirmed visits</span>
        </section>

        <section className="portal-panel next-appointment-panel">
          <div className="section-heading">
            <h2>Next appointment</h2>
          </div>
          {nextAppointment ? (
            <div className="next-appointment">
              <div>
                <h3>{nextAppointment.service_name || "Appointment"}</h3>
                <p>{formatDateTime(nextAppointment.start_at)}</p>
              </div>
              <dl>
                <div>
                  <dt>Doctor</dt>
                  <dd>{nextAppointment.doctor_name || "Doctor pending"}</dd>
                </div>
                <div>
                  <dt>Clinic</dt>
                  <dd>{nextAppointment.clinic_name || "Clinic pending"}</dd>
                </div>
              </dl>
              <StatusBadge status={nextAppointment.status} />
            </div>
          ) : (
            <div className="empty-state">
              <h3>No upcoming appointment</h3>
              <p>Your next confirmed visit will appear here.</p>
            </div>
          )}
        </section>
      </div>

      <section className="quick-actions" aria-label="Quick actions">
        <Link className="quick-action" to="/patient/book?mode=service">
          <span>Book by service</span>
          <strong>Find care</strong>
        </Link>
        <Link className="quick-action" to="/patient/book?mode=doctor">
          <span>Book by doctor</span>
          <strong>Choose provider</strong>
        </Link>
        <Link className="quick-action" to="/patient/appointments">
          <span>My appointments</span>
          <strong>Manage visits</strong>
        </Link>
      </section>

      <section className="portal-panel">
        <div className="section-heading">
          <h2>Upcoming appointments</h2>
          <Link to="/patient/appointments">View all</Link>
        </div>
        {upcomingAppointments.length > 0 ? (
          <div className="appointment-list compact">
            {upcomingAppointments.slice(0, 4).map((appointment) => (
              <AppointmentRow key={appointment.id} appointment={appointment} />
            ))}
          </div>
        ) : (
          <div className="empty-state">
            <h3>No visits scheduled</h3>
            <p>Book an appointment when you are ready.</p>
          </div>
        )}
      </section>
    </section>
  );
}
