import "bootstrap";

document.addEventListener("click", (e) => {
  // Appointment cancellation panel
  const cancelPanelTrigger = e.target.closest("[data-cancel-panel-target]");
  if (cancelPanelTrigger) {
    e.preventDefault();
    const target = cancelPanelTrigger.getAttribute("data-cancel-panel-target");
    const panel = target ? document.querySelector(target) : null;
    if (!panel) return;

    panel.classList.remove("d-none");
    panel.setAttribute("aria-hidden", "false");

    const reasonInput = panel.querySelector("[name='cancellation_reason']");
    if (reasonInput) reasonInput.focus();
    return;
  }

  const cancelPanelClose = e.target.closest("[data-cancel-panel-close]");
  if (cancelPanelClose) {
    e.preventDefault();
    const panel = cancelPanelClose.closest(".appointment-cancel-panel");
    if (!panel) return;

    panel.classList.add("d-none");
    panel.setAttribute("aria-hidden", "true");

    const trigger = document.querySelector(`[data-cancel-panel-target="#${panel.id}"]`);
    if (trigger) trigger.focus();
    return;
  }

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

document.addEventListener("change", (e) => {
  if (e.target.classList.contains("month-jump-select") && e.target.value) {
    window.location.href = e.target.value;
  }
});
