import "bootstrap";

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

document.addEventListener("DOMContentLoaded", positionAgendaScroll);

document.addEventListener("click", (e) => {
  const servicesArrow = e.target.closest("[data-landing-services-prev], [data-landing-services-next]");
  if (servicesArrow) {
    e.preventDefault();
    const track = document.querySelector("[data-landing-services-track]");
    if (!track) return;

    const direction = servicesArrow.hasAttribute("data-landing-services-prev") ? -1 : 1;
    const card = track.querySelector(".landing-card");
    const visibleWidth = track.clientWidth;
    const cardWidth = card ? card.getBoundingClientRect().width + 16 : 300;
    const scrollAmount = Math.max(cardWidth, visibleWidth - cardWidth);
    track.scrollBy({ left: direction * scrollAmount, behavior: "smooth" });
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
