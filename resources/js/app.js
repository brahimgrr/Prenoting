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

function openAvailabilityPreviewModal() {
  const modal = document.querySelector("[data-availability-preview-open]");
  if (!modal) return;

  bootstrap.Modal.getOrCreateInstance(modal).show();
}

function setAvailabilityLunchCardState(toggle) {
  const card = toggle.closest("[data-availability-lunch-card]");
  if (!card) return;

  const collapsed = !toggle.checked;
  card.classList.toggle("availability-lunch-card--collapsed", collapsed);
  card.querySelectorAll("[data-availability-lunch-fields] input").forEach((input) => {
    input.disabled = collapsed;
  });
}

function syncAvailabilityLunchCards() {
  document
    .querySelectorAll("[data-availability-lunch-toggle]")
    .forEach(setAvailabilityLunchCardState);
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
  openAvailabilityPreviewModal();
  syncAvailabilityLunchCards();
  tickAgendaNowMarker();
  setInterval(tickAgendaNowMarker, 30_000);
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

document.addEventListener("change", (e) => {
  const lunchToggle = e.target.closest("[data-availability-lunch-toggle]");
  if (!lunchToggle) return;

  setAvailabilityLunchCardState(lunchToggle);
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

  const editAvailabilityPreview = e.target.closest("[data-availability-edit-preview]");
  if (editAvailabilityPreview) {
    const modal = editAvailabilityPreview.closest(".modal");
    if (!modal) return;

    modal.querySelector("[data-availability-form-step]")?.classList.remove("d-none");
    modal.querySelector("[data-availability-preview-step]")?.classList.add("d-none");
    modal.querySelector("[data-availability-form-actions]")?.classList.remove("d-none");
    modal.querySelector("[data-availability-preview-actions]")?.classList.add("d-none");
    modal.querySelector("[data-availability-form-step] input:not([type='hidden'])")?.focus();
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
