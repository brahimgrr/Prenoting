const STATUS_VARIANTS = {
  confirmed: "text-bg-success",
  completed: "text-bg-primary",
  cancelled: "text-bg-danger",
  canceled: "text-bg-danger",
  pending: "text-bg-warning",
  scheduled: "text-bg-info",
  draft: "text-bg-secondary",
};

function titleize(value) {
  return String(value)
    .replace(/[_-]/g, " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function StatusBadge({ status = "pending", children }) {
  const normalizedStatus = String(status).toLowerCase();
  const variant = STATUS_VARIANTS[normalizedStatus] ?? "text-bg-secondary";

  return (
    <span className={`badge rounded-pill ${variant}`}>
      {children ?? titleize(status)}
    </span>
  );
}
