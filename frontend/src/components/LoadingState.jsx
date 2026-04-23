export default function LoadingState({ label = "Loading" }) {
  return (
    <div className="loading-state" role="status" aria-live="polite">
      <div className="spinner-border text-primary" aria-hidden="true" />
      <span className="fw-medium text-secondary">{label}</span>
    </div>
  );
}
