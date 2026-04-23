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
    return "Orario in attesa";
  }

  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function AppointmentRow({ appointment }) {
  return (
    <article className="appointment-row">
      <div>
        <h3>{appointment.service_name || "Appuntamento"}</h3>
        <p>{formatDateTime(appointment.start_at)}</p>
      </div>
      <div className="appointment-row__meta">
        <span>{appointment.doctor_name || "Medico in attesa"}</span>
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
          setError("Impossibile caricare gli appuntamenti. Aggiorna la pagina e riprova.");
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
    return <LoadingState label="Caricamento dashboard paziente" />;
  }

  return (
    <section className="portal-section">
      <div className="portal-page-heading portal-heading-row">
        <div>
          <span className="portal-eyebrow">Portale paziente</span>
          <h1>Riepilogo</h1>
        </div>
        <Link className="btn btn-primary" to="/patient/book">
          Prenota visita
        </Link>
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      <div className="dashboard-grid">
        <section className="metric-panel">
          <span className="metric-panel__label">Prossimi</span>
          <strong>{upcomingAppointments.length}</strong>
          <span className="metric-panel__caption">visite confermate</span>
        </section>

        <section className="portal-panel next-appointment-panel">
          <div className="section-heading">
            <h2>Prossimo appuntamento</h2>
          </div>
          {nextAppointment ? (
            <div className="next-appointment">
              <div>
                <h3>{nextAppointment.service_name || "Appuntamento"}</h3>
                <p>{formatDateTime(nextAppointment.start_at)}</p>
              </div>
              <dl>
                <div>
                  <dt>Medico</dt>
                  <dd>{nextAppointment.doctor_name || "Medico in attesa"}</dd>
                </div>
                <div>
                  <dt>Ambulatorio</dt>
                  <dd>{nextAppointment.clinic_name || "Ambulatorio in attesa"}</dd>
                </div>
              </dl>
              <StatusBadge status={nextAppointment.status} />
            </div>
          ) : (
            <div className="empty-state">
              <h3>Nessun appuntamento imminente</h3>
              <p>La tua prossima visita confermata comparirà qui.</p>
            </div>
          )}
        </section>
      </div>

      <section className="quick-actions" aria-label="Azioni rapide">
        <Link className="quick-action" to="/patient/book?mode=service">
          <span>Prenota per prestazione</span>
          <strong>Trova assistenza</strong>
        </Link>
        <Link className="quick-action" to="/patient/book?mode=doctor">
          <span>Prenota per medico</span>
          <strong>Scegli professionista</strong>
        </Link>
        <Link className="quick-action" to="/patient/appointments">
          <span>I miei appuntamenti</span>
          <strong>Gestisci visite</strong>
        </Link>
      </section>

      <section className="portal-panel">
        <div className="section-heading">
          <h2>Appuntamenti imminenti</h2>
          <Link to="/patient/appointments">Vedi tutti</Link>
        </div>
        {upcomingAppointments.length > 0 ? (
          <div className="appointment-list compact">
            {upcomingAppointments.slice(0, 4).map((appointment) => (
              <AppointmentRow key={appointment.id} appointment={appointment} />
            ))}
          </div>
        ) : (
          <div className="empty-state">
            <h3>Nessuna visita programmata</h3>
            <p>Prenota un appuntamento quando sei pronto.</p>
          </div>
        )}
      </section>
    </section>
  );
}
