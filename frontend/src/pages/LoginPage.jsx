import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { routeForRole, useAuth } from "../auth/AuthContext";

function FieldError({ errors }) {
  if (!errors) {
    return null;
  }

  const messages = Array.isArray(errors) ? errors : [errors];

  return <div className="invalid-feedback d-block">{messages.join(" ")}</div>;
}

function errorText(errors) {
  return Array.isArray(errors) ? errors.join(" ") : errors;
}

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [form, setForm] = useState({ username: "", password: "" });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  function updateField(event) {
    setForm((currentForm) => ({
      ...currentForm,
      [event.target.name]: event.target.value,
    }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setErrors({});

    try {
      const currentUser = await login(form.username, form.password);
      const fallbackRoute = routeForRole(currentUser);
      navigate(fallbackRoute ? location.state?.from?.pathname || fallbackRoute : "/unsupported-role", { replace: true });
    } catch (error) {
      setErrors(error.response?.data ?? { detail: "Unable to sign in. Try again." });
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="auth-page">
      <section className="auth-panel">
        <div className="auth-panel__header">
          <span className="app-brand__mark">M</span>
          <div>
            <h1>Sign in</h1>
            <p>Access the medical appointment portal.</p>
          </div>
        </div>

        {errors.detail && <div className="alert alert-danger">{errorText(errors.detail)}</div>}
        {errors.non_field_errors && <div className="alert alert-danger">{errorText(errors.non_field_errors)}</div>}

        <form onSubmit={handleSubmit} noValidate>
          <div className="mb-3">
            <label className="form-label" htmlFor="username">
              Username or email
            </label>
            <input
              className={`form-control${errors.username ? " is-invalid" : ""}`}
              id="username"
              name="username"
              autoComplete="username"
              value={form.username}
              onChange={updateField}
              required
            />
            <FieldError errors={errors.username} />
          </div>

          <div className="mb-4">
            <label className="form-label" htmlFor="password">
              Password
            </label>
            <input
              className={`form-control${errors.password ? " is-invalid" : ""}`}
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              value={form.password}
              onChange={updateField}
              required
            />
            <FieldError errors={errors.password} />
          </div>

          <button className="btn btn-primary w-100" type="submit" disabled={submitting}>
            {submitting ? "Signing in..." : "Sign in"}
          </button>
        </form>

        <p className="auth-panel__footer">
          New patient? <Link to="/register">Create an account</Link>
        </p>
      </section>
    </main>
  );
}
