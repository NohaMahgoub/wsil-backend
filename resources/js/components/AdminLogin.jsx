import { useState } from "react";

const C = {
  bg:       "#0F1623",
  surface:  "#161E2E",
  border:   "#242E44",
  gold:     "#C9A84C",
  goldLight:"#E8C870",
  white:    "#FFFFFF",
  textPri:  "#E8EDF5",
  textSec:  "#8A96B0",
  textMuted:"#4A5568",
  red:      "#EF4444",
  redBg:    "rgba(239,68,68,0.12)",
};

export default function AdminLogin({ onLogin }) {
  const [email,    setEmail]    = useState("");
  const [password, setPassword] = useState("");
  const [error,    setError]    = useState("");
  const [loading,  setLoading]  = useState(false);

  const handleLogin = async () => {
    setError("");
    setLoading(true);

    try {
      // Step 1 — get CSRF cookie first
      await fetch("/sanctum/csrf-cookie", {
        method: "GET",
        credentials: "include",
      });

      // Step 2 — then login
      const res = await fetch("/api/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept":       "application/json",
        },
        credentials: "include",
        body: JSON.stringify({ email, password }),
      });

      const data = await res.json();
      console.log("Login response:", data);
      if (!res.ok) {
        setError(data.message || "Invalid credentials.");
        setLoading(false);
        return;
      }

      // Make sure the user is an admin
     const role = Array.isArray(data.role) ? data.role[0] : data.role;
     if (role !== "admin") {
        setError("Access denied. Admin accounts only.");
        setLoading(false);
        return;
      }

      onLogin(data.token);

    } catch (e) {
      setError("Connection error. Make sure the server is running.");
      setLoading(false);
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === "Enter") handleLogin();
  };

  return (
    <div style={{
      minHeight: "100vh",
      background: C.bg,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      fontFamily: "'Tajawal', sans-serif",
      position: "relative",
      overflow: "hidden",
    }}>
      {/* Background decoration */}
      <div style={{
        position: "absolute",
        width: 600, height: 600,
        borderRadius: "50%",
        background: `radial-gradient(circle, rgba(201,168,76,0.06) 0%, transparent 70%)`,
        top: "50%", left: "50%",
        transform: "translate(-50%, -50%)",
        pointerEvents: "none",
      }} />

      <div style={{
        width: 400,
        background: C.surface,
        borderRadius: 20,
        padding: "40px 36px",
        border: `1px solid ${C.border}`,
        boxShadow: "0 40px 80px rgba(0,0,0,0.4)",
        position: "relative",
      }}>
        {/* Logo */}
        <div style={{ textAlign: "center", marginBottom: 36 }}>
          <div style={{
            width: 56, height: 56, borderRadius: 14,
            background: `linear-gradient(135deg, ${C.gold}, ${C.goldLight})`,
            display: "flex", alignItems: "center", justifyContent: "center",
            margin: "0 auto 16px",
            fontSize: 24, fontWeight: 900, color: C.bg,
          }}>W</div>
          <div style={{ fontSize: 22, fontWeight: 800, letterSpacing: "3px", color: C.textPri }}>WASIL</div>
          <div style={{ fontSize: 12, color: C.textMuted, letterSpacing: "2px", marginTop: 4 }}>ADMIN CONSOLE</div>
        </div>

        {/* Error */}
        {error && (
          <div style={{
            background: C.redBg, border: `1px solid ${C.red}`,
            borderRadius: 10, padding: "10px 14px",
            fontSize: 13, color: C.red, marginBottom: 20,
          }}>
            ⚠ {error}
          </div>
        )}

        {/* Email */}
        <div style={{ marginBottom: 16 }}>
          <div style={{ fontSize: 11, fontWeight: 700, color: C.textSec, textTransform: "uppercase", letterSpacing: "0.5px", marginBottom: 8 }}>
            Email Address
          </div>
          <input
            type="email"
            value={email}
            onChange={e => setEmail(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="admin@wasil.sa"
            style={{
              width: "100%", padding: "12px 14px",
              background: "#0F1623",
              border: `1.5px solid ${C.border}`,
              borderRadius: 10, fontSize: 14,
              color: C.textPri, outline: "none",
              boxSizing: "border-box",
              fontFamily: "inherit",
            }}
          />
        </div>

        {/* Password */}
        <div style={{ marginBottom: 28 }}>
          <div style={{ fontSize: 11, fontWeight: 700, color: C.textSec, textTransform: "uppercase", letterSpacing: "0.5px", marginBottom: 8 }}>
            Password
          </div>
          <input
            type="password"
            value={password}
            onChange={e => setPassword(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="••••••••"
            style={{
              width: "100%", padding: "12px 14px",
              background: "#0F1623",
              border: `1.5px solid ${C.border}`,
              borderRadius: 10, fontSize: 14,
              color: C.textPri, outline: "none",
              boxSizing: "border-box",
              fontFamily: "inherit",
            }}
          />
        </div>

        {/* Login Button */}
        <button
          onClick={handleLogin}
          disabled={loading}
          style={{
            width: "100%", padding: "14px",
            background: loading
              ? C.border
              : `linear-gradient(135deg, ${C.gold}, ${C.goldLight})`,
            border: "none", borderRadius: 12,
            fontWeight: 700, fontSize: 15,
            color: loading ? C.textMuted : C.bg,
            cursor: loading ? "not-allowed" : "pointer",
            letterSpacing: "0.5px",
            transition: "all 0.2s",
          }}>
          {loading ? "Signing in..." : "Sign In →"}
        </button>

        <div style={{ textAlign: "center", marginTop: 20, fontSize: 12, color: C.textMuted }}>
          Wasil Logistics Platform · Admin Only
        </div>
      </div>
    </div>
  );
}