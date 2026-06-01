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

      document.addEventListener("click", (event) => {
        const historyFilter = event.target.closest("[data-appointments-history-filter]");
        if (!historyFilter) return;

        event.preventDefault();
        fetchAndReplace(historyFilter.href, "[data-appointments-history]");
      });
    })();
  </script>
@endonce
