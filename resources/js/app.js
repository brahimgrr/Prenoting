import "bootstrap";
import $ from "jquery";

window.$ = $;
window.jQuery = $;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

$.ajaxSetup({
  headers: {
    "X-CSRF-TOKEN": csrfToken,
  },
});

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
  const formatter = new Intl.DateTimeFormat("it-IT", {
    hour: "numeric",
    minute: "2-digit",
  });

  if (!start) {
    return "Orario in attesa";
  }

  return end ? `${formatter.format(start)} - ${formatter.format(end)}` : formatter.format(start);
}

function initBooking() {
  const page = $(".booking-page");
  if (!page.length) {
    return;
  }

  let mode = page.data("initial-mode") || "service";
  let selectedService = null;
  let selectedDoctor = null;
  let selectedSlot = null;

  function resetSlot() {
    selectedSlot = null;
    $("#booking-slot-id").val("");
    $("#summary-slot").text("Non selezionato");
    $("#summary-doctor").text(selectedDoctor?.name || "Non selezionato");
    $("#summary-clinic").text("Non selezionato");
    $("#booking-submit").prop("disabled", true);
  }

  function applyMode(nextMode) {
    mode = nextMode;
    $(".booking-mode-tab").toggleClass("active", false);
    $(`.booking-mode-tab[data-mode="${mode}"]`).toggleClass("active", true);
    $(".result-list--services").toggleClass("d-none", mode !== "service");
    $(".result-list--doctors").toggleClass("d-none", mode !== "doctor");
    $(".doctor-service-wrap").toggleClass("d-none", mode !== "doctor");
    selectedService = null;
    selectedDoctor = null;
    $("#booking-service-id").val("");
    $("#doctor-service").val("");
    $("#summary-service").text("Non selezionata");
    resetSlot();
    $(".result-item").removeClass("is-selected");
    $(".booking-slots").empty();
    $(".booking-empty").removeClass("d-none");
  }

  function loadSlots() {
    const serviceId = mode === "service" ? selectedService?.id : $("#doctor-service").val();
    if (!serviceId || (mode === "doctor" && !selectedDoctor)) {
      return;
    }

    resetSlot();
    $(".booking-empty").addClass("d-none");
    $(".booking-slots").html('<div class="empty-state"><p>Caricamento disponibilita...</p></div>');

    $.getJSON("/availability", {
      service: serviceId,
      doctor: mode === "doctor" ? selectedDoctor.id : undefined,
      clinic: $("#clinic-filter").val() || undefined,
      date: $("#booking-date").val() || undefined,
    }).done((slots) => {
      if (!slots.length) {
        $(".booking-slots").html('<div class="empty-state"><h3>Nessuno slot disponibile</h3><p>Scegli un altra data o un altro ambulatorio.</p></div>');
        return;
      }

      $(".booking-slots").html(slots.map((slot) => `
        <button type="button" class="slot-chip booking-slot" data-slot='${JSON.stringify(slot)}'>
          <span>${formatTimeRange(slot)}</span>
          <small>${slot.doctor_name || "Medico"} / ${slot.clinic_name || `Ambulatorio ${slot.clinic}`}</small>
        </button>
      `).join(""));
    }).fail(() => {
      $(".booking-slots").html('<div class="empty-state"><h3>Disponibilita non caricata</h3><p>Modifica i filtri e riprova.</p></div>');
    });
  }

  $(".booking-mode-tab").on("click", function () {
    applyMode($(this).data("mode"));
  });

  $(".booking-service").on("click", function () {
    selectedService = {
      id: $(this).data("id"),
      name: $(this).data("name"),
    };
    selectedDoctor = null;
    $(".result-item").removeClass("is-selected");
    $(this).addClass("is-selected");
    $("#booking-service-id").val(selectedService.id);
    $("#summary-service").text(selectedService.name);
    $("#summary-doctor").text("Non selezionato");
    loadSlots();
  });

  $(".booking-doctor").on("click", function () {
    selectedDoctor = {
      id: $(this).data("id"),
      name: $(this).data("name"),
      services: String($(this).data("services") || "").split(",").filter(Boolean),
    };
    $(".result-item").removeClass("is-selected");
    $(this).addClass("is-selected");
    $("#summary-doctor").text(selectedDoctor.name);
    $("#doctor-service option").each(function () {
      const value = String($(this).attr("value") || "");
      $(this).prop("disabled", value !== "" && !selectedDoctor.services.includes(value));
    });
    $("#doctor-service").val("");
    $("#booking-service-id").val("");
    $("#summary-service").text("Non selezionata");
    resetSlot();
    $(".booking-slots").empty();
  });

  $("#doctor-service").on("change", function () {
    const option = $(this).find("option:selected");
    $("#booking-service-id").val($(this).val());
    $("#summary-service").text(option.text() || "Non selezionata");
    loadSlots();
  });

  $("#booking-date, #clinic-filter").on("change input", function () {
    loadSlots();
  });

  $("#booking-search").on("input", function () {
    const value = $(this).val().toLowerCase();
    $(".result-item").each(function () {
      $(this).toggleClass("d-none", !$(this).text().toLowerCase().includes(value));
    });
  });

  $(document).on("click", ".booking-slot", function () {
    selectedSlot = $(this).data("slot");
    $(".booking-slot").removeClass("is-selected");
    $(this).addClass("is-selected");
    $("#booking-slot-id").val(selectedSlot.id);
    $("#summary-slot").text(formatDateTime(selectedSlot.start_at));
    $("#summary-doctor").text(selectedSlot.doctor_name || selectedDoctor?.name || "Non selezionato");
    $("#summary-clinic").text(selectedSlot.clinic_name || "Non selezionato");
    $("#booking-submit").prop("disabled", false);
  });
}

function initRescheduleForms() {
  $(".reschedule-form").each(function () {
    const form = $(this);
    const dateInput = form.find(".reschedule-date");
    const select = form.find(".reschedule-slot-select");
    const serviceId = form.data("service");

    function loadOptions() {
      select.html('<option value="">Caricamento nuovi orari...</option>');
      $.getJSON("/availability", {
        service: serviceId,
        date: dateInput.val(),
      }).done((slots) => {
        if (!slots.length) {
          select.html('<option value="">Nessuno slot disponibile</option>');
          return;
        }
        select.html('<option value="">Scegli nuovo orario</option>' + slots.map((slot) => `
          <option value="${slot.id}">${formatTimeRange(slot)} - ${slot.doctor_name || "Medico"} / ${slot.clinic_name || "Ambulatorio"}</option>
        `).join(""));
      }).fail(() => {
        select.html('<option value="">Disponibilita non caricata</option>');
      });
    }

    dateInput.on("change", loadOptions);
    loadOptions();
  });
}

$(function () {
  initBooking();
  initRescheduleForms();
});
