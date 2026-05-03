import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import WasilAdmin from './components/WasilAdmin';
import AdminLogin from './components/AdminLogin';

function App() {
    const [token, setToken] = useState(localStorage.getItem('admin_token'));

    const handleLogin = (newToken) => {
        localStorage.setItem('admin_token', newToken);
        setToken(newToken);
    };

    const handleLogout = () => {
        localStorage.removeItem('admin_token');
        setToken(null);
    };

    if (!token) {
        return <AdminLogin onLogin={handleLogin} />;
    }

    return <WasilAdmin onLogout={handleLogout} />;
}

const container = document.getElementById('admin-root');
if (container) {
    createRoot(container).render(<App />);
}