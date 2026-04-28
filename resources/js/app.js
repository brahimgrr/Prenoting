import "bootstrap";

function initSlotPeriodFilters() {
  document.querySelectorAll("[data-slot-period-filter]").forEach((button) => {
    button.addEventListener("click", () => {
      const period = button.getAttribute("data-slot-period-filter");

      document.querySelectorAll("[data-slot-period-filter]").forEach((item) => {
        item.classList.toggle("btn-primary", item === button);
        item.classList.toggle("btn-outline-primary", item !== button);
      });

      document.querySelectorAll(".slot-choice-col").forEach((slot) => {
        slot.classList.toggle("d-none", period !== "all" && slot.getAttribute("data-period") !== period);
      });
    });
  });
}

function initMonthJumpSelects() {
  document.querySelectorAll(".month-jump-select").forEach((select) => {
    select.addEventListener("change", () => {
      if (select.value) {
        window.location.href = select.value;
      }
    });
  });
}

document.addEventListener("DOMContentLoaded", () => {
  initSlotPeriodFilters();
  initMonthJumpSelects();
});
