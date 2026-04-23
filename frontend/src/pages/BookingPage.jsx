import { useEffect, useMemo, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import api from "../api/client";
import LoadingState from "../components/LoadingState";

function listFromResponse(data) {
  return Array.isArray(data) ? data : data?.results ?? [];
}

function todayValue() {
  return new Date().toISOString().slice(0, 10);
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

function formatTimeRange(slot) {
  const start = slot.start_at ? new Date(slot.start_at) : null;
  const end = slot.end_at ? new Date(slot.end_at) : null;

  if (!start) {
    return "Orario in attesa";
  }

  const timeFormatter = new Intl.DateTimeFormat("it-IT", {
    hour: "numeric",
    minute: "2-digit",
  });

  return end ? `${timeFormatter.format(start)} - ${timeFormatter.format(end)}` : timeFormatter.format(start);
}

function apiMessage(error, fallback) {
  const data = error?.response?.data;
  if (data?.slot) {
    return "Questo orario non è più disponibile. Scegli un altro orario.";
  }
  if (typeof data?.detail === "string") {
    return data.detail;
  }
  if (data && typeof data === "object") {
    const firstValue = Object.values(data)[0];
    if (Array.isArray(firstValue)) {
      return firstValue.join(" ");
    }
  }
  return fallback;
}

function matchesSpecialty(service, doctor) {
  if (!service || !doctor) {
    return false;
  }

  return String(service.specialty) === String(doctor.specialty) || service.specialty_name === doctor.specialty_name;
}

function doctorOffersService(doctor, service) {
  if (!doctor || !service) {
    return false;
  }

  if (Array.isArray(doctor.service_ids)) {
    return doctor.service_ids.map(String).includes(String(service.id));
  }

  return matchesSpecialty(service, doctor);
}

export default function BookingPage() {
  const [searchParams] = useSearchParams();
  const initialMode = searchParams.get("mode") === "doctor" ? "doctor" : "service";
  const [activeTab, setActiveTab] = useState(initialMode);
  const [search, setSearch] = useState("");
  const [clinicId, setClinicId] = useState("");
  const [selectedDate, setSelectedDate] = useState(todayValue);
  const [services, setServices] = useState([]);
  const [doctors, setDoctors] = useState([]);
  const [selectedService, setSelectedService] = useState(null);
  const [selectedDoctor, setSelectedDoctor] = useState(null);
  const [doctorServiceId, setDoctorServiceId] = useState("");
  const [slots, setSlots] = useState([]);
  const [selectedSlot, setSelectedSlot] = useState(null);
  const [notes, setNotes] = useState("");
  const [loadingResults, setLoadingResults] = useState(true);
  const [loadingSlots, setLoadingSlots] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  useEffect(() => {
    let active = true;

    async function loadResults() {
      setLoadingResults(true);
      setError("");

      try {
        const endpoint = activeTab === "service" ? "/services/" : "/doctors/";
        const response = await api.get(endpoint, { params: search ? { search } : {} });
        if (!active) {
          return;
        }

        if (activeTab === "service") {
          setServices(listFromResponse(response.data));
        } else {
          setDoctors(listFromResponse(response.data));
        }
      } catch {
        if (active) {
          setError("Impossibile caricare i risultati della ricerca. Riprova.");
        }
      } finally {
        if (active) {
          setLoadingResults(false);
        }
      }
    }

    loadResults();

    return () => {
      active = false;
    };
  }, [activeTab, search]);

  useEffect(() => {
    if (activeTab !== "doctor") {
      return;
    }

    let active = true;

    async function loadServicesForDoctorFlow() {
      try {
        const response = await api.get("/services/");
        if (active) {
          setServices(listFromResponse(response.data));
        }
      } catch {
        if (active) {
          setError("Impossibile caricare le prestazioni per la prenotazione. Riprova.");
        }
      }
    }

    loadServicesForDoctorFlow();

    return () => {
      active = false;
    };
  }, [activeTab]);

  useEffect(() => {
    const selectedEntity = activeTab === "service" ? selectedService : selectedDoctor;
    if (!selectedEntity || !selectedDate || (activeTab === "doctor" && !doctorServiceId)) {
      setSlots([]);
      setSelectedSlot(null);
      return;
    }

    let active = true;

    async function loadAvailability() {
      setLoadingSlots(true);
      setError("");
      setSelectedSlot(null);

      const params = {
        date: selectedDate,
        ...(clinicId ? { clinic: clinicId } : {}),
        ...(activeTab === "service"
          ? { service: selectedService.id }
          : { doctor: selectedDoctor.id, service: doctorServiceId }),
      };

      try {
        const response = await api.get("/availability/", { params });
        if (active) {
          setSlots(listFromResponse(response.data));
        }
      } catch {
        if (active) {
          setError("Impossibile caricare le disponibilità. Modifica i filtri e riprova.");
          setSlots([]);
        }
      } finally {
        if (active) {
          setLoadingSlots(false);
        }
      }
    }

    loadAvailability();

    return () => {
      active = false;
    };
  }, [activeTab, clinicId, doctorServiceId, selectedDate, selectedDoctor, selectedService]);

  const doctorServiceOptions = useMemo(() => {
    if (!selectedDoctor) {
      return services;
    }

    return services.filter((service) => doctorOffersService(selectedDoctor, service));
  }, [selectedDoctor, services]);

  const bookingService = activeTab === "service" ? selectedService : services.find((service) => String(service.id) === String(doctorServiceId));

  function selectService(service) {
    setSelectedService(service);
    setSelectedDoctor(null);
    setSelectedSlot(null);
    setSuccess("");
  }

  function selectDoctor(doctor) {
    setSelectedDoctor(doctor);
    setSelectedService(null);
    setSelectedSlot(null);
    setDoctorServiceId("");
    setSuccess("");
  }

  function removeSlot(slotId) {
    setSlots((currentSlots) => currentSlots.filter((slot) => slot.id !== slotId));
  }

  async function confirmAppointment(event) {
    event.preventDefault();
    const bookedSlotId = selectedSlot.id;
    setSubmitting(true);
    setError("");
    setSuccess("");

    try {
      await api.post("/appointments/", {
        slot: selectedSlot.id,
        service: bookingService.id,
        notes,
      });
      setSuccess("Appuntamento confermato.");
      removeSlot(bookedSlotId);
      setSelectedSlot(null);
      setNotes("");
    } catch (appointmentError) {
      setError(apiMessage(appointmentError, "Impossibile prenotare l'appuntamento. Riprova."));
      if (appointmentError?.response?.data?.slot) {
        removeSlot(bookedSlotId);
        setSelectedSlot(null);
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section className="portal-section">
      <div className="portal-page-heading">
        <span className="portal-eyebrow">Prenotazione</span>
        <h1>Prenota visita</h1>
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}
      {success && (
        <div className="alert alert-success" role="status">
          {success} <Link to="/patient/appointments">Vedi appuntamenti</Link>
        </div>
      )}

      <div className="booking-layout">
        <section className="portal-panel booking-search-panel">
          <ul className="nav nav-tabs portal-tabs" role="tablist">
            <li className="nav-item" role="presentation">
              <button
                type="button"
                className={`nav-link${activeTab === "service" ? " active" : ""}`}
                onClick={() => setActiveTab("service")}
              >
                Prestazione
              </button>
            </li>
            <li className="nav-item" role="presentation">
              <button
                type="button"
                className={`nav-link${activeTab === "doctor" ? " active" : ""}`}
                onClick={() => setActiveTab("doctor")}
              >
                Medico
              </button>
            </li>
          </ul>

          <div className="booking-filters">
            <label className="form-label" htmlFor="booking-search">
              Cerca
            </label>
            <input
              id="booking-search"
              className="form-control"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={activeTab === "service" ? "Cerca prestazioni" : "Cerca medici"}
            />
            <label className="form-label" htmlFor="clinic-filter">
              ID ambulatorio
            </label>
            <input
              id="clinic-filter"
              className="form-control"
              inputMode="numeric"
              pattern="[0-9]*"
              value={clinicId}
              onChange={(event) => setClinicId(event.target.value.replace(/\D/g, ""))}
              placeholder="Opzionale"
            />
            <label className="form-label" htmlFor="booking-date">
              Data
            </label>
            <input
              id="booking-date"
              className="form-control"
              type="date"
              value={selectedDate}
              onChange={(event) => setSelectedDate(event.target.value)}
            />
          </div>

          {loadingResults ? (
            <LoadingState label="Caricamento risultati" />
          ) : (
            <div className="result-list">
              {activeTab === "service" &&
                services.map((service) => (
                  <button
                    type="button"
                    key={service.id}
                    className={`result-item${selectedService?.id === service.id ? " is-selected" : ""}`}
                    onClick={() => selectService(service)}
                  >
                    <span>
                      <strong>{service.name}</strong>
                      <small>{service.specialty_name || service.category || "Prestazione medica"}</small>
                    </span>
                    <span>{service.duration_minutes} min</span>
                  </button>
                ))}
              {activeTab === "doctor" &&
                doctors.map((doctor) => (
                  <button
                    type="button"
                    key={doctor.id}
                    className={`result-item${selectedDoctor?.id === doctor.id ? " is-selected" : ""}`}
                    onClick={() => selectDoctor(doctor)}
                  >
                    <span>
                      <strong>{doctor.display_name}</strong>
                      <small>{doctor.specialty_name || "Medico"}</small>
                    </span>
                    <span>#{doctor.id}</span>
                  </button>
                ))}
              {((activeTab === "service" && services.length === 0) || (activeTab === "doctor" && doctors.length === 0)) && (
                <div className="empty-state">
                  <h3>Nessun risultato trovato</h3>
                  <p>Prova con un altro termine di ricerca.</p>
                </div>
              )}
            </div>
          )}
        </section>

        <section className="portal-panel">
          <div className="section-heading">
            <h2>Orari disponibili</h2>
          </div>

          {activeTab === "doctor" && selectedDoctor && (
            <div className="mb-3">
              <label className="form-label" htmlFor="doctor-service">
                Prestazione
              </label>
              <select
                id="doctor-service"
                className="form-select"
                value={doctorServiceId}
                onChange={(event) => setDoctorServiceId(event.target.value)}
              >
                <option value="">Scegli prestazione</option>
                {doctorServiceOptions.map((service) => (
                  <option key={service.id} value={service.id}>
                    {service.name}
                  </option>
                ))}
              </select>
            </div>
          )}

          {!selectedService && activeTab === "service" && (
            <div className="empty-state">
              <h3>Seleziona una prestazione</h3>
              <p>Gli orari disponibili compariranno dopo la selezione.</p>
            </div>
          )}
          {!selectedDoctor && activeTab === "doctor" && (
            <div className="empty-state">
              <h3>Seleziona un medico</h3>
              <p>Gli orari disponibili compariranno dopo la selezione.</p>
            </div>
          )}
          {selectedDoctor && activeTab === "doctor" && !doctorServiceId && (
            <div className="empty-state">
              <h3>Seleziona una prestazione</h3>
              <p>Scegli una prestazione prima di selezionare un orario con questo medico.</p>
            </div>
          )}

          {loadingSlots && <LoadingState label="Caricamento disponibilità" />}

          {!loadingSlots && (selectedService || (selectedDoctor && doctorServiceId)) && (
            <div className="slot-list">
              {slots.map((slot) => (
                <button
                  type="button"
                  key={slot.id}
                  className={`slot-chip${selectedSlot?.id === slot.id ? " is-selected" : ""}`}
                  onClick={() => setSelectedSlot(slot)}
                >
                  <span>{formatTimeRange(slot)}</span>
                  <small>{slot.doctor_name || "Medico"} / {slot.clinic_name || `Ambulatorio ${slot.clinic}`}</small>
                </button>
              ))}
              {slots.length === 0 && (
                <div className="empty-state">
                  <h3>Nessuno slot disponibile</h3>
                  <p>Scegli un'altra data o un altro ambulatorio.</p>
                </div>
              )}
            </div>
          )}
        </section>

        <section className="portal-panel confirmation-panel">
          <div className="section-heading">
            <h2>Conferma</h2>
          </div>
          <form onSubmit={confirmAppointment}>
            <dl className="summary-list">
              <div>
                <dt>Prestazione</dt>
                <dd>{bookingService?.name || "Non selezionata"}</dd>
              </div>
              <div>
                <dt>Orario</dt>
                <dd>{selectedSlot ? formatDateTime(selectedSlot.start_at) : "Non selezionato"}</dd>
              </div>
              <div>
                <dt>Medico</dt>
                <dd>{selectedSlot?.doctor_name || selectedDoctor?.display_name || "Non selezionato"}</dd>
              </div>
              <div>
                <dt>Ambulatorio</dt>
                <dd>{selectedSlot?.clinic_name || "Non selezionato"}</dd>
              </div>
            </dl>
            <label className="form-label" htmlFor="appointment-notes">
              Note
            </label>
            <textarea
              id="appointment-notes"
              className="form-control"
              rows="4"
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
            />
            <button type="submit" className="btn btn-primary w-100 mt-3" disabled={!selectedSlot || !bookingService || submitting}>
              {submitting ? "Conferma..." : "Conferma"}
            </button>
          </form>
        </section>
      </div>
    </section>
  );
}
