import * as bootstrap from "bootstrap";

function positionAgendaScroll() {
  const container = document.querySelector("[data-agenda-scroll-container]");
  const marker = container?.querySelector("[data-agenda-now-marker]");
  const firstOccupiedRow = container?.querySelector("[data-agenda-occupied-row]");
  if (!container || (!marker && !firstOccupiedRow)) return;

  requestAnimationFrame(() => {
    const containerRect = container.getBoundingClientRect();
    const targetRect = (marker ?? firstOccupiedRow).getBoundingClientRect();
    const targetTop = targetRect.top - containerRect.top + container.scrollTop;

    if (marker) {
      const markerCenter = targetTop + targetRect.height / 2;
      container.scrollTop = Math.max(0, markerCenter - container.clientHeight / 2);
      return;
    }

    container.scrollTop = Math.max(0, targetTop);
  });
}

const DEFAULT_WORKING_START = "09:00";
const DEFAULT_WORKING_END = "12:00";
const WORKING_HOUR_AUTOFILL_MINUTES = 60;

function halfHourTimeOptions(selectedValue = "") {
  let options = selectedValue
    ? `<option value="${selectedValue}" selected>${selectedValue}</option><option value="">--:--</option>`
    : '<option value="">--:--</option>';

  for (let hour = 0; hour < 24; hour += 1) {
    for (const minute of [0, 30]) {
      const value = `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
      if (value !== selectedValue) {
        options += `<option value="${value}">${value}</option>`;
      }
    }
  }

  return options;
}

function timeSelectTemplate(name, selectedValue = "") {
  return `<select class="form-select form-select-sm text-center" name="${name}">${halfHourTimeOptions(selectedValue)}</select>`;
}

function timeToMinutes(value) {
  const match = /^(\d{2}):(\d{2})$/.exec(value);
  if (!match) return null;

  return Number(match[1]) * 60 + Number(match[2]);
}

function minutesToTime(minutes) {
  const lastOptionMinutes = (23 * 60) + 30;
  if (minutes < 0 || minutes > lastOptionMinutes) return "";

  const hour = Math.floor(minutes / 60);
  const minute = minutes % 60;
  return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
}

function addMinutesToTime(value, minutesToAdd) {
  const minutes = timeToMinutes(value);
  if (minutes === null) return "";

  return minutesToTime(minutes + minutesToAdd);
}

function suggestedWorkingHoursEnd(startValue) {
  return addMinutesToTime(startValue, WORKING_HOUR_AUTOFILL_MINUTES);
}

function previousWorkingHoursEnd(rows) {
  return Array.from(rows.querySelectorAll('[data-working-hours-row] select[name$="[end_time]"]'))
    .reverse()
    .find((select) => select.value)?.value || "";
}

function workingHoursDefaultsForNewRow(rows) {
  const start = addMinutesToTime(previousWorkingHoursEnd(rows), WORKING_HOUR_AUTOFILL_MINUTES) || DEFAULT_WORKING_START;
  const end = suggestedWorkingHoursEnd(start) || DEFAULT_WORKING_END;

  return { start, end };
}

function workingHoursRowTemplate(weekday, index, startValue = "", endValue = "") {
  return `
    <div class="input-group input-group-sm w-auto" data-working-hours-row>
      ${timeSelectTemplate(`working_hours[${weekday}][${index}][start_time]`, startValue)}
      <span class="input-group-text bg-transparent px-2">a</span>
      ${timeSelectTemplate(`working_hours[${weekday}][${index}][end_time]`, endValue)}
      <button class="btn btn-outline-danger border-start-0" type="button" data-working-hours-remove aria-label="Rimuovi fascia">x</button>
    </div>
  `;
}

function workingHoursRowsHaveValues(day) {
  return Array.from(day.querySelectorAll("[data-working-hours-row] select")).some((select) => select.value);
}

function workingHoursDayMinutes(day) {
  return Array.from(day.querySelectorAll("[data-working-hours-row]")).reduce((total, row) => {
    const start = row.querySelector('select[name$="[start_time]"]')?.value;
    const end = row.querySelector('select[name$="[end_time]"]')?.value;
    const startMinutes = timeToMinutes(start || "");
    const endMinutes = timeToMinutes(end || "");

    if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) return total;

    return total + (endMinutes - startMinutes);
  }, 0);
}

function workingHoursDurationLabel(minutes) {
  if (minutes <= 0) return "";

  const hours = Math.floor(minutes / 60);
  const remainder = minutes % 60;
  return `${hours}h${remainder > 0 ? ` ${remainder}m` : ""}`;
}

function setWorkingHoursDayOpen(day, isOpen, fillDefaults = false) {
  const rows = day.querySelector("[data-working-hours-rows]");
  const controls = day.querySelector("[data-working-hours-controls]");
  const emptyMessage = day.querySelector("[data-working-hours-empty]");
  const toggle = day.querySelector("[data-working-hours-toggle]");
  const status = day.querySelector("[data-working-hours-status]");
  const label = day.querySelector(".form-check-label");
  const layoutRow = day.querySelector(".row");

  if (!rows) return;

  if (!isOpen) {
    const firstRow = rows.querySelector("[data-working-hours-row]");
    rows.querySelectorAll("[data-working-hours-row]").forEach((row, index) => {
      if (index > 0) row.remove();
    });
    firstRow?.querySelectorAll("select").forEach((select) => {
      select.value = "";
    });
    day.setAttribute("data-next-index", "1");
  } else if (fillDefaults && !workingHoursRowsHaveValues(day)) {
    const firstRow = rows.querySelector("[data-working-hours-row]");
    const startSelect = firstRow?.querySelector('select[name$="[start_time]"]');
    const endSelect = firstRow?.querySelector('select[name$="[end_time]"]');
    if (startSelect) startSelect.value = DEFAULT_WORKING_START;
    if (endSelect) endSelect.value = DEFAULT_WORKING_END;
  }

  toggle && (toggle.checked = isOpen);
  controls?.classList.toggle("d-flex", isOpen);
  controls?.classList.toggle("d-none", !isOpen);
  emptyMessage?.classList.toggle("d-none", isOpen);
  day.classList.toggle("bg-body-tertiary", !isOpen);
  label?.classList.toggle("text-secondary", !isOpen);
  layoutRow?.classList.toggle("align-items-start", isOpen);
  layoutRow?.classList.toggle("align-items-center", !isOpen);

  if (status) {
    status.classList.toggle("text-primary", isOpen);
    status.classList.toggle("text-secondary", !isOpen);
    const duration = workingHoursDurationLabel(workingHoursDayMinutes(day));
    status.textContent = isOpen ? `Aperto${duration ? ` - ${duration}` : ""}` : "Chiuso";
  }
}

const COMUNE_SUGGESTION_LIMIT = 8;
let suppressComuneFocusUpdate = false;

function normalizeComune(value) {
  return value.trim().toLocaleUpperCase("it-IT");
}

function hideComuneSuggestions(combobox) {
  const input = combobox.querySelector("[data-comune-input]");
  const suggestions = combobox.querySelector("[data-comune-suggestions]");

  suggestions?.setAttribute("hidden", "");
  input?.setAttribute("aria-expanded", "false");
  suggestions?.querySelectorAll("[data-comune-option]").forEach((option) => {
    option.hidden = true;
  });
}

function updateComuneSuggestions(input) {
  const combobox = input.closest("[data-comune-combobox]");
  const suggestions = combobox?.querySelector("[data-comune-suggestions]");
  if (!combobox || !suggestions) return;

  const query = normalizeComune(input.value);
  let shown = 0;

  suggestions.querySelectorAll("[data-comune-option]").forEach((option) => {
    const matches =
      query.length > 0 &&
      normalizeComune(option.value).includes(query) &&
      shown < COMUNE_SUGGESTION_LIMIT;

    option.hidden = !matches;
    if (matches) shown += 1;
  });

  suggestions.toggleAttribute("hidden", shown === 0);
  input.setAttribute("aria-expanded", shown > 0 ? "true" : "false");
}

function visibleComuneOptions(combobox) {
  return Array.from(combobox.querySelectorAll("[data-comune-option]")).filter(
    (option) => !option.hidden
  );
}

function selectComuneOption(option) {
  const combobox = option.closest("[data-comune-combobox]");
  const input = combobox?.querySelector("[data-comune-input]");
  if (!combobox || !input) return;

  suppressComuneFocusUpdate = true;
  input.value = option.value;
  input.focus();
  hideComuneSuggestions(combobox);

  requestAnimationFrame(() => {
    suppressComuneFocusUpdate = false;
  });
}

function tickAgendaNowMarker() {
  const container = document.querySelector("[data-agenda-scroll-container]");
  if (!container) return;

  const now = new Date();
  const rows = Array.from(container.querySelectorAll(".doctor-agenda-row"));

  let currentRow = null;
  rows.forEach((row) => {
    const timeEl = row.querySelector("time[datetime]");
    if (!timeEl) return;
    const rowStart = new Date(timeEl.getAttribute("datetime"));
    const rowEnd = new Date(rowStart.getTime() + 30 * 60 * 1000);
    row.classList.toggle("doctor-agenda-row--past", now >= rowEnd);
    if (now >= rowStart && now < rowEnd) currentRow = row;
  });

  let marker = container.querySelector("[data-agenda-now-marker]");

  if (!currentRow) {
    marker?.remove();
    return;
  }

  const contentDiv = currentRow.querySelector(".doctor-agenda-row__content");
  if (!contentDiv) return;

  contentDiv.removeAttribute("aria-hidden");

  if (!marker) {
    marker = document.createElement("div");
    marker.className = "doctor-agenda-now-marker";
    marker.setAttribute("data-agenda-now-marker", "");
    marker.innerHTML = "<span></span>";
    contentDiv.prepend(marker);
  } else if (!contentDiv.contains(marker)) {
    const oldContent = marker.parentElement;
    marker.remove();
    if (oldContent && !oldContent.querySelector(".doctor-agenda-item")) {
      oldContent.setAttribute("aria-hidden", "true");
    }
    contentDiv.prepend(marker);
  }

  const rowStart = new Date(currentRow.querySelector("time[datetime]").getAttribute("datetime"));
  const minutesIntoRow = (now - rowStart) / 60000;
  marker.style.setProperty("--now-position", `${Math.min(100, Math.max(0, (minutesIntoRow / 30) * 100))}%`);

  const label = marker.querySelector("span");
  if (label) {
    const hh = String(now.getHours()).padStart(2, "0");
    const mm = String(now.getMinutes()).padStart(2, "0");
    label.textContent = `Ora ${hh}:${mm}`;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  positionAgendaScroll();
  tickAgendaNowMarker();
  setInterval(tickAgendaNowMarker, 30_000);

  document.querySelectorAll("[data-auto-show-modal]").forEach((modalEl) => {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });
});

document.addEventListener("input", (e) => {
  const comuneInput = e.target.closest("[data-comune-input]");
  if (!comuneInput) return;

  updateComuneSuggestions(comuneInput);
});

document.addEventListener("focusin", (e) => {
  const comuneInput = e.target.closest("[data-comune-input]");
  if (!comuneInput || suppressComuneFocusUpdate) return;

  updateComuneSuggestions(comuneInput);
});

document.addEventListener("change", (e) => {
  const workingHoursToggle = e.target.closest?.("[data-working-hours-toggle]");
  if (workingHoursToggle) {
    const day = workingHoursToggle.closest("[data-working-hours-day]");
    if (day) {
      setWorkingHoursDayOpen(day, workingHoursToggle.checked, true);
    }
    return;
  }

  const workingHoursSelect = e.target.closest?.("[data-working-hours-row] select");
  const startSelect = e.target.matches?.('[data-working-hours-row] select[name$="[start_time]"]')
    ? e.target
    : null;
  if (!workingHoursSelect) return;

  const row = workingHoursSelect.closest("[data-working-hours-row]");
  if (startSelect) {
    const endSelect = row?.querySelector('select[name$="[end_time]"]');
    if (endSelect) {
      endSelect.value = suggestedWorkingHoursEnd(startSelect.value);
    }
  }

  const day = row?.closest("[data-working-hours-day]");
  if (day) setWorkingHoursDayOpen(day, workingHoursRowsHaveValues(day));
});

document.addEventListener("keydown", (e) => {
  const comuneInput = e.target.closest("[data-comune-input]");
  if (comuneInput) {
    const combobox = comuneInput.closest("[data-comune-combobox]");
    const options = combobox ? visibleComuneOptions(combobox) : [];

    if (e.key === "ArrowDown" && options.length > 0) {
      e.preventDefault();
      options[0].focus();
    }

    if (e.key === "Escape" && combobox) {
      hideComuneSuggestions(combobox);
    }

    return;
  }

  const comuneOption = e.target.closest("[data-comune-option]");
  if (!comuneOption) return;

  const combobox = comuneOption.closest("[data-comune-combobox]");
  const options = combobox ? visibleComuneOptions(combobox) : [];
  const currentIndex = options.indexOf(comuneOption);

  if (e.key === "ArrowDown" && options[currentIndex + 1]) {
    e.preventDefault();
    options[currentIndex + 1].focus();
  }

  if (e.key === "ArrowUp") {
    e.preventDefault();
    if (options[currentIndex - 1]) {
      options[currentIndex - 1].focus();
      return;
    }

    combobox?.querySelector("[data-comune-input]")?.focus();
  }

  if (e.key === "Escape" && combobox) {
    hideComuneSuggestions(combobox);
    combobox.querySelector("[data-comune-input]")?.focus();
  }
});

document.addEventListener("click", (e) => {
  const comuneOption = e.target.closest("[data-comune-option]");
  if (comuneOption) {
    selectComuneOption(comuneOption);
    return;
  }

  document.querySelectorAll("[data-comune-combobox]").forEach((combobox) => {
    if (!combobox.contains(e.target)) {
      hideComuneSuggestions(combobox);
    }
  });

  // Week navigation arrow (fetch-based, no page reload)
  const arrow = e.target.closest("[data-week-url]");
  const isDisabled = arrow
    ? (arrow.tagName === "BUTTON" ? arrow.disabled : arrow.getAttribute("aria-disabled") === "true")
    : false;
  if (arrow && !isDisabled) {
    e.preventDefault();
    const weekUrl = arrow.getAttribute("data-week-url");
    const pageUrl = arrow.getAttribute("data-page-url");
    const region = document.getElementById("booking-week-region");
    if (!region) return;

    document.querySelectorAll("[data-week-url]").forEach((b) => {
      if (b.tagName === "BUTTON") {
        b.disabled = true;
      } else {
        b.setAttribute("aria-disabled", "true");
      }
    });

    fetch(weekUrl, { headers: { "X-Requested-With": "XMLHttpRequest" } })
      .then((res) => {
        if (!res.ok) throw new Error();
        return res.text();
      })
      .then((html) => {
        region.innerHTML = html;
        history.pushState({}, "", pageUrl);
      })
      .catch(() => {
        window.location.href = pageUrl;
      });
    return;
  }

  // Slot period filter
  const filterBtn = e.target.closest("[data-slot-period-filter]");
  if (filterBtn) {
    const period = filterBtn.getAttribute("data-slot-period-filter");
    const dayBlock = filterBtn.closest(".slot-day-block");
    if (!dayBlock) return;

    dayBlock.querySelectorAll("[data-slot-period-filter]").forEach((btn) => {
      btn.classList.toggle("btn-primary", btn === filterBtn);
      btn.classList.toggle("btn-outline-primary", btn !== filterBtn);
    });

    dayBlock.querySelectorAll(".slot-choice-col").forEach((col) => {
      col.classList.toggle(
        "d-none",
        period !== "all" && col.getAttribute("data-period") !== period
      );
    });
    return;
  }

  const addWorkingHoursRow = e.target.closest("[data-working-hours-add]");
  if (addWorkingHoursRow) {
    const day = addWorkingHoursRow.closest("[data-working-hours-day]");
    const rows = day?.querySelector("[data-working-hours-rows]");
    if (!day || !rows) return;

    const weekday = day.getAttribute("data-weekday");
    const index = Number(day.getAttribute("data-next-index") || "0");
    const defaults = workingHoursDefaultsForNewRow(rows);
    rows.insertAdjacentHTML("beforeend", workingHoursRowTemplate(weekday, index, defaults.start, defaults.end));
    day.setAttribute("data-next-index", String(index + 1));
    setWorkingHoursDayOpen(day, true);
    rows.querySelector("[data-working-hours-row]:last-child select")?.focus();
    return;
  }

  const removeWorkingHoursRow = e.target.closest("[data-working-hours-remove]");
  if (removeWorkingHoursRow) {
    const row = removeWorkingHoursRow.closest("[data-working-hours-row]");
    const day = row?.closest("[data-working-hours-day]");
    const rows = row?.parentElement;
    if (!row || !rows) return;

    if (rows.querySelectorAll("[data-working-hours-row]").length <= 1) {
      if (day) {
        setWorkingHoursDayOpen(day, false);
      } else {
        row.querySelectorAll("select").forEach((select) => {
          select.value = "";
        });
        row.querySelector("select")?.focus();
      }
      return;
    }

    row.remove();
    if (day) {
      setWorkingHoursDayOpen(day, workingHoursRowsHaveValues(day));
    }
    return;
  }

  // Day card selection (show pre-rendered slot block, no page reload)
  const dayCard = e.target.closest(".week-day[data-date]");
  if (dayCard) {
    e.preventDefault();
    const date = dayCard.getAttribute("data-date");
    const region = document.getElementById("booking-week-region");
    if (!region) return;

    region.querySelectorAll(".week-day").forEach((c) =>
      c.classList.remove("week-day--selected")
    );
    dayCard.classList.add("week-day--selected");

    region.querySelectorAll(".slot-day-block").forEach((block) => {
      block.classList.toggle(
        "d-none",
        block.getAttribute("data-date") !== date
      );
    });

    history.pushState({}, "", dayCard.href);
    return;
  }
});
