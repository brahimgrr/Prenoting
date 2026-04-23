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

  if (!start) {
    return "Time pending";
  }

  const timeFormatter = new Intl.DateTimeFormat(undefined, {
    hour: "numeric",
    minute: "2-digit",
  });

  return end ? `${timeFormatter.format(start)} - ${timeFormatter.format(end)}` : timeFormatter.format(start);
}

function apiMessage(error, fallback) {
  const data = error?.response?.data;
  if (data?.slot) {
    return "This slot is no longer available. Please choose another time.";
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
          setError("Search results could not be loaded. Please try again.");
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
          setError("Services could not be loaded for booking. Please try again.");
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
    if (!selectedEntity || !selectedDate) {
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
        ...(activeTab === "service" ? { service: selectedService.id } : { doctor: selectedDoctor.id }),
      };

      try {
        const response = await api.get("/availability/", { params });
        if (active) {
          setSlots(listFromResponse(response.data));
        }
      } catch {
        if (active) {
          setError("Availability could not be loaded. Please adjust your filters and try again.");
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
  }, [activeTab, clinicId, selectedDate, selectedDoctor, selectedService]);

  const doctorServiceOptions = useMemo(() => {
    if (!selectedDoctor) {
      return services;
    }

    const matching = services.filter((service) => matchesSpecialty(service, selectedDoctor));
    return matching.length > 0 ? matching : services;
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

  async function confirmAppointment(event) {
    event.preventDefault();
    setSubmitting(true);
    setError("");
    setSuccess("");

    try {
      await api.post("/appointments/", {
        slot: selectedSlot.id,
        service: bookingService.id,
        notes,
      });
      setSuccess("Appointment confirmed.");
      setSelectedSlot(null);
      setNotes("");
    } catch (appointmentError) {
      setError(apiMessage(appointmentError, "Appointment could not be booked. Please try again."));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section className="portal-section">
      <div className="portal-page-heading">
        <span className="portal-eyebrow">Booking</span>
        <h1>Book appointment</h1>
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}
      {success && (
        <div className="alert alert-success" role="status">
          {success} <Link to="/patient/appointments">View appointments</Link>
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
                Service
              </button>
            </li>
            <li className="nav-item" role="presentation">
              <button
                type="button"
                className={`nav-link${activeTab === "doctor" ? " active" : ""}`}
                onClick={() => setActiveTab("doctor")}
              >
                Doctor
              </button>
            </li>
          </ul>

          <div className="booking-filters">
            <label className="form-label" htmlFor="booking-search">
              Search
            </label>
            <input
              id="booking-search"
              className="form-control"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={activeTab === "service" ? "Search services" : "Search doctors"}
            />
            <label className="form-label" htmlFor="clinic-filter">
              Clinic ID
            </label>
            <input
              id="clinic-filter"
              className="form-control"
              inputMode="numeric"
              pattern="[0-9]*"
              value={clinicId}
              onChange={(event) => setClinicId(event.target.value.replace(/\D/g, ""))}
              placeholder="Optional"
            />
            <label className="form-label" htmlFor="booking-date">
              Date
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
            <LoadingState label="Loading results" />
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
                      <small>{service.specialty_name || service.category || "Medical service"}</small>
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
                      <small>{doctor.specialty_name || "Doctor"}</small>
                    </span>
                    <span>#{doctor.id}</span>
                  </button>
                ))}
              {((activeTab === "service" && services.length === 0) || (activeTab === "doctor" && doctors.length === 0)) && (
                <div className="empty-state">
                  <h3>No results found</h3>
                  <p>Try another search term.</p>
                </div>
              )}
            </div>
          )}
        </section>

        <section className="portal-panel">
          <div className="section-heading">
            <h2>Available times</h2>
          </div>

          {activeTab === "doctor" && selectedDoctor && (
            <div className="mb-3">
              <label className="form-label" htmlFor="doctor-service">
                Service
              </label>
              <select
                id="doctor-service"
                className="form-select"
                value={doctorServiceId}
                onChange={(event) => setDoctorServiceId(event.target.value)}
              >
                <option value="">Choose service</option>
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
              <h3>Select a service</h3>
              <p>Available times will appear after a service is selected.</p>
            </div>
          )}
          {!selectedDoctor && activeTab === "doctor" && (
            <div className="empty-state">
              <h3>Select a doctor</h3>
              <p>Available times will appear after a doctor is selected.</p>
            </div>
          )}

          {loadingSlots && <LoadingState label="Loading availability" />}

          {!loadingSlots && (selectedService || selectedDoctor) && (
            <div className="slot-list">
              {slots.map((slot) => (
                <button
                  type="button"
                  key={slot.id}
                  className={`slot-chip${selectedSlot?.id === slot.id ? " is-selected" : ""}`}
                  onClick={() => setSelectedSlot(slot)}
                >
                  <span>{formatTimeRange(slot)}</span>
                  <small>{slot.doctor_name || "Doctor"} / {slot.clinic_name || `Clinic ${slot.clinic}`}</small>
                </button>
              ))}
              {slots.length === 0 && (
                <div className="empty-state">
                  <h3>No slots available</h3>
                  <p>Choose another date or clinic.</p>
                </div>
              )}
            </div>
          )}
        </section>

        <section className="portal-panel confirmation-panel">
          <div className="section-heading">
            <h2>Confirmation</h2>
          </div>
          <form onSubmit={confirmAppointment}>
            <dl className="summary-list">
              <div>
                <dt>Service</dt>
                <dd>{bookingService?.name || "Not selected"}</dd>
              </div>
              <div>
                <dt>Time</dt>
                <dd>{selectedSlot ? formatDateTime(selectedSlot.start_at) : "Not selected"}</dd>
              </div>
              <div>
                <dt>Doctor</dt>
                <dd>{selectedSlot?.doctor_name || selectedDoctor?.display_name || "Not selected"}</dd>
              </div>
              <div>
                <dt>Clinic</dt>
                <dd>{selectedSlot?.clinic_name || "Not selected"}</dd>
              </div>
            </dl>
            <label className="form-label" htmlFor="appointment-notes">
              Notes
            </label>
            <textarea
              id="appointment-notes"
              className="form-control"
              rows="4"
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
            />
            <button type="submit" className="btn btn-primary w-100 mt-3" disabled={!selectedSlot || !bookingService || submitting}>
              {submitting ? "Confirming..." : "Confirm"}
            </button>
          </form>
        </section>
      </div>
    </section>
  );
}
