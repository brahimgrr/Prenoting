const STATUS_VARIANTS = {
  confirmed: "text-bg-success",
  completed: "text-bg-primary",
  cancelled: "text-bg-danger",
  canceled: "text-bg-danger",
  pending: "text-bg-warning",
  scheduled: "text-bg-info",
  draft: "text-bg-secondary",
};

const STATUS_LABELS = {
  confirmed: "Confermato",
  checked_in: "Accettato",
  completed: "Completato",
  cancelled: "Annullato",
  canceled: "Annullato",
  no_show: "Assente",
  pending: "In attesa",
  scheduled: "Programmato",
  draft: "Bozza",
};

function labelForStatus(value) {
  const normalized = String(value).toLowerCase();
  return STATUS_LABELS[normalized] ?? String(value).replace(/[_-]/g, " ");
}

export default function StatusBadge({ status = "pending", children }) {
  const normalizedStatus = String(status).toLowerCase();
  const variant = STATUS_VARIANTS[normalizedStatus] ?? "text-bg-secondary";

  return (
    <span className={`badge rounded-pill ${variant}`}>
      {children ?? labelForStatus(status)}
    </span>
  );
}
