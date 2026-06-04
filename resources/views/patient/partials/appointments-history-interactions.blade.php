@once
  <script>
    (() => {
      function fetchAndReplace(url, replaceSelector) {
        const target = document.querySelector(replaceSelector);
        if (!target) {
          window.location.href = url;
          return;
        }

        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
          .then((response) => {
            if (!response.ok) throw new Error();
            return response.text();
          })
          .then((html) => {
            target.outerHTML = html;
            history.pushState({}, "", url);
          })
          .catch(() => {
            window.location.href = url;
          });
      }

      function historyPlaceholderHtml() {
        return `
          <div class="appointment-history-placeholder mt-4" data-appointments-history-placeholder>
            <a
              class="btn btn-link px-0 py-1 text-decoration-none fw-semibold"
              href="/patient/appointments?show_history=1"
              data-appointments-history-reveal
            >Visualizza storico appuntamenti</a>
          </div>
        `;
      }

      function collapseHistory(replaceSelector) {
        const target = document.querySelector(replaceSelector);
        if (!target) {
          window.location.href = "/patient/appointments";
          return;
        }

        target.outerHTML = historyPlaceholderHtml();
        history.pushState({}, "", "/patient/appointments");
      }

      document.addEventListener("click", (event) => {
        const historyReveal = event.target.closest("[data-appointments-history-reveal]");
        if (historyReveal) {
          event.preventDefault();
          fetchAndReplace(historyReveal.href, "[data-appointments-history-placeholder]");
          return;
        }

        const historyHide = event.target.closest("[data-appointments-history-hide]");
        if (historyHide) {
          event.preventDefault();
          collapseHistory("[data-appointments-history]");
          return;
        }

      });

      document.addEventListener("change", (event) => {
        const historyFilter = event.target.closest("[data-appointments-history-filter]");
        if (!historyFilter) return;

        fetchAndReplace(historyFilter.value, "[data-appointments-history]");
      });
    })();
  </script>
@endonce
