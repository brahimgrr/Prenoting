import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import api, { ensureCsrfToken } from "../api/client";

const AuthContext = createContext(null);

export function routeForRole(userOrRole) {
  const role = typeof userOrRole === "string" ? userOrRole : userOrRole?.role;

  if (role === "patient") {
    return "/patient";
  }

  if (role === "doctor") {
    return "/doctor";
  }

  if (role === "staff") {
    return "/staff";
  }

  return null;
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const refreshUser = useCallback(async () => {
    try {
      const response = await api.get("/auth/me/");
      setUser(response.data);
      return response.data;
    } catch (error) {
      if ([401, 403].includes(error.response?.status)) {
        setUser(null);
        return null;
      }

      throw error;
    }
  }, []);

  useEffect(() => {
    let active = true;

    async function loadUser() {
      try {
        const currentUser = await refreshUser();
        if (!active) {
          return;
        }
        setUser(currentUser);
      } catch {
        if (active) {
          setUser(null);
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    loadUser();

    return () => {
      active = false;
    };
  }, [refreshUser]);

  const login = useCallback(async (username, password) => {
    await ensureCsrfToken();
    await api.post("/auth/login/", { username, password });
    return refreshUser();
  }, [refreshUser]);

  const logout = useCallback(async () => {
    await ensureCsrfToken();
    await api.post("/auth/logout/");
    setUser(null);
  }, []);

  const register = useCallback(async (payload) => {
    await ensureCsrfToken();
    await api.post("/auth/register/", payload);
    await api.post("/auth/login/", {
      username: payload.username,
      password: payload.password,
    });
    return refreshUser();
  }, [refreshUser]);

  const value = useMemo(
    () => ({
      user,
      loading,
      login,
      logout,
      register,
      refreshUser,
    }),
    [loading, login, logout, refreshUser, register, user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used within AuthProvider");
  }

  return context;
}
