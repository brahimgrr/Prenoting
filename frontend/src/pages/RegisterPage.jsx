import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
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

const initialForm = {
  first_name: "",
  last_name: "",
  username: "",
  password: "",
  phone: "",
};

export default function RegisterPage() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState(initialForm);
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
      const currentUser = await register(form);
      navigate(routeForRole(currentUser), { replace: true });
    } catch (error) {
      setErrors(error.response?.data ?? { detail: "Unable to create the account. Try again." });
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="auth-page">
      <section className="auth-panel auth-panel--wide">
        <div className="auth-panel__header">
          <span className="app-brand__mark">M</span>
          <div>
            <h1>Create account</h1>
            <p>Register as a patient to book and manage appointments.</p>
          </div>
        </div>

        {errors.detail && <div className="alert alert-danger">{errorText(errors.detail)}</div>}
        {errors.non_field_errors && <div className="alert alert-danger">{errorText(errors.non_field_errors)}</div>}

        <form onSubmit={handleSubmit} noValidate>
          <div className="row g-3">
            <div className="col-md-6">
              <label className="form-label" htmlFor="first_name">
                First name
              </label>
              <input
                className={`form-control${errors.first_name ? " is-invalid" : ""}`}
                id="first_name"
                name="first_name"
                autoComplete="given-name"
                value={form.first_name}
                onChange={updateField}
              />
              <FieldError errors={errors.first_name} />
            </div>

            <div className="col-md-6">
              <label className="form-label" htmlFor="last_name">
                Last name
              </label>
              <input
                className={`form-control${errors.last_name ? " is-invalid" : ""}`}
                id="last_name"
                name="last_name"
                autoComplete="family-name"
                value={form.last_name}
                onChange={updateField}
              />
              <FieldError errors={errors.last_name} />
            </div>

            <div className="col-12">
              <label className="form-label" htmlFor="username">
                Email username
              </label>
              <input
                className={`form-control${errors.username ? " is-invalid" : ""}`}
                id="username"
                name="username"
                type="email"
                autoComplete="email"
                value={form.username}
                onChange={updateField}
                required
              />
              <FieldError errors={errors.username} />
            </div>

            <div className="col-md-6">
              <label className="form-label" htmlFor="password">
                Password
              </label>
              <input
                className={`form-control${errors.password ? " is-invalid" : ""}`}
                id="password"
                name="password"
                type="password"
                autoComplete="new-password"
                value={form.password}
                onChange={updateField}
                required
              />
              <FieldError errors={errors.password} />
            </div>

            <div className="col-md-6">
              <label className="form-label" htmlFor="phone">
                Phone
              </label>
              <input
                className={`form-control${errors.phone ? " is-invalid" : ""}`}
                id="phone"
                name="phone"
                type="tel"
                autoComplete="tel"
                value={form.phone}
                onChange={updateField}
                required
              />
              <FieldError errors={errors.phone} />
            </div>
          </div>

          <button className="btn btn-primary w-100 mt-4" type="submit" disabled={submitting}>
            {submitting ? "Creating account..." : "Create account"}
          </button>
        </form>

        <p className="auth-panel__footer">
          Already registered? <Link to="/login">Sign in</Link>
        </p>
      </section>
    </main>
  );
}
