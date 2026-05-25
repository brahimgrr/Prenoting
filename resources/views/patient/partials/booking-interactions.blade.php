@once
  <script>
    (() => {
      function fetchAndReplace(url, replaceSelector, scrollTargetId = null, historyUrl = url, replaceMode = "outer") {
        const target = document.querySelector(replaceSelector);
        if (!target) {
          window.location.href = historyUrl;
          return;
        }

        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
          .then((response) => {
            if (!response.ok) throw new Error();
            return response.text();
          })
          .then((html) => {
            if (replaceMode === "inner") {
              target.innerHTML = html;
            } else {
              target.outerHTML = html;
            }

            history.pushState({}, "", historyUrl);
            if (scrollTargetId) {
              document.getElementById(scrollTargetId)?.scrollIntoView({ block: "start" });
            }
          })
          .catch(() => {
            window.location.href = historyUrl;
          });
      }

      document.addEventListener("click", (event) => {
        const serviceCard = event.target.closest(".service-choice-card[href]");
        if (serviceCard) {
          event.preventDefault();
          fetchAndReplace(serviceCard.href, ".booking-wizard", "booking-step-day");
          return;
        }

        const arrow = event.target.closest("[data-week-url]");
        const isDisabled = arrow
          ? (arrow.tagName === "BUTTON" ? arrow.disabled : arrow.getAttribute("aria-disabled") === "true")
          : false;

        if (arrow && !isDisabled) {
          event.preventDefault();
          const weekUrl = arrow.getAttribute("data-week-url");
          const pageUrl = arrow.getAttribute("data-page-url");

          document.querySelectorAll("[data-week-url]").forEach((button) => {
            if (button.tagName === "BUTTON") {
              button.disabled = true;
            } else {
              button.setAttribute("aria-disabled", "true");
            }
          });

          fetchAndReplace(weekUrl, "#booking-week-region", null, pageUrl, "inner");
          return;
        }

        const filterButton = event.target.closest("[data-slot-period-filter]");
        if (filterButton) {
          const period = filterButton.getAttribute("data-slot-period-filter");
          const dayBlock = filterButton.closest(".slot-day-block");
          if (!dayBlock) return;

          dayBlock.querySelectorAll("[data-slot-period-filter]").forEach((button) => {
            button.classList.toggle("btn-primary", button === filterButton);
            button.classList.toggle("btn-outline-primary", button !== filterButton);
          });

          dayBlock.querySelectorAll(".slot-choice-col").forEach((column) => {
            column.classList.toggle(
              "d-none",
              period !== "all" && column.getAttribute("data-period") !== period
            );
          });
          return;
        }

        const slotButton = event.target.closest(".slot-time-button");
        if (slotButton) {
          event.preventDefault();
          fetchAndReplace(slotButton.href, ".booking-wizard", "booking-confirm");
          return;
        }

        const dayCard = event.target.closest(".week-day[data-date]");
        if (dayCard) {
          event.preventDefault();
          const date = dayCard.getAttribute("data-date");
          const region = document.getElementById("booking-week-region");
          if (!region) return;

          region.querySelectorAll(".week-day").forEach((card) => {
            card.classList.remove("week-day--selected");
            card.classList.remove("border-primary", "bg-primary", "bg-opacity-10");
          });
          dayCard.classList.add("week-day--selected");
          dayCard.classList.add("border-primary", "bg-primary", "bg-opacity-10");

          region.querySelectorAll(".slot-day-block").forEach((block) => {
            block.classList.toggle(
              "d-none",
              block.getAttribute("data-date") !== date
            );
          });

          history.pushState({}, "", dayCard.href);
        }
      });
    })();
  </script>
@endonce
