import axios from "axios";

const UNSAFE_METHODS = new Set(["post", "put", "patch", "delete"]);

function getCookie(name) {
  if (typeof document === "undefined") {
    return "";
  }

  return document.cookie
    .split(";")
    .map((cookie) => cookie.trim())
    .find((cookie) => cookie.startsWith(`${name}=`))
    ?.slice(name.length + 1) ?? "";
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api",
  withCredentials: true,
});

export function backendBaseURL() {
  return api.defaults.baseURL.replace(/\/api\/?$/, "");
}

api.interceptors.request.use((config) => {
  const method = config.method?.toLowerCase();

  if (method && UNSAFE_METHODS.has(method)) {
    const csrfToken = decodeURIComponent(getCookie("csrftoken"));
    if (csrfToken) {
      config.headers = config.headers ?? {};
      config.headers["X-CSRFToken"] = csrfToken;
    }
  }

  return config;
});

export async function ensureCsrfToken() {
  if (!getCookie("csrftoken")) {
    await api.get("/auth/csrf/");
  }
}

export default api;
